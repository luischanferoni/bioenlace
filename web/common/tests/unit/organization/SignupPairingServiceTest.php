<?php

namespace common\tests\unit\organization;

use Codeception\Test\Unit;
use common\components\Domain\Organization\Efector\Application\Service\SignupPairingException;
use common\components\Domain\Organization\Efector\Application\Service\SignupPairingService;
use common\models\Organization\SignupPairing;
use common\components\Shared\Infrastructure\Migration\MigrationEnumColumn;
use yii\db\Connection;
use yii\db\Query;

/**
 * Vínculo del alta web sobre SQLite en memoria: estados, TTL, intentos y binding de sesión.
 */
class SignupPairingServiceTest extends Unit
{
    private const WEB_SESSION = 'web-session-abc';
    private const DEVICE_SESSION = 'device-session-xyz';
    private const CLAIM_TTL = 180;
    private const VERIFICATION_TTL = 600;
    private const MAX_TTL = 900;
    private const MAX_ATTEMPTS = 3;

    private Connection $db;
    private SignupPairingService $service;

    protected function _before(): void
    {
        $this->db = new Connection(['dsn' => 'sqlite::memory:']);
        $this->db->open();

        $this->db->createCommand(<<<'SQL'
            CREATE TABLE signup_pairing (
                id VARCHAR(32) NOT NULL PRIMARY KEY,
                token_hash VARCHAR(64) NOT NULL,
                code_hash VARCHAR(64) NOT NULL,
                code_attempts INTEGER NOT NULL DEFAULT 0,
                id_efector INTEGER NULL,
                web_session_hash VARCHAR(64) NOT NULL,
                device_session_hash VARCHAR(64) NULL,
                state VARCHAR(24) NOT NULL,
                didit_session_id VARCHAR(128) NULL,
                verification_id VARCHAR(128) NULL,
                id_persona INTEGER NULL,
                id_user INTEGER NULL,
                expires_at INTEGER NOT NULL,
                claimed_at INTEGER NULL,
                authorized_at INTEGER NULL,
                verified_at INTEGER NULL,
                completed_at INTEGER NULL,
                created_at INTEGER NOT NULL
            )
        SQL)->execute();

        $this->service = new SignupPairingService($this->db, [
            'claimTtlSeconds' => self::CLAIM_TTL,
            'verificationTtlSeconds' => self::VERIFICATION_TTL,
            'maxTtlSeconds' => self::MAX_TTL,
            'codeMaxAttempts' => self::MAX_ATTEMPTS,
        ]);
    }

    protected function _after(): void
    {
        $this->db->close();
    }

    public function testStartPersisteSoloHashesYDevuelveSecretosUnaVez(): void
    {
        $started = $this->service->start(self::WEB_SESSION, ['id_efector' => 42]);

        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $started['pairing_id']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $started['token']);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{3}-[A-Z0-9]{3}$/', $started['code']);

