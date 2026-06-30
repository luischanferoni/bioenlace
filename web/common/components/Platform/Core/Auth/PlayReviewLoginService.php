<?php

namespace common\components\Platform\Core\Auth;

use common\models\LoginForm;
use common\models\Person\Persona;
use common\models\User;
use Yii;

/**
 * Login usuario/contraseña solo para cuentas allowlisted (revisión Google Play / App Store).
 *
 * Credenciales en params-local: play_review_accounts + play_review_login_habilitado.
 *
 * @see mobile/PLAY_APP_ACCESS.md
 */
final class PlayReviewLoginService
{
    public static function isEnabled(): bool
    {
        return (bool) (Yii::$app->params['play_review_login_habilitado'] ?? false);
    }

    /**
     * @return array{user: User, persona: Persona}
     */
    public static function authenticate(string $username, string $password): array
    {
        if (!self::isEnabled()) {
            throw new \DomainException('El acceso de revisión no está habilitado en este entorno.');
        }

        $username = trim($username);
        if ($username === '' || $password === '') {
            throw new \DomainException('Usuario y contraseña son requeridos.');
        }

        if (!self::isAllowlistedUsername($username)) {
            throw new \DomainException('Credenciales inválidas.');
        }

        $model = new LoginForm();
        $model->username = $username;
        $model->password = $password;

        if (!$model->login()) {
            throw new \DomainException('Credenciales inválidas.');
        }

        $user = $model->getUser();
        if (!$user instanceof User || (int) $user->status !== User::STATUS_ACTIVE) {
            throw new \DomainException('Usuario inactivo.');
        }

        $persona = Persona::findOne(['id_user' => (int) $user->id]);
        if ($persona === null) {
            throw new \DomainException('El usuario no tiene persona asociada.');
        }

        return [
            'user' => $user,
            'persona' => $persona,
        ];
    }

    /**
     * @return list<string>
     */
    public static function allowlistedUsernames(): array
    {
        $accounts = Yii::$app->params['play_review_accounts'] ?? [];
        if (!is_array($accounts)) {
            return [];
        }

        $out = [];
        foreach ($accounts as $row) {
            if (!is_array($row)) {
                continue;
            }
            $u = trim((string) ($row['username'] ?? ''));
            if ($u !== '') {
                $out[] = $u;
            }
        }

        return array_values(array_unique($out));
    }

    private static function isAllowlistedUsername(string $username): bool
    {
        foreach (self::allowlistedUsernames() as $allowed) {
            if (strcasecmp($allowed, $username) === 0) {
                return true;
            }
        }

        return false;
    }
}
