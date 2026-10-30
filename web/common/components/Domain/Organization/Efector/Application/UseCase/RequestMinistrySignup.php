<?php

namespace common\components\Domain\Organization\Efector\Application\UseCase;

use common\components\Domain\Organization\Efector\Application\Service\ContactPhoneValidator;
use common\models\Organization\BillingAccount;
use common\models\Organization\BillingSignupRequest;
use common\models\Platform\User;
use Yii;

/**
 * Solicitudes de alta ministerio (asistidas) y aprobación desde admin.
 */
final class RequestMinistrySignup
{
    public const ROLE_ADMIN_MINISTERIO = 'AdminMinisterio';

    private const INVITATION_TOKEN_TTL = 72 * 3600;

    /**
     * @param array<string, mixed> $data
     */
    public static function createRequest(array $data): BillingSignupRequest
    {
        $email = strtolower(trim((string) ($data['contacto_email'] ?? '')));
        $org = trim((string) ($data['nombre_organizacion'] ?? ''));
        $nombre = trim((string) ($data['contacto_nombre'] ?? ''));
        $apellido = trim((string) ($data['contacto_apellido'] ?? ''));

        if ($org === '' || $nombre === '' || $apellido === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Completá organización, nombre, apellido y un e-mail válido.');
        }

        ContactPhoneValidator::assertValid(
            self::nullableString($data['contacto_telefono'] ?? null),
            'Teléfono de contacto'
        );

        $dup = BillingSignupRequest::find()
            ->where([
                'tipo' => BillingSignupRequest::TIPO_MINISTERIO,
                'status' => BillingSignupRequest::STATUS_PENDING,
                'contacto_email' => $email,
                'deleted_at' => null,
            ])
            ->one();
        if ($dup !== null) {
            throw new \InvalidArgumentException('Ya hay una solicitud pendiente con ese e-mail.');
        }

        $row = new BillingSignupRequest();
        $row->tipo = BillingSignupRequest::TIPO_MINISTERIO;
        $row->status = BillingSignupRequest::STATUS_PENDING;
        $row->nombre_organizacion = $org;
        $row->sector = BillingSignupRequest::SECTOR_PUBLICO;
        $row->contacto_nombre = $nombre;
        $row->contacto_apellido = $apellido;
        $row->contacto_email = $email;
        $row->contacto_telefono = self::nullableString($data['contacto_telefono'] ?? null);
        $row->contacto_documento = self::nullableString($data['contacto_documento'] ?? null);
        $row->notas = self::nullableString($data['notas'] ?? null);

        if (!$row->save()) {
            throw new \InvalidArgumentException('No se pudo guardar la solicitud: ' . json_encode($row->getErrors()));
        }

        return $row;
    }

    /**
     * Genera un token de invitación de un solo uso (link a la app) para completar el KYC.
     */
    public static function issueInvitationToken(int $idRequest, ?int $actorUserId = null): string
    {
        $req = BillingSignupRequest::findOne(['id' => $idRequest, 'deleted_at' => null]);
        if ($req === null || $req->tipo !== BillingSignupRequest::TIPO_MINISTERIO) {
            throw new \InvalidArgumentException('Solicitud de ministerio inexistente.');
        }
        if ($req->status !== BillingSignupRequest::STATUS_PENDING) {
            throw new \InvalidArgumentException('La solicitud ya fue resuelta.');
        }

        $plain = bin2hex(random_bytes(24));
        $req->invitation_token_hash = Yii::$app->security->generatePasswordHash($plain);
        $req->invitation_expires_at = date('Y-m-d H:i:s', time() + self::INVITATION_TOKEN_TTL);
        if (!$req->save(false)) {
            throw new \RuntimeException('No se pudo generar el token de invitación.');
        }

        return $plain;
    }

    /**
     * Resuelve una solicitud pendiente por su token de invitación (un solo uso, con TTL).
     */
    public static function resolvePendingByToken(string $token): BillingSignupRequest
    {
        $token = trim($token);
        if ($token === '') {
            throw new \InvalidArgumentException('Token de invitación requerido.');
        }

        $rows = BillingSignupRequest::find()
            ->where([
                'tipo' => BillingSignupRequest::TIPO_MINISTERIO,
                'status' => BillingSignupRequest::STATUS_PENDING,
                'deleted_at' => null,
            ])
            ->andWhere(['not', ['invitation_token_hash' => null]])
            ->all();

        foreach ($rows as $req) {
            if ((string) $req->invitation_expires_at !== '' && strtotime((string) $req->invitation_expires_at) < time()) {
                continue;
            }
            if (Yii::$app->security->validatePassword($token, (string) $req->invitation_token_hash)) {
                return $req;
            }
        }

        throw new \InvalidArgumentException('Token de invitación inválido o expirado.');
    }

