<?php

namespace frontend\components;

use common\models\Platform\User;
use RuntimeException;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\CredentialRecord;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\Exception\AuthenticatorResponseVerificationException;
use Webauthn\Exception\InvalidDataException;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;
use Yii;
use yii\db\Query;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;

final class PasskeyAuthenticationService
{
    private const CHALLENGE_TTL_SECONDS = 300;

    private static ?SerializerInterface $serializer = null;

    public static function registrationOptions(User $user, string $sessionId): array
    {
        if ((int) $user->status !== User::STATUS_ACTIVE) {
            throw new ForbiddenHttpException('La cuenta no está habilitada para registrar una passkey.');
        }

        $relyingParty = self::relyingParty();
        $challenge = self::createChallenge('passkey-register', (int) $user->id, $sessionId, $relyingParty);
        $excludedCredentials = [];

        foreach (self::activeCredentials((int) $user->id) as $credential) {
            $excludedCredentials[] = PublicKeyCredentialDescriptor::create(
                'public-key',
                self::base64UrlDecode((string) $credential['credential_id']),
                []
            );
        }

        $options = PublicKeyCredentialCreationOptions::create(
            PublicKeyCredentialRpEntity::create('Bioenlace', $relyingParty['rpId']),
            PublicKeyCredentialUserEntity::create(
                (string) ($user->username ?: $user->email ?: $user->id),
                (string) $user->id,
                (string) ($user->username ?: $user->email ?: 'Usuario Bioenlace')
            ),
            $challenge['raw'],
            [
                PublicKeyCredentialParameters::createPk(-7),
                PublicKeyCredentialParameters::createPk(-257),
            ],
            AuthenticatorSelectionCriteria::create(null, 'required', 'required'),
            'none',
            $excludedCredentials,
            60000
        );

        return [
            'challengeId' => $challenge['id'],
            'publicKey' => self::decodeJson(self::serializer()->serialize($options, 'json')),
        ];
    }

    public static function registerCredential(
        User $user,
        string $sessionId,
        string $challengeId,
        array $credential,
        string $name
    ): void {
        $challenge = self::claimChallenge($challengeId, 'passkey-register', (int) $user->id, $sessionId);

        try {
            $publicKeyCredential = self::deserializeCredential($credential);
            if (!$publicKeyCredential->response instanceof AuthenticatorAttestationResponse) {
                throw new BadRequestHttpException('La respuesta no corresponde al registro de una passkey.');
            }

            $creationOptions = self::creationOptions($challenge, $user);
            $factory = new CeremonyStepManagerFactory();
            $factory->setAllowedOrigins([(string) $challenge['origin']]);
            $validator = AuthenticatorAttestationResponseValidator::create(
                $factory->creationCeremony()
            );
            $credentialRecord = $validator->check(
                $publicKeyCredential->response,
                $creationOptions,
                self::originHost($challenge['origin'])
            );

            if (!hash_equals($credentialRecord->userHandle, (string) $user->id)) {
                throw new BadRequestHttpException('La passkey no pertenece a la cuenta actual.');
            }

            $rawCredentialId = self::credentialIdFromPayload($credential);
            if (!hash_equals($credentialRecord->publicKeyCredentialId, $rawCredentialId)) {
                throw new BadRequestHttpException('El identificador de la passkey no coincide.');
            }

            Yii::$app->db->createCommand()->insert('{{%webauthn_credential}}', [
                'credential_id_hash' => hash('sha256', $rawCredentialId),
                'id_user' => (int) $user->id,
                'credential_id' => self::base64UrlEncode($rawCredentialId),
                'credential_source' => self::serializer()->serialize($credentialRecord, 'json'),
                'name' => self::credentialName($name),
                'created_at' => time(),
                'last_used_at' => null,
                'revoked_at' => null,
            ])->execute();

            self::finishChallenge($challengeId, 'consumed');
        } catch (Throwable $e) {
            self::finishChallenge($challengeId, 'failed');
            Yii::warning('Passkey registration rejected: ' . $e->getMessage(), __METHOD__);
            if (
                $e instanceof BadRequestHttpException
                || $e instanceof ForbiddenHttpException
                || $e instanceof \yii\db\Exception
                || $e instanceof RuntimeException
            ) {
                throw $e;
            }

            if (!$e instanceof AuthenticatorResponseVerificationException && !$e instanceof InvalidDataException) {
                throw $e;
            }

            throw new BadRequestHttpException('No se pudo verificar el registro de la passkey.', 0, $e);
        }
    }

    public static function authenticationOptions(string $sessionId): array
    {
        $relyingParty = self::relyingParty();
        $challenge = self::createChallenge('passkey-login', null, $sessionId, $relyingParty);
        $options = PublicKeyCredentialRequestOptions::create(
            $challenge['raw'],
            $relyingParty['rpId'],
            [],
            'required',
            60000
        );

        return [
            'challengeId' => $challenge['id'],
            'publicKey' => self::decodeJson(self::serializer()->serialize($options, 'json')),
        ];
    }

