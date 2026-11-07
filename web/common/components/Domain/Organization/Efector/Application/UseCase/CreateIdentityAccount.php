<?php

namespace common\components\Domain\Organization\Efector\Application\UseCase;

use common\components\Domain\Person\Identity\Application\Service\DiditIdentityService;
use common\components\Domain\Person\Identity\Application\Service\IdentitySignupAttemptStore;
use common\components\Domain\Person\Identity\Application\Service\IdentityVerificationException;
use common\models\Person\Persona;
use common\models\Platform\User;
use Yii;

/**
 * Crea la cuenta de identidad (Persona + User passwordless) a partir de una
 * verificación Didit. No crea efector, plan ni pago: es el paso previo común
 * a clínica/centro, consultorio y profesionales de una organización.
 */
final class CreateIdentityAccount
{
    private const IDENTITY_LOCK_TIMEOUT_SECONDS = 10;

    /**
     * @param array{verification_id: string, email?: string} $payload
     * @return array{persona: Persona, user: User, es_nueva_persona: bool, es_nuevo_usuario: bool, didit: array<string, mixed>}
     */
    public function crear(array $payload): array
    {
        $verificationId = trim((string) ($payload['verification_id'] ?? ''));
        $email = trim((string) ($payload['email'] ?? ''));
        $this->assertVerificationPayload($verificationId, $email);

        $identityService = new DiditIdentityService();
        $attemptStore = new IdentitySignupAttemptStore();
        try {
            $diditResult = $identityService->fetchApprovedVerification($verificationId);
        } catch (IdentityVerificationException $e) {
            $attemptStore->recordVerification($verificationId, $e->verificationStatus);
            throw $e;
        }
        $attemptStore->recordVerification($verificationId, 'approved');

        return $this->persistVerifiedIdentity(
            $identityService,
            $attemptStore,
            $diditResult,
            $verificationId,
            $email
        );
    }

