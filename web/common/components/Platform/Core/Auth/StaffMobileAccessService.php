<?php

namespace common\components\Platform\Core\Auth;

use common\components\Platform\Ui\Home\Service\HomePanelManifest;
use common\models\ProfesionalEfectorServicio;
use common\models\User;
use Yii;

/**
 * Acceso a API móvil del personal (X-Client: mobile / app Personal de Salud).
 */
final class StaffMobileAccessService
{
    public static function isCurrentUserAllowed(): bool
    {
        if (Yii::$app->user->isGuest) {
            return false;
        }

        if (Yii::$app->user->isSuperadmin) {
            return true;
        }

        $staffRoles = (new HomePanelManifest())->audienceStaffRoles();
        if ($staffRoles !== [] && User::hasRole($staffRoles, false)) {
            return true;
        }

        if (self::hasEfectorScopedStaffRole($staffRoles)) {
            return true;
        }

        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona > 0 && ProfesionalEfectorServicio::getEfectoresParaSesion($idPersona) !== []) {
            return true;
        }

        return false;
    }

    /**
     * Roles RBAC con sufijo por efector (p. ej. Medico_x_efector_863).
     *
     * @param list<string> $baseRoles
     */
    private static function hasEfectorScopedStaffRole(array $baseRoles): bool
    {
        if ($baseRoles === [] || !Yii::$app->has('authManager')) {
            return false;
        }

        $userId = (int) Yii::$app->user->id;
        if ($userId <= 0) {
            return false;
        }

        $assigned = Yii::$app->authManager->getRolesByUser($userId);
        foreach (array_keys($assigned) as $roleName) {
            foreach ($baseRoles as $base) {
                $base = trim($base);
                if ($base === '') {
                    continue;
                }
                if ($roleName === $base || str_starts_with((string) $roleName, $base . '_x_efector_')) {
                    return true;
                }
            }
        }

        return false;
    }
}