    public static function authenticate(
        string $sessionId,
        string $challengeId,
        array $credential
    ): User {
        $challenge = self::claimChallenge($challengeId, 'passkey-login', null, $sessionId);

        try {
            $publicKeyCredential = self::deserializeCredential($credential);
            if (!$publicKeyCredential->response instanceof AuthenticatorAssertionResponse) {
                throw new BadRequestHttpException('La respuesta no corresponde al inicio de sesión.');
            }

            $rawCredentialId = self::credentialIdFromPayload($credential);
            $credentialRow = (new Query())
                ->from('{{%webauthn_credential}}')
                ->where([
                    'credential_id_hash' => hash('sha256', $rawCredentialId),
                    'revoked_at' => null,
                ])
                ->one();
            if ($credentialRow === false) {
                throw new BadRequestHttpException('La passkey no está registrada o fue revocada.');
            }

            $record = self::serializer()->deserialize(
                (string) $credentialRow['credential_source'],
                CredentialRecord::class,
                'json'
            );
            if (!$record instanceof CredentialRecord) {
                throw new RuntimeException('No se pudo cargar el registro de la passkey.');
            }

            $userHandle = $publicKeyCredential->response->userHandle;
            if (!is_string($userHandle) || !hash_equals($record->userHandle, $userHandle)) {
                throw new BadRequestHttpException('La passkey no coincide con la cuenta.');
            }

            $requestOptions = PublicKeyCredentialRequestOptions::create(
                self::base64UrlDecode((string) $challenge['challenge']),
                (string) $challenge['rp_id'],
                [],
                'required',
                60000
            );
            $factory = new CeremonyStepManagerFactory();
            $factory->setAllowedOrigins([(string) $challenge['origin']]);
            $validator = AuthenticatorAssertionResponseValidator::create($factory->requestCeremony());
            $updatedRecord = $validator->check(
                $record,
                $publicKeyCredential->response,
                $requestOptions,
                self::originHost((string) $challenge['origin']),
                $userHandle
            );

            $user = User::findIdentity((int) $credentialRow['id_user']);
            if (!$user instanceof User || (int) $user->status !== User::STATUS_ACTIVE) {
                throw new BadRequestHttpException('La cuenta no está habilitada para iniciar sesión.');
            }

            Yii::$app->db->createCommand()->update('{{%webauthn_credential}}', [
                'credential_source' => self::serializer()->serialize($updatedRecord, 'json'),
                'last_used_at' => time(),
            ], [
                'credential_id_hash' => $credentialRow['credential_id_hash'],
                'id_user' => (int) $user->id,
                'revoked_at' => null,
            ])->execute();

            self::finishChallenge($challengeId, 'consumed');

            return $user;
        } catch (Throwable $e) {
            self::finishChallenge($challengeId, 'failed');
            Yii::warning('Passkey authentication rejected: ' . $e->getMessage(), __METHOD__);
            if ($e instanceof BadRequestHttpException || $e instanceof ForbiddenHttpException) {
                throw $e;
            }
            if ($e instanceof \yii\db\Exception || $e instanceof RuntimeException) {
                throw $e;
            }
            if (!$e instanceof AuthenticatorResponseVerificationException && !$e instanceof InvalidDataException) {
                throw $e;
            }

            throw new BadRequestHttpException('No se pudo verificar la passkey.', 0, $e);
        }
    }

    private static function creationOptions(array $challenge, User $user): PublicKeyCredentialCreationOptions
    {
        $excludedCredentials = [];
        foreach (self::activeCredentials((int) $user->id) as $credential) {
            $excludedCredentials[] = PublicKeyCredentialDescriptor::create(
                'public-key',
                self::base64UrlDecode((string) $credential['credential_id']),
                []
            );
        }

        return PublicKeyCredentialCreationOptions::create(
            PublicKeyCredentialRpEntity::create('Bioenlace', (string) $challenge['rp_id']),
            PublicKeyCredentialUserEntity::create(
                (string) ($user->username ?: $user->email ?: $user->id),
                (string) $user->id,
                (string) ($user->username ?: $user->email ?: 'Usuario Bioenlace')
            ),
            self::base64UrlDecode((string) $challenge['challenge']),
            [
                PublicKeyCredentialParameters::createPk(-7),
                PublicKeyCredentialParameters::createPk(-257),
            ],
            AuthenticatorSelectionCriteria::create(null, 'required', 'required'),
            'none',
            $excludedCredentials,
            60000
        );
    }

    private static function deserializeCredential(array $credential): PublicKeyCredential
    {
        $json = json_encode($credential, JSON_THROW_ON_ERROR);
        $decoded = self::serializer()->deserialize($json, PublicKeyCredential::class, 'json');
        if (!$decoded instanceof PublicKeyCredential) {
            throw new BadRequestHttpException('La respuesta de la passkey no es válida.');
        }

        return $decoded;
    }

    private static function credentialIdFromPayload(array $credential): string
    {
        $rawId = $credential['rawId'] ?? null;
        if (!is_string($rawId) || $rawId === '') {
            throw new BadRequestHttpException('Falta el identificador de la passkey.');
        }

        return self::base64UrlDecode($rawId);
    }