    /**
     * Aprueba solicitud de tipo MINISTERIO: cuenta + rol AdminMinisterio.
     *
     * @return array{request: BillingSignupRequest, account: BillingAccount, user_id: int}
     */
    public static function approve(int $idRequest, int $reviewerUserId, ?int $idBillingAccount = null): array
    {
        $req = BillingSignupRequest::findOne(['id' => $idRequest, 'deleted_at' => null]);
        if ($req === null || $req->tipo !== BillingSignupRequest::TIPO_MINISTERIO) {
            throw new \InvalidArgumentException('Solicitud de ministerio inexistente.');
        }
        if ($req->status !== BillingSignupRequest::STATUS_PENDING) {
            throw new \InvalidArgumentException('La solicitud ya fue resuelta.');
        }

        $tx = Yii::$app->db->beginTransaction();
        try {
            if ($idBillingAccount !== null && $idBillingAccount > 0) {
                $account = BillingAccount::findOne(['id' => $idBillingAccount, 'deleted_at' => null]);
                if ($account === null || $account->tipo !== BillingAccount::TIPO_MINISTERIO) {
                    throw new \InvalidArgumentException('La cuenta indicada no es un ministerio activo.');
                }
            } else {
                $account = BillingAccountService::createAccount([
                    'nombre' => $req->nombre_organizacion,
                    'tipo' => BillingAccount::TIPO_MINISTERIO,
                    'notas' => 'Alta asistida desde solicitud #' . $req->id,
                    'activo' => 1,
                ]);
            }

            $user = self::ensureUserForRequest($req);
            $account->owner_user_id = (int) $user->id;
            $account->save(false, ['owner_user_id', 'updated_at']);

            if (!User::assignRole((int) $user->id, self::ROLE_ADMIN_MINISTERIO)) {
                throw new \RuntimeException('No se pudo asignar el rol AdminMinisterio.');
            }

            $req->status = BillingSignupRequest::STATUS_APPROVED;
            $req->id_user = (int) $user->id;
            $req->id_billing_account = (int) $account->id;
            $req->reviewed_by = $reviewerUserId > 0 ? $reviewerUserId : null;
            $req->reviewed_at = date('Y-m-d H:i:s');
            if (!$req->save(false)) {
                throw new \RuntimeException('No se pudo actualizar la solicitud.');
            }

            $tx->commit();

            return [
                'request' => $req,
                'account' => $account,
                'user_id' => (int) $user->id,
            ];
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
    }

    public static function reject(int $idRequest, int $reviewerUserId, ?string $notas = null): BillingSignupRequest
    {
        $req = BillingSignupRequest::findOne(['id' => $idRequest, 'deleted_at' => null]);
        if ($req === null) {
            throw new \InvalidArgumentException('Solicitud inexistente.');
        }
        if ($req->status !== BillingSignupRequest::STATUS_PENDING) {
            throw new \InvalidArgumentException('La solicitud ya fue resuelta.');
        }

        $req->status = BillingSignupRequest::STATUS_REJECTED;
        $req->reviewed_by = $reviewerUserId > 0 ? $reviewerUserId : null;
        $req->reviewed_at = date('Y-m-d H:i:s');
        if ($notas !== null && trim($notas) !== '') {
            $req->notas = trim((string) $req->notas . "\n[Rechazo] " . trim($notas));
        }
        if (!$req->save(false)) {
            throw new \RuntimeException('No se pudo rechazar la solicitud.');
        }

        return $req;
    }

    private static function ensureUserForRequest(BillingSignupRequest $req): User
    {
        $email = strtolower(trim((string) $req->contacto_email));
        $existing = User::findOne(['email' => $email]);
        if ($existing !== null) {
            return $existing;
        }

        $username = self::uniqueUsernameFromEmail($email);
        $user = new User();
        $user->username = $username;
        $user->email = $email;
        $user->status = User::STATUS_ACTIVE;
        $user->setPassword(Yii::$app->security->generateRandomString(32));
        $user->password_set_at = null;
        $user->generateAuthKey();
        $user->email_confirmed = 0;
        if (!$user->save()) {
            throw new \InvalidArgumentException('No se pudo crear el usuario: ' . json_encode($user->getErrors()));
        }

        return $user;
    }

    private static function uniqueUsernameFromEmail(string $email): string
    {
        $base = preg_replace('/[^a-z0-9_]/i', '_', strstr($email, '@', true) ?: 'admin_min');
        $base = substr((string) $base, 0, 40) ?: 'admin_min';
        $candidate = $base;
        $i = 0;
        while (User::find()->where(['username' => $candidate])->exists()) {
            $i++;
            $candidate = substr($base, 0, 36) . '_' . $i;
        }

        return $candidate;
    }

    private static function nullableString(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }
}
