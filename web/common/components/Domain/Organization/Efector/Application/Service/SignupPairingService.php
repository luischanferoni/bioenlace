<?php

namespace common\components\Domain\Organization\Efector\Application\Service;

use common\models\Organization\SignupPairing;
use Yii;
use yii\db\Connection;
use yii\db\Query;

/**
 * Vínculo de un solo uso entre el navegador que inicia el alta web y el teléfono que
 * verifica la identidad.
 *
 * El token (para el QR) y el código (que se muestra en la computadora) se devuelven
 * una sola vez y se persisten hasheados. Las transiciones son `UPDATE` condicionados
 * por el estado anterior: si la fila no cambia, la operación falla. No conoce HTTP,
 * sesión ni request: el controlador aporta los identificadores de sesión.
 */
final class SignupPairingService
{
    private const TOKEN_BYTES = 16;
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    private const CODE_LENGTH = 6;
    private const CODE_GROUP_SIZE = 3;

    private const DEFAULT_CLAIM_TTL_SECONDS = 180;
    private const DEFAULT_VERIFICATION_TTL_SECONDS = 600;
    private const DEFAULT_MAX_TTL_SECONDS = 900;
    private const DEFAULT_CODE_MAX_ATTEMPTS = 5;

    /** Estado sintético cuando el vínculo no existe o no pertenece a la sesión. */
    public const STATE_NOT_FOUND = 'NOT_FOUND';

    private Connection $db;

    /** @var array<string, mixed> */
    private array $config;

    /**
     * @param array<string, mixed> $config overrides de los knobs `webSignupPairing` (tests)
     */
    public function __construct(Connection $db, array $config = [])
    {
        $this->db = $db;
        $this->config = $config;
    }