        $row = $this->row($started['pairing_id']);
        $this->assertSame(SignupPairing::STATE_PENDING, $row['state']);
        $this->assertSame(hash('sha256', $started['token']), $row['token_hash']);
        $this->assertSame(
            hash('sha256', str_replace('-', '', $started['code'])),
            $row['code_hash']
        );
        $this->assertNotSame($started['token'], $row['token_hash']);
        $this->assertStringNotContainsString(
            str_replace('-', '', $started['code']),
            (string) $row['code_hash']
        );
        $this->assertSame(42, (int) $row['id_efector']);
        $this->assertSame(hash('sha256', self::WEB_SESSION), $row['web_session_hash']);
        $this->assertNull($row['device_session_hash']);
        $this->assertSame((int) $row['created_at'] + self::CLAIM_TTL, (int) $row['expires_at']);
    }

    public function testStartSinEfectorGuardaNull(): void
    {
        $started = $this->service->start(self::WEB_SESSION);

        $this->assertNull($this->row($started['pairing_id'])['id_efector']);
    }

    public function testStartExigeSesion(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->start('   ');
    }

    public function testClaimConsumeElTokenYEnlazaElTelefono(): void
    {
        $started = $this->service->start(self::WEB_SESSION, ['id_efector' => 7]);

        $claimed = $this->service->claim($started['token'], self::DEVICE_SESSION);

        $this->assertSame($started['pairing_id'], $claimed['pairing_id']);
        $this->assertSame(7, $claimed['id_efector']);

        $row = $this->row($started['pairing_id']);
        $this->assertSame(SignupPairing::STATE_CLAIMED, $row['state']);
        $this->assertSame(hash('sha256', self::DEVICE_SESSION), $row['device_session_hash']);
        $this->assertNotNull($row['claimed_at']);
        $this->assertLessThanOrEqual((int) $row['created_at'] + self::MAX_TTL, (int) $row['expires_at']);
        $this->assertSame((int) $row['claimed_at'] + self::VERIFICATION_TTL, (int) $row['expires_at']);
    }

    public function testSegundoTelefonoNoPuedeReclamarElMismoToken(): void
    {
        $started = $this->service->start(self::WEB_SESSION);
        $this->service->claim($started['token'], self::DEVICE_SESSION);

        try {
            $this->service->claim($started['token'], 'otro-telefono');
            $this->fail('El token debería estar consumido.');
        } catch (SignupPairingException $e) {
            $this->assertSame(SignupPairingException::ALREADY_USED, $e->reason);
        }
    }

    public function testClaimConTokenInexistente(): void
    {
        $this->expectException(SignupPairingException::class);

        $this->service->claim(str_repeat('a', 32), self::DEVICE_SESSION);
    }

    public function testClaimVencidoMarcaExpirado(): void
    {
        $started = $this->service->start(self::WEB_SESSION);
        $this->expire($started['pairing_id']);

        try {
            $this->service->claim($started['token'], self::DEVICE_SESSION);
            $this->fail('El vínculo debía estar vencido.');
        } catch (SignupPairingException $e) {
            $this->assertSame(SignupPairingException::EXPIRED, $e->reason);
        }

        $this->assertSame(SignupPairing::STATE_EXPIRED, $this->row($started['pairing_id'])['state']);
    }

    public function testCodigoIncorrectoDescuentaIntentos(): void
    {
        $pairingId = $this->claimedPairing();

        try {
            $this->service->authorizeDevice($pairingId, 'ZZZZZZ', self::DEVICE_SESSION);
            $this->fail('El código no debería coincidir.');
        } catch (SignupPairingException $e) {
            $this->assertSame(SignupPairingException::INVALID_CODE, $e->reason);
            $this->assertSame(self::MAX_ATTEMPTS - 1, $e->remainingAttempts);
        }

        $row = $this->row($pairingId);
        $this->assertSame(1, (int) $row['code_attempts']);
        $this->assertSame(SignupPairing::STATE_CLAIMED, $row['state']);
    }

    public function testIntentosAgotadosInvalidanElVinculo(): void
    {
        $pairingId = $this->claimedPairing();

        for ($attempt = 1; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            try {
                $this->service->authorizeDevice($pairingId, 'ZZZZZZ', self::DEVICE_SESSION);
                $this->fail('El código no debería coincidir.');
            } catch (SignupPairingException $e) {
                $this->assertSame(SignupPairingException::INVALID_CODE, $e->reason);
            }
        }

        try {
            $this->service->authorizeDevice($pairingId, 'ZZZZZZ', self::DEVICE_SESSION);
            $this->fail('Los intentos debían estar agotados.');
        } catch (SignupPairingException $e) {
            $this->assertSame(SignupPairingException::ATTEMPTS_EXHAUSTED, $e->reason);
        }

        $this->assertSame(SignupPairing::STATE_FAILED, $this->row($pairingId)['state']);
    }

    public function testCodigoCorrectoAutorizaAunqueVengaSinGuionYEnMinusculas(): void
    {
        $started = $this->service->start(self::WEB_SESSION);
        $this->service->claim($started['token'], self::DEVICE_SESSION);

        $this->service->authorizeDevice(
            $started['pairing_id'],
            strtolower(str_replace('-', '', $started['code'])),
            self::DEVICE_SESSION
        );

        $row = $this->row($started['pairing_id']);
        $this->assertSame(SignupPairing::STATE_AUTHORIZED, $row['state']);
        $this->assertNotNull($row['authorized_at']);
    }

    public function testOtraSesionDeTelefonoNoPuedeAutorizar(): void
    {
        $started = $this->service->start(self::WEB_SESSION);
        $this->service->claim($started['token'], self::DEVICE_SESSION);

        try {
            $this->service->authorizeDevice($started['pairing_id'], $started['code'], 'otro-telefono');
            $this->fail('Otra sesión no debería operar el vínculo.');
        } catch (SignupPairingException $e) {
            $this->assertSame(SignupPairingException::DEVICE_MISMATCH, $e->reason);
        }
    }

    public function testNoSePuedeMarcarVerificadoSinHaberIniciadoLaVerificacion(): void
    {
        $pairingId = $this->authorizedPairing();

        try {
            $this->service->markVerified($pairingId, self::DEVICE_SESSION, 'didit-123');
            $this->fail('El vínculo no está en verificación.');
        } catch (SignupPairingException $e) {
            $this->assertSame(SignupPairingException::INVALID_STATE, $e->reason);
        }
    }

    public function testRecorridoCompletoHastaLaCuentaCreada(): void
    {
        $started = $this->service->start(self::WEB_SESSION);
        $pairingId = $this->service->claim($started['token'], self::DEVICE_SESSION)['pairing_id'];
        $this->service->authorizeDevice($pairingId, $started['code'], self::DEVICE_SESSION);
        $this->service->markVerificationStarted($pairingId, self::DEVICE_SESSION, 'didit-session-1');
        $this->service->markVerified($pairingId, self::DEVICE_SESSION, 'didit-verification-1');
        $this->service->markAccountCreated($pairingId, 501, 77);

        $row = $this->row($pairingId);
        $this->assertSame(SignupPairing::STATE_ACCOUNT_CREATED, $row['state']);
        $this->assertSame('didit-session-1', $row['didit_session_id']);
        $this->assertSame('didit-verification-1', $row['verification_id']);
        $this->assertSame(501, (int) $row['id_persona']);
        $this->assertSame(77, (int) $row['id_user']);
        $this->assertNotNull($row['verified_at']);
        $this->assertNotNull($row['completed_at']);
        $this->assertSame(
            SignupPairing::STATE_ACCOUNT_CREATED,
            $this->service->status(self::WEB_SESSION, $pairingId)['state']
        );
    }

    public function testMarkAccountCreatedExigePersonaYUsuario(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->markAccountCreated('cualquiera', 0, 0);
    }

    public function testStatusDeOtraSesionDeComputadoraNoEncuentraElVinculo(): void
    {
        $started = $this->service->start(self::WEB_SESSION);

        $this->assertSame(
            SignupPairingService::STATE_NOT_FOUND,
            $this->service->status('otra-computadora', $started['pairing_id'])['state']
        );
    }

    public function testStatusMarcaYDevuelveExpirado(): void
    {
        $started = $this->service->start(self::WEB_SESSION);
        $this->expire($started['pairing_id']);

        $status = $this->service->status(self::WEB_SESSION, $started['pairing_id']);

        $this->assertSame(SignupPairing::STATE_EXPIRED, $status['state']);
        $this->assertSame(SignupPairing::STATE_EXPIRED, $this->row($started['pairing_id'])['state']);
    }

    public function testCancelarEsIdempotenteYBloqueaElReclamo(): void
    {
        $started = $this->service->start(self::WEB_SESSION);

        $this->service->cancel(self::WEB_SESSION, $started['pairing_id']);
        $this->service->cancel(self::WEB_SESSION, $started['pairing_id']);

        $this->assertSame(SignupPairing::STATE_CANCELLED, $this->row($started['pairing_id'])['state']);

        try {
            $this->service->claim($started['token'], self::DEVICE_SESSION);
            $this->fail('Un vínculo cancelado no se reclama.');
        } catch (SignupPairingException $e) {
            $this->assertSame(SignupPairingException::ALREADY_USED, $e->reason);
        }
    }

    public function testCancelarUnVinculoDeOtraComputadoraFalla(): void
    {
        $started = $this->service->start(self::WEB_SESSION);

        $this->expectException(SignupPairingException::class);

        $this->service->cancel('otra-computadora', $started['pairing_id']);
    }

    public function testEstadosDelModeloCoincidenConElEnumDeLaMigracion(): void
    {
        $values = SignupPairing::stateValues();

        $this->assertSame(array_values(array_unique($values)), $values);
        foreach ($values as $value) {
            $this->assertSame(strtoupper($value), $value);
        }
        foreach (SignupPairing::terminalStateValues() as $terminal) {
            $this->assertContains($terminal, $values);
        }

        $enum = MigrationEnumColumn::mysqlEnum($values, SignupPairing::STATE_PENDING, true);
        foreach ($values as $value) {
            $this->assertStringContainsString("'" . $value . "'", $enum);
        }
        $this->assertStringContainsString("DEFAULT '" . SignupPairing::STATE_PENDING . "'", $enum);
    }

    public function testDeviceStatusDevuelveElEstadoAlTelefonoQueReclamo(): void
    {
        $started = $this->service->start(self::WEB_SESSION, ['id_efector' => 9]);
        $pairingId = $this->service->claim($started['token'], self::DEVICE_SESSION)['pairing_id'];

        $status = $this->service->deviceStatus($pairingId, self::DEVICE_SESSION);

        $this->assertSame(SignupPairing::STATE_CLAIMED, $status['state']);
        $this->assertSame(9, $status['id_efector']);
        $this->assertNull($status['didit_session_id']);
    }

    public function testDeviceStatusDesdeOtraSesionNoEncuentraElVinculo(): void
    {
        $started = $this->service->start(self::WEB_SESSION);
        $pairingId = $this->service->claim($started['token'], self::DEVICE_SESSION)['pairing_id'];

        $this->assertSame(
            SignupPairingService::STATE_NOT_FOUND,
            $this->service->deviceStatus($pairingId, 'otro-telefono')['state']
        );
    }

    public function testDeviceStatusExponeLaSesionDeDiditCreada(): void
    {
        $pairingId = $this->authorizedPairing();
        $this->service->markVerificationStarted($pairingId, self::DEVICE_SESSION, 'didit-session-42');

        $status = $this->service->deviceStatus($pairingId, self::DEVICE_SESSION);

        $this->assertSame(SignupPairing::STATE_VERIFICATION_STARTED, $status['state']);
        $this->assertSame('didit-session-42', $status['didit_session_id']);
    }

    public function testMarkFailedSoloDesdeVerificacionIniciada(): void
    {
        $pairingId = $this->authorizedPairing();

        try {
            $this->service->markFailed($pairingId, self::DEVICE_SESSION);
            $this->fail('No debería fallar un vínculo que todavía no verificó.');
        } catch (SignupPairingException $e) {
            $this->assertSame(SignupPairingException::INVALID_STATE, $e->reason);
        }

        $this->service->markVerificationStarted($pairingId, self::DEVICE_SESSION, 'didit-session-9');
        $this->service->markFailed($pairingId, self::DEVICE_SESSION);

        $this->assertSame(SignupPairing::STATE_FAILED, $this->row($pairingId)['state']);
    }

    private function claimedPairing(): string
    {
        $started = $this->service->start(self::WEB_SESSION);

        return $this->service->claim($started['token'], self::DEVICE_SESSION)['pairing_id'];
    }

    private function authorizedPairing(): string
    {
        $started = $this->service->start(self::WEB_SESSION);
        $pairingId = $this->service->claim($started['token'], self::DEVICE_SESSION)['pairing_id'];
        $this->service->authorizeDevice($pairingId, $started['code'], self::DEVICE_SESSION);

        return $pairingId;
    }

    private function expire(string $pairingId): void
    {
        $this->db->createCommand()->update(
            SignupPairing::tableName(),
            ['expires_at' => time() - 1],
            ['id' => $pairingId]
        )->execute();
    }

    /**
     * @return array<string, mixed>
     */
    private function row(string $pairingId): array
    {
        $row = (new Query())
            ->from(SignupPairing::tableName())
            ->where(['id' => $pairingId])
            ->one($this->db);

        $this->assertIsArray($row, 'El vínculo debería existir.');

        return $row;
    }
}
