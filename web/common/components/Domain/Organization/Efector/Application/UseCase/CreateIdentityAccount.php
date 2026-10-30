<?php

namespace common\components\Domain\Organization\Efector\Application\UseCase;

use common\components\Domain\Person\Identity\Application\Service\DiditIdentityService;
use common\models\Person\Persona;
use common\models\Platform\User;
use Firebase\JWT\JWT;
use Yii;

/**
 * Crea la cuenta de identidad (Persona + User passwordless) a partir de una
 * verificación Didit. No crea efector, plan ni pago: es el paso previo común
 * a clínica/centro, consultorio y profesionales de una organización.
 */
final class CreateIdentityAccount
{
    /**
     * @param array{verification_id: string, email?: string} $payload
     * @return array{persona: Persona, user: User, es_nueva_persona: bool, es_nuevo_usuario: bool, didit: array<string, mixed>}
     */
    public function crear(array $payload): array
    {
        $verificationId = trim((string) ($payload['verification_id'] ?? ''));
        if ($verificationId === '') {
            throw new \InvalidArgumentException('verification_id (Didit) es requerido.');
        }

        $email = trim((string) ($payload['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('E-mail inválido.');
        }

        $resolved = (new DiditIdentityService())->resolveAndUpsertPersona(
            $verificationId,
            null,
            ['email' => $email]
        );
        $persona = $resolved['persona'];

        [$user, $esNuevoUsuario] = $this->ensureUserForPersona($persona, $email);

        return [
            'persona' => $persona,
            'user' => $user,
            'es_nueva_persona' => (bool) $resolved['es_nueva'],
            'es_nuevo_usuario' => $esNuevoUsuario,
            'didit' => $resolved['didit'],
            'token' => $this->issueJwt($user, $persona),
        ];
    }

    /**
     * Emite un JWT de sesión para la cuenta de identidad (mismo patrón que RegisterPerson).
     */
    private function issueJwt(User $user, Persona $persona): ?string
    {
        $jwtSecret = Yii::$app->params['jwtSecret'] ?? null;
        if (!$jwtSecret) {
            Yii::warning('jwtSecret no configurado; no se generará token en CreateIdentityAccount.', 'billing.signup');

            return null;
        }

        try {
            return JWT::encode([
                'user_id' => (int) $user->id,
                'email' => (string) $user->email,
                'role' => 'usuario',
                'id_persona' => (int) $persona->id_persona,
                'iat' => time(),
                'exp' => time() + (24 * 60 * 60),
            ], $jwtSecret, 'HS256');
        } catch (\Throwable $e) {
            Yii::error('Error generando JWT en CreateIdentityAccount: ' . $e->getMessage(), 'billing.signup');

            return null;
        }
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
        return $persona->documento . '@example.com';
    }
}