    /**
     * Crea la cuenta desde una verificación ya aprobada (p. ej. identidad de
     * prueba sandbox), sin consultar Didit.
     *
     * @param array<string, mixed> $diditResult
     * @return array{persona: Persona, user: User, es_nueva_persona: bool, es_nuevo_usuario: bool, didit: array<string, mixed>}
     */
    public function crearDesdeVerificacionAprobada(
        array $diditResult,
        string $verificationId,
        string $email = ''
    ): array {
        $this->assertVerificationPayload($verificationId, $email);

        $attemptStore = new IdentitySignupAttemptStore();
        $attemptStore->recordVerification($verificationId, 'approved');

        return $this->persistVerifiedIdentity(
            new DiditIdentityService(),
            $attemptStore,
            $diditResult,
            $verificationId,
            $email
        );
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function assertVerificationPayload(string $verificationId, string $email): void
    {
        if ($verificationId === '') {
            throw new \InvalidArgumentException('verification_id (Didit) es requerido.');
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('E-mail inválido.');
        }
    }

    /**
     * @param array<string, mixed> $diditResult
     * @return array{persona: Persona, user: User, es_nueva_persona: bool, es_nuevo_usuario: bool, didit: array<string, mixed>}
     */
    private function persistVerifiedIdentity(
        DiditIdentityService $identityService,
        IdentitySignupAttemptStore $attemptStore,
        array $diditResult,
        string $verificationId,
        string $email
    ): array {
        $lockName = $this->identityLockName($diditResult);
        $lockAcquired = Yii::$app->db->createCommand(
            'SELECT GET_LOCK(:lockName, :timeout)'
        )->bindValues([
            ':lockName' => $lockName,
            ':timeout' => self::IDENTITY_LOCK_TIMEOUT_SECONDS,
        ])->queryScalar();
        if ((int) $lockAcquired !== 1) {
            throw new \RuntimeException('No se pudo reservar la identidad para completar el alta; reintentá en unos segundos.');
        }

        try {
            $account = Yii::$app->db->transaction(function () use (
                $identityService,
                $attemptStore,
                $diditResult,
                $verificationId,
                $email
            ): array {
                $resolved = $identityService->upsertVerifiedPersona(
                    $diditResult,
                    $verificationId,
                    null,
                    ['email' => $email]
                );
                $persona = $resolved['persona'];
                [$user, $esNuevoUsuario] = $this->ensureUserForPersona($persona, $email);
                $this->ensurePatientRole($user);
                $attemptStore->markAccountCreated(
                    $verificationId,
                    (int) $persona->id_persona,
                    (int) $user->id
                );

                return [
                    'persona' => $persona,
                    'user' => $user,
                    'es_nueva_persona' => (bool) $resolved['es_nueva'],
                    'es_nuevo_usuario' => $esNuevoUsuario,
                ];
            });
        } finally {
            $lockReleased = Yii::$app->db->createCommand(
                'SELECT RELEASE_LOCK(:lockName)'
            )->bindValue(':lockName', $lockName)->queryScalar();
            if ((int) $lockReleased !== 1) {
                Yii::error(
                    'No se pudo liberar el bloqueo de alta de identidad ' . $lockName,
                    __METHOD__
                );
            }
        }

        return [
            'persona' => $account['persona'],
            'user' => $account['user'],
            'es_nueva_persona' => $account['es_nueva_persona'],
            'es_nuevo_usuario' => $account['es_nuevo_usuario'],
            'didit' => $diditResult,
        ];
    }

    /**
     * Garantiza un User passwordless vinculado a la Persona (reusa el existente).
     *
     * @return array{0: User, 1: bool} user y flag de usuario recién creado
     */
    public function ensureUserForPersona(Persona $persona, string $email): array
    {
        if ((int) $persona->id_user > 0) {
            $existing = User::findOne((int) $persona->id_user);
            if ($existing !== null) {
                return [$existing, false];
            }
        }

        return [$this->createPasswordlessUser($persona, $email), true];
    }

    private function ensurePatientRole(User $user): void
    {
        if (!Yii::$app->has('authManager')) {
            throw new \RuntimeException('No se pudo asignar el perfil Paciente a la cuenta.');
        }

        $authManager = Yii::$app->authManager;
        $role = $authManager->getRole('paciente');
        if ($role === null) {
            throw new \RuntimeException('No se encontró el perfil Paciente.');
        }
        if ($authManager->getAssignment('paciente', (string) $user->id) === null) {
            $authManager->assign($role, (string) $user->id);
        }
    }

    private function createPasswordlessUser(Persona $persona, string $email): User
    {
        $user = new User();
        $user->username = $this->uniqueUsername($persona, $email);
        $user->email = $email !== '' ? $email : $this->fallbackEmail($persona);
        $user->status = User::STATUS_ACTIVE;
        $user->setPassword(Yii::$app->security->generateRandomString(32));
        $user->password_set_at = null;
        $user->email_confirmed = 0;
        $user->generateAuthKey();
        if (!$user->save()) {
            throw new \InvalidArgumentException('No se pudo crear el usuario: ' . json_encode($user->getErrors()));
        }

        $persona->id_user = (int) $user->id;
        $persona->scenario = Persona::SCENARIOUSERUPDATE;
        if (!$persona->save(false)) {
            throw new \RuntimeException('No se pudo vincular usuario a persona.');
        }

        return $user;
    }

    private function uniqueUsername(Persona $persona, string $email): string
    {
        $base = $email !== ''
            ? (strstr($email, '@', true) ?: 'staff')
            : 'staff_' . $persona->documento;
        $base = substr((string) preg_replace('/[^a-z0-9_]/i', '_', $base), 0, 40) ?: 'staff';
        $candidate = $base;
        $i = 0;
        while (User::find()->where(['username' => $candidate])->exists()) {
            $i++;
            $candidate = substr($base, 0, 36) . '_' . $i;
        }

        return $candidate;
    }

    private function fallbackEmail(Persona $persona): string
    {
        return $this->uniqueUsername($persona, '') . '@accounts.bioenlace.invalid';
    }

    /**
     * MySQL named locks serialize concurrent first-time signups for one identity,
     * including the case where no Persona row exists yet.
     *
     * @param array<string, mixed> $diditResult
     */
    private function identityLockName(array $diditResult): string
    {
        $documento = preg_replace('/\D+/', '', (string) ($diditResult['documento'] ?? '')) ?? '';
        $fechaNacimiento = trim((string) ($diditResult['fecha_nacimiento'] ?? ''));
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $fechaNacimiento, $matches)) {
            $fechaNacimiento = $matches[3] . '-' . $matches[2] . '-' . $matches[1];
        } elseif (preg_match('/^(\d{4}-\d{2}-\d{2})/', $fechaNacimiento, $matches)) {
            $fechaNacimiento = $matches[1];
        }

        return 'signup-persona-' . substr(hash('sha256', $documento . '|' . $fechaNacimiento), 0, 48);
    }
}
