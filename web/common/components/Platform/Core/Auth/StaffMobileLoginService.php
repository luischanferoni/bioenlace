<?php

namespace common\components\Platform\Core\Auth;

use common\models\LoginForm;
use common\models\Person\Persona;
use common\models\ProfesionalEfectorServicio;
use common\models\User;

/**
 * Login usuario/contraseña para app móvil Personal de Salud (primera vez en el dispositivo).
 */
final class StaffMobileLoginService
{
    /**
     * @return array{user: User, persona: Persona}
     */
    public static function authenticate(string $username, string $password): array
    {
        $username = trim($username);
        if ($username === '' || $password === '') {
            throw new \DomainException('Usuario y contraseña son requeridos.');
        }

        $model = new LoginForm();
        $model->username = $username;
        $model->password = $password;

        $user = $model->getUser();
        if ($user instanceof User && StaffAccountInvitationService::isPendingActivation($user)) {
            throw new \DomainException(
                'Tu cuenta aún no está activada. Pedí a administración el código o el e-mail de activación.'
            );
        }

        if (!$model->validate()) {
            throw new \DomainException('Usuario y/o contraseña incorrectos.');
        }

        $user = $model->getUser();
        if (!$user instanceof User || (int) $user->status !== User::STATUS_ACTIVE) {
            throw new \DomainException('Usuario inactivo.');
        }

        $persona = Persona::findOne(['id_user' => (int) $user->id]);
        if ($persona === null) {
            throw new \DomainException('El usuario no tiene persona asociada.');
        }

        $efectores = ProfesionalEfectorServicio::getEfectoresParaSesion((int) $persona->id_persona);
        if ($efectores === []) {
            throw new \DomainException(
                'No tenés asignación en ningún efector. Pedí acceso al administrador de tu centro.'
            );
        }

        return [
            'user' => $user,
            'persona' => $persona,
        ];
    }
}
