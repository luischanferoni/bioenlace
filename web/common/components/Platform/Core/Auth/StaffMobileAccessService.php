<?php

namespace common\components\Platform\Core\Auth;

use common\models\Organization\ProfesionalEfectorServicio;
use common\models\Organization\Servicio;
use common\models\Person\Persona;
use common\models\Platform\User;
use Yii;

/**
 * Acceso a API móvil del personal (X-Client: mobile / app Personal de Salud).
 */
final class StaffMobileAccessService
{
    private const MOBILE_STAFF_ROLES = ['Medico', 'medico', 'enfermeria', 'Enfermeria'];
    private const MOBILE_ADMIN_ROLES = ['AdminEfector'];
    private const PERSONAL_SALUD_APP_CLIENTS = ['personalsalud-flutter', 'bioenlace-personalsalud'];

    public static function isPersonalSaludRequest(string $client, string $appClient): bool
    {
        return strtolower(trim($client)) === 'mobile'
            && in_array(strtolower(trim($appClient)), self::PERSONAL_SALUD_APP_CLIENTS, true);
    }

    public static function isPersonalSaludOnboardingRequest(string $method, string $path): bool
    {
        $method = strtoupper(trim($method));
        $path = strtolower(trim($path, '/'));

        return match ($method) {
            'POST' => preg_match(
                '~(?:^|/)licencia/(?:registrar-efector|cancelar-suscripcion)$~',
                $path
            ) === 1,
            'GET' => preg_match('~(?:^|/)licencia/estado-checkout$~', $path) === 1,
            default => false,
        };
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

        $assignedRoles = array_merge(
            array_keys(Yii::$app->authManager->getRolesByUser((int) $user->id)),
            self::getRolesFromActivePes((int) $persona->id_persona)
        );
        $hasEfectorAssignment = ProfesionalEfectorServicio::getEfectoresParaSesion(
            (int) $persona->id_persona
        ) !== [];

        return self::isMobileAccountAllowed($assignedRoles, true, $hasEfectorAssignment);
    }

    /**
     * Roles inherited from PES must be resolved from this persona directly: the
     * auth manager's request-context roles may not be initialized during JWT auth.
     *
     * @return list<string>
     */
    private static function getRolesFromActivePes(int $idPersona): array
    {
        $roles = ProfesionalEfectorServicio::find()
            ->alias('pes')
            ->innerJoin(['servicio' => Servicio::tableName()], 'servicio.id_servicio = pes.id_servicio')
            ->select('servicio.item_name')
            ->where([
                'pes.id_persona' => $idPersona,
                'pes.deleted_at' => null,
            ])
            ->distinct()
            ->column();

        return array_values(array_filter(
            array_map(static fn($role): string => trim((string) $role), $roles),
            static fn(string $role): bool => $role !== ''
        ));
    }

    /** @param list<string> $assignedRoles */
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

        if (self::hasMobileAdminRole($assignedRoles)) {
            return true;
        }

        return false;
    }

    /**
     * AdminEfector may authenticate in the app; identity/patient-only accounts
     * must use the patient app or obtain a staff role from an efector.
     *
     * @param list<string> $assignedRoles
     */
    private static function hasMobileAdminRole(array $assignedRoles): bool
    {
        foreach ($assignedRoles as $roleName) {
            foreach (self::MOBILE_ADMIN_ROLES as $baseRole) {
                if ($roleName === $baseRole
                    || str_starts_with((string) $roleName, $baseRole . '_x_efector_')) {
                    return true;
                }
            }
        }

        return false;
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