    private static function createChallenge(
        string $purpose,
        ?int $userId,
        string $sessionId,
        array $relyingParty
    ): array {
        $rawChallenge = random_bytes(32);
        $challengeId = bin2hex(random_bytes(32));
        $challenge = self::base64UrlEncode($rawChallenge);
        $now = time();

        Yii::$app->db->createCommand()->insert('{{%auth_challenge}}', [
            'id' => $challengeId,
            'id_user' => $userId,
            'purpose' => $purpose,
            'challenge_hash' => hash('sha256', $rawChallenge),
            'challenge' => $challenge,
            'session_hash' => hash('sha256', $sessionId),
            'rp_id' => $relyingParty['rpId'],
            'origin' => $relyingParty['origin'],
            'state' => 'pending',
            'approved_user_id' => null,
            'expires_at' => $now + self::CHALLENGE_TTL_SECONDS,
            'consumed_at' => null,
            'created_at' => $now,
        ])->execute();

        return ['id' => $challengeId, 'raw' => $rawChallenge];
    }

    private static function claimChallenge(
        string $challengeId,
        string $purpose,
        ?int $userId,
        string $sessionId
    ): array {
        $conditions = [
            'id' => $challengeId,
            'purpose' => $purpose,
            'session_hash' => hash('sha256', $sessionId),
            'state' => 'pending',
        ];
        if ($userId !== null) {
            $conditions['id_user'] = $userId;
        }

        $updated = Yii::$app->db->createCommand()->update('{{%auth_challenge}}', [
            'state' => 'processing',
        ], ['and', $conditions, ['>', 'expires_at', time()]])->execute();
        if ($updated !== 1) {
            throw new BadRequestHttpException('El desafío expiró o ya fue utilizado. Volvé a intentarlo.');
        }

        $challenge = (new Query())
            ->from('{{%auth_challenge}}')
            ->where(['id' => $challengeId, 'state' => 'processing'])
            ->one();
        if ($challenge === false) {
            throw new BadRequestHttpException('No se encontró el desafío de autenticación.');
        }

        $rawChallenge = self::base64UrlDecode((string) $challenge['challenge']);
        if (!hash_equals((string) $challenge['challenge_hash'], hash('sha256', $rawChallenge))) {
            self::finishChallenge($challengeId, 'failed');
            throw new BadRequestHttpException('El desafío de autenticación no es válido.');
        }

        return $challenge;
    }

    private static function finishChallenge(string $challengeId, string $state): void
    {
        Yii::$app->db->createCommand()->update('{{%auth_challenge}}', [
            'state' => $state,
            'consumed_at' => time(),
        ], [
            'id' => $challengeId,
            'state' => 'processing',
        ])->execute();
    }

    private static function activeCredentials(int $userId): array
    {
        return (new Query())
            ->from('{{%webauthn_credential}}')
            ->where(['id_user' => $userId, 'revoked_at' => null])
            ->all();
    }

    private static function relyingParty(): array
    {
        $config = Yii::$app->params['webAuthn'] ?? [];
        $rpId = strtolower(trim((string) ($config['rpId'] ?? '')));
        $origin = (string) Yii::$app->request->getHostInfo();
        $allowedOrigins = $config['allowedOrigins'] ?? [];
        $isDev = defined('YII_ENV') && YII_ENV === 'dev';
        $hostName = strtolower((string) Yii::$app->request->hostName);

        if ($isDev && in_array($hostName, ['localhost', '127.0.0.1', '::1'], true)) {
            $rpId = $hostName;
            $allowedOrigins = [$origin];
        }

        $parsedOrigin = parse_url($origin);
        if (
            $rpId === ''
            || !is_array($parsedOrigin)
            || !isset($parsedOrigin['host'])
            || !in_array($origin, $allowedOrigins, true)
            || strtolower((string) $parsedOrigin['host']) !== $rpId
            || (($parsedOrigin['scheme'] ?? '') !== 'https'
                && !($isDev && $rpId === 'localhost'))
        ) {
            throw new RuntimeException('La configuración de WebAuthn no coincide con el origen de la aplicación.');
        }

        return [
            'rpId' => $rpId,
            'origin' => $origin,
        ];
    }

    private static function originHost(string $origin): string
    {
        $host = parse_url($origin, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            throw new RuntimeException('El origen de WebAuthn no tiene un host válido.');
        }

        return $host;
    }

    private static function serializer(): SerializerInterface
    {
        if (self::$serializer === null) {
            $manager = AttestationStatementSupportManager::create();
            self::$serializer = (new WebauthnSerializerFactory($manager))->create();
        }

        return self::$serializer;
    }

    private static function decodeJson(string $json): array
    {
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new RuntimeException('La biblioteca WebAuthn devolvió opciones inválidas.');
        }

        return $decoded;
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string
    {
        $padding = (4 - strlen($value) % 4) % 4;
        $decoded = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', $padding), true);
        if (!is_string($decoded)) {
            throw new BadRequestHttpException('La respuesta de la passkey contiene datos inválidos.');
        }

        return $decoded;
    }

    private static function credentialName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return 'Passkey';
        }

        return mb_substr($name, 0, 100);
    }
}