    /**
     * Crea el vínculo. Devuelve el token para el QR y el código para la computadora;
     * el código queda hasheado en base y sólo se muestra cuando el teléfono lo reclama.
     *
     * @param array{id_efector?: int} $context
     * @return array{pairing_id: string, token: string, code: string, expires_at: int}
     */
    public function start(string $webSessionId, array $context = []): array
    {
        $this->assertSessionId($webSessionId);

        $now = time();
        $pairingId = bin2hex(random_bytes(self::TOKEN_BYTES));
        $token = bin2hex(random_bytes(self::TOKEN_BYTES));
        $code = $this->generateCode();
        $expiresAt = $now + $this->claimTtlSeconds();
        $idEfector = (int) ($context['id_efector'] ?? 0);

        $this->db->createCommand()->insert(SignupPairing::tableName(), [
            'id' => $pairingId,
            'token_hash' => $this->hashValue($token),
            'code_hash' => $this->hashCode($code),
            'code_attempts' => 0,
            'id_efector' => $idEfector > 0 ? $idEfector : null,
            'web_session_hash' => $this->hashValue($webSessionId),
            'device_session_hash' => null,
            'state' => SignupPairing::STATE_PENDING,
            'didit_session_id' => null,
            'verification_id' => null,
            'id_persona' => null,
            'id_user' => null,
            'expires_at' => $expiresAt,
            'claimed_at' => null,
            'authorized_at' => null,
            'verified_at' => null,
            'completed_at' => null,
            'created_at' => $now,
        ])->execute();

        $this->log($pairingId, '-', SignupPairing::STATE_PENDING);

        return [
            'pairing_id' => $pairingId,
            'token' => $token,
            'code' => $this->formatCode($code),
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Reclama el vínculo desde el teléfono. El token se consume: un segundo teléfono
     * que abre la misma URL recibe `already_used`.
     *
     * @return array{pairing_id: string, id_efector: int|null, expires_at: int}
     */
    public function claim(string $token, string $deviceSessionId): array
    {
        $this->assertSessionId($deviceSessionId);
        $token = trim($token);
        if ($token === '') {
            throw new SignupPairingException(SignupPairingException::NOT_FOUND);
        }

        $row = $this->findByTokenHash($this->hashValue($token));
        if ($row === null) {
            throw new SignupPairingException(SignupPairingException::NOT_FOUND);
        }

        $row = $this->refreshExpiry($row);
        if ($row['state'] !== SignupPairing::STATE_PENDING) {
            throw new SignupPairingException(
                $row['state'] === SignupPairing::STATE_EXPIRED
                    ? SignupPairingException::EXPIRED
                    : SignupPairingException::ALREADY_USED
            );
        }

        $now = time();
        $expiresAt = min(
            $now + $this->verificationTtlSeconds(),
            (int) $row['created_at'] + $this->maxTtlSeconds()
        );

        $updated = $this->db->createCommand()->update(SignupPairing::tableName(), [
            'state' => SignupPairing::STATE_CLAIMED,
            'device_session_hash' => $this->hashValue($deviceSessionId),
            'claimed_at' => $now,
            'expires_at' => $expiresAt,
        ], [
            'id' => (string) $row['id'],
            'state' => SignupPairing::STATE_PENDING,
        ])->execute();

        if ($updated !== 1) {
            throw new SignupPairingException(SignupPairingException::ALREADY_USED);
        }

        $this->log((string) $row['id'], SignupPairing::STATE_PENDING, SignupPairing::STATE_CLAIMED);

        return [
            'pairing_id' => (string) $row['id'],
            'id_efector' => $row['id_efector'] !== null ? (int) $row['id_efector'] : null,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Valida el código que el usuario transcribe en el teléfono.
     *
     * @throws SignupPairingException con `remainingAttempts` cuando el código no coincide
     */
    public function authorizeDevice(string $pairingId, string $code, string $deviceSessionId): void
    {
        $row = $this->requireDeviceRow($pairingId, $deviceSessionId, SignupPairing::STATE_CLAIMED);
        $maxAttempts = $this->codeMaxAttempts();

        if ((int) $row['code_attempts'] >= $maxAttempts) {
            $this->fail($pairingId);
            throw new SignupPairingException(SignupPairingException::ATTEMPTS_EXHAUSTED);
        }

        if (!hash_equals((string) $row['code_hash'], $this->hashCode($code))) {
            $attempts = (int) $row['code_attempts'] + 1;
            $exhausted = $attempts >= $maxAttempts;
            $this->db->createCommand()->update(SignupPairing::tableName(), [
                'code_attempts' => $attempts,
                'state' => $exhausted ? SignupPairing::STATE_FAILED : SignupPairing::STATE_CLAIMED,
            ], [
                'id' => $pairingId,
                'state' => SignupPairing::STATE_CLAIMED,
            ])->execute();

            throw new SignupPairingException(
                $exhausted
                    ? SignupPairingException::ATTEMPTS_EXHAUSTED
                    : SignupPairingException::INVALID_CODE,
                max(0, $maxAttempts - $attempts)
            );
        }

        $this->transition(
            $pairingId,
            SignupPairing::STATE_CLAIMED,
            SignupPairing::STATE_AUTHORIZED,
            ['authorized_at' => time()],
            $deviceSessionId
        );
    }

    /**
     * Registra la sesión hosted de Didit creada para este vínculo.
     */
    public function markVerificationStarted(
        string $pairingId,
        string $deviceSessionId,
        string $diditSessionId
    ): void {
        $diditSessionId = trim($diditSessionId);
        if ($diditSessionId === '') {
            throw new \InvalidArgumentException('El identificador de la sesión Didit es requerido.');
        }

        $this->requireDeviceRow($pairingId, $deviceSessionId, SignupPairing::STATE_AUTHORIZED);
        $this->transition(
            $pairingId,
            SignupPairing::STATE_AUTHORIZED,
            SignupPairing::STATE_VERIFICATION_STARTED,
            ['didit_session_id' => $diditSessionId],
            $deviceSessionId
        );
    }

    /**
     * Deja el vínculo verificado. El `verification_id` es siempre el que confirmó el
     * servidor contra Didit, nunca uno enviado por un cliente.
     */
    public function markVerified(string $pairingId, string $deviceSessionId, string $verificationId): void
    {
        $verificationId = trim($verificationId);
        if ($verificationId === '') {
            throw new \InvalidArgumentException('El identificador de la verificación es requerido.');
        }

        $this->requireDeviceRow($pairingId, $deviceSessionId, SignupPairing::STATE_VERIFICATION_STARTED);
        $this->transition(
            $pairingId,
            SignupPairing::STATE_VERIFICATION_STARTED,
            SignupPairing::STATE_VERIFIED,
            ['verification_id' => $verificationId, 'verified_at' => time()],
            $deviceSessionId
        );
    }

    /**
     * Marca el vínculo como fallido (verificación rechazada por Didit).
     */
    public function markFailed(string $pairingId, string $deviceSessionId): void
    {
        $this->requireDeviceRow($pairingId, $deviceSessionId, SignupPairing::STATE_VERIFICATION_STARTED);
        $this->transition(
            $pairingId,
            SignupPairing::STATE_VERIFICATION_STARTED,
            SignupPairing::STATE_FAILED,
            [],
            $deviceSessionId
        );
    }

    /**
     * Estado del vínculo para el teléfono que lo reclamó.
     *
     * Un pedido desde otra sesión devuelve `NOT_FOUND`: no se distingue de un vínculo
     * inexistente para no filtrar información.
     *
     * @return array{state: string, id_efector: int|null, didit_session_id: string|null, expires_at: int|null}
     */
    public function deviceStatus(string $pairingId, string $deviceSessionId): array
    {
        $this->assertSessionId($deviceSessionId);

        $notFound = [
            'state' => self::STATE_NOT_FOUND,
            'id_efector' => null,
            'didit_session_id' => null,
            'expires_at' => null,
        ];

        $row = (new Query())
            ->from(SignupPairing::tableName())
            ->where(['id' => trim($pairingId)])
            ->one($this->db);

        if (!is_array($row)) {
            return $notFound;
        }

        $row = $this->refreshExpiry($row);

        $deviceHash = $row['device_session_hash'];
        if (!is_string($deviceHash) || $deviceHash === ''
            || !hash_equals($deviceHash, $this->hashValue($deviceSessionId))) {
            return $notFound;
        }

        return [
            'state' => (string) $row['state'],
            'id_efector' => $row['id_efector'] !== null ? (int) $row['id_efector'] : null,
            'didit_session_id' => $row['didit_session_id'] !== null ? (string) $row['didit_session_id'] : null,
            'expires_at' => (int) $row['expires_at'],
        ];
    }

    /**
     * Cierra el vínculo con la cuenta creada o reutilizada.
     */
    public function markAccountCreated(string $pairingId, int $idPersona, int $idUser): void
    {
        if ($idPersona <= 0 || $idUser <= 0) {
            throw new \InvalidArgumentException('La persona y el usuario son obligatorios para cerrar el alta.');
        }

        $this->transition(
            $pairingId,
            SignupPairing::STATE_VERIFIED,
            SignupPairing::STATE_ACCOUNT_CREATED,
            [
                'id_persona' => $idPersona,
                'id_user' => $idUser,
                'completed_at' => time(),
            ]
        );
    }

    /**
     * Estado visible para la computadora que inició el vínculo.
     *
     * @return array{state: string, expires_at?: int}
     */
    public function status(string $webSessionId, string $pairingId): array
    {
        $this->assertSessionId($webSessionId);

        $row = (new Query())
            ->from(SignupPairing::tableName())
            ->where([
                'id' => trim($pairingId),
                'web_session_hash' => $this->hashValue($webSessionId),
            ])
            ->one($this->db);

        if (!is_array($row)) {
            return ['state' => self::STATE_NOT_FOUND];
        }

        $row = $this->refreshExpiry($row);

        return [
            'state' => (string) $row['state'],
            'expires_at' => (int) $row['expires_at'],
        ];
    }

    /**
     * Cancela el alta desde la computadora. Idempotente si ya estaba cancelada.
     */
    public function cancel(string $webSessionId, string $pairingId): void
    {
        $this->assertSessionId($webSessionId);

        $row = (new Query())
            ->from(SignupPairing::tableName())
            ->where([
                'id' => trim($pairingId),
                'web_session_hash' => $this->hashValue($webSessionId),
            ])
            ->one($this->db);

        if (!is_array($row)) {
            throw new SignupPairingException(SignupPairingException::NOT_FOUND);
        }

        $row = $this->refreshExpiry($row);
        if ($row['state'] === SignupPairing::STATE_CANCELLED) {
            return;
        }
        if (in_array($row['state'], SignupPairing::terminalStateValues(), true)) {
            throw new SignupPairingException($this->reasonForState((string) $row['state']));
        }

        $updated = $this->db->createCommand()->update(SignupPairing::tableName(), [
            'state' => SignupPairing::STATE_CANCELLED,
        ], [
            'id' => (string) $row['id'],
            'state' => (string) $row['state'],
        ])->execute();

        if ($updated !== 1) {
            throw new SignupPairingException(SignupPairingException::INVALID_STATE);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findByTokenHash(string $tokenHash): ?array
    {
        $row = (new Query())
            ->from(SignupPairing::tableName())
            ->where(['token_hash' => $tokenHash])
            ->one($this->db);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function requireDeviceRow(string $pairingId, string $deviceSessionId, string $expectedState): array
    {
        $this->assertSessionId($deviceSessionId);

        $row = (new Query())
            ->from(SignupPairing::tableName())
            ->where(['id' => trim($pairingId)])
            ->one($this->db);

        if (!is_array($row)) {
            throw new SignupPairingException(SignupPairingException::NOT_FOUND);
        }

        $row = $this->refreshExpiry($row);
        if ($row['state'] !== $expectedState) {
            throw new SignupPairingException($this->reasonForState((string) $row['state']));
        }

        $deviceHash = $row['device_session_hash'];
        if (!is_string($deviceHash) || $deviceHash === ''
            || !hash_equals($deviceHash, $this->hashValue($deviceSessionId))) {
            throw new SignupPairingException(SignupPairingException::DEVICE_MISMATCH);
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function transition(
        string $pairingId,
        string $fromState,
        string $toState,
        array $values,
        ?string $deviceSessionId = null
    ): void {
        $condition = ['id' => trim($pairingId), 'state' => $fromState];
        if ($deviceSessionId !== null) {
            $condition['device_session_hash'] = $this->hashValue($deviceSessionId);
        }

        $updated = $this->db->createCommand()->update(
            SignupPairing::tableName(),
            array_merge($values, ['state' => $toState]),
            $condition
        )->execute();

        if ($updated !== 1) {
            throw new SignupPairingException(SignupPairingException::INVALID_STATE);
        }

        $this->log($pairingId, $fromState, $toState);
    }

    /**
     * Traza de estados sin secretos: ni token ni código aparecen en el log.
     */
    private function log(string $pairingId, string $fromState, string $toState): void
    {
        Yii::info(
            sprintf('signup-pairing %s: %s -> %s', $pairingId, $fromState, $toState),
            'signup.pairing'
        );
    }

    /**
     * Marca `EXPIRED` cuando el vínculo venció en su etapa actual.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function refreshExpiry(array $row): array
    {
        $state = (string) $row['state'];
        if (in_array($state, SignupPairing::terminalStateValues(), true)) {
            return $row;
        }
        if ((int) $row['expires_at'] > time()) {
            return $row;
        }

        $this->db->createCommand()->update(SignupPairing::tableName(), [
            'state' => SignupPairing::STATE_EXPIRED,
        ], [
            'id' => (string) $row['id'],
            'state' => $state,
        ])->execute();

        $this->log((string) $row['id'], $state, SignupPairing::STATE_EXPIRED);

        $row['state'] = SignupPairing::STATE_EXPIRED;

        return $row;
    }

    private function fail(string $pairingId): void
    {
        $this->db->createCommand()->update(SignupPairing::tableName(), [
            'state' => SignupPairing::STATE_FAILED,
        ], [
            'id' => trim($pairingId),
            'state' => SignupPairing::STATE_CLAIMED,
        ])->execute();

        $this->log(trim($pairingId), SignupPairing::STATE_CLAIMED, SignupPairing::STATE_FAILED);
    }

    private function reasonForState(string $state): string
    {
        return match ($state) {
            SignupPairing::STATE_EXPIRED => SignupPairingException::EXPIRED,
            SignupPairing::STATE_PENDING,
            SignupPairing::STATE_CLAIMED,
            SignupPairing::STATE_AUTHORIZED,
            SignupPairing::STATE_VERIFICATION_STARTED,
            SignupPairing::STATE_VERIFIED,
            SignupPairing::STATE_ACCOUNT_CREATED,
            SignupPairing::STATE_CANCELLED,
            SignupPairing::STATE_FAILED => SignupPairingException::INVALID_STATE,
            default => SignupPairingException::NOT_FOUND,
        };
    }

    private function generateCode(): string
    {
        $max = strlen(self::CODE_ALPHABET) - 1;
        $code = '';
        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= self::CODE_ALPHABET[random_int(0, $max)];
        }

        return $code;
    }

    private function formatCode(string $code): string
    {
        return implode('-', str_split($code, self::CODE_GROUP_SIZE));
    }

    private function hashCode(string $code): string
    {
        return hash('sha256', $this->normalizeCode($code));
    }

    private function normalizeCode(string $code): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $code));
    }

    private function hashValue(string $value): string
    {
        return hash('sha256', $value);
    }

    private function assertSessionId(string $sessionId): void
    {
        if (trim($sessionId) === '') {
            throw new \InvalidArgumentException('La sesión es requerida para operar el vínculo del alta.');
        }
    }

    private function claimTtlSeconds(): int
    {
        return $this->intConfig('claimTtlSeconds', self::DEFAULT_CLAIM_TTL_SECONDS, 30);
    }

    private function verificationTtlSeconds(): int
    {
        return $this->intConfig('verificationTtlSeconds', self::DEFAULT_VERIFICATION_TTL_SECONDS, 60);
    }

    private function maxTtlSeconds(): int
    {
        return $this->intConfig('maxTtlSeconds', self::DEFAULT_MAX_TTL_SECONDS, 60);
    }

    private function codeMaxAttempts(): int
    {
        return $this->intConfig('codeMaxAttempts', self::DEFAULT_CODE_MAX_ATTEMPTS, 1);
    }

    private function intConfig(string $key, int $default, int $minimum): int
    {
        $value = $this->config[$key]
            ?? (Yii::$app->params['webSignupPairing'][$key] ?? null);

        return max($minimum, (int) ($value ?? $default));
    }
}
