<?php

namespace common\components\Platform\Core\Auth;

use common\models\Organization\ProfesionalEfectorServicio;
use common\models\Person\Persona;
use common\models\Platform\User;
use Yii;

/**
 * Acceso a API móvil del personal (X-Client: mobile / app Personal de Salud).
 */
final class StaffMobileAccessService
{
    private const MOBILE_STAFF_ROLES = ['Medico', 'medico', 'enfermeria', 'Enfermeria'];
    private const IDENTITY_ONLY_ROLES = ['usuario', 'paciente'];
    private const PERSONAL_SALUD_APP_CLIENTS = ['personalsalud-flutter', 'bioenlace-personalsalud'];

    public static function isPersonalSaludRequest(string $client, string $appClient): bool
    {
        return strtolower(trim($client)) === 'mobile'
            && in_array(strtolower(trim($appClient)), self::PERSONAL_SALUD_APP_CLIENTS, true);
    }

    public static function isCurrentUserAllowed(): bool
    {
        if (Yii::$app->user->isSuperadmin) {
            return true;
        }

        $user = Yii::$app->user->identity;
        if (!$user instanceof User) {
            return false;
        }

        $persona = Persona::findOne(['id_user' => (int) $user->id]);
        if ($persona === null) {
            return false;
        }

        return self::isUserAllowedToLogin($user, $persona);
    }

    public static function isUserAllowedToLogin(User $user, Persona $persona): bool
    {
        if ((int) $user->status !== User::STATUS_ACTIVE
            || (int) $user->id <= 0
            || (int) $persona->id_user !== (int) $user->id) {
            return false;
        }

        if ((int) $user->superadmin === 1) {
            return true;
        }

        if (!Yii::$app->has('authManager')) {
            return false;
        }

        $assignedRoles = array_keys(Yii::$app->authManager->getRolesByUser((int) $user->id));
        $hasEfectorAssignment = ProfesionalEfectorServicio::getEfectoresParaSesion(
            (int) $persona->id_persona
        ) !== [];

        return self::isMobileAccountAllowed($assignedRoles, true, $hasEfectorAssignment);
    }

    /**
     * Roles clínicos requieren una asignación vigente; cuentas de identidad/paciente
     * sin asignaciones solo pueden consultar el wizard de sesión.
     *
     * @param list<string> $assignedRoles
     */
    public static function isMobileAccountAllowed(
        array $assignedRoles,
        bool $hasPersona,
        bool $hasEfectorAssignment
    ): bool {
        if (!$hasPersona) {
            return false;
        }

        if (self::hasMobileStaffRole($assignedRoles)) {
            return $hasEfectorAssignment;
        }

        return self::isIdentityOnlyAccountAllowed($assignedRoles, $hasEfectorAssignment);
    }

    /**
     * @param list<string> $assignedRoles
     */
    private static function isIdentityOnlyAccountAllowed(array $assignedRoles, bool $hasEfectorAssignment): bool
    {
        if ($hasEfectorAssignment) {
            return false;
        }

        foreach ($assignedRoles as $roleName) {
            if (!in_array($roleName, self::IDENTITY_ONLY_ROLES, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Roles clínicos habilitados en Personal de Salud, incluidos los nombres scoped por efector.
     *
     * @param list<string> $assignedRoles
     */
    public static function hasMobileStaffRole(array $assignedRoles): bool
    {
        foreach ($assignedRoles as $roleName) {
            foreach (self::MOBILE_STAFF_ROLES as $baseRole) {
                if ($roleName === $baseRole
                    || str_starts_with((string) $roleName, $baseRole . '_x_efector_')) {
                    return true;
                }
            }
        }

        return false;
    }
}
