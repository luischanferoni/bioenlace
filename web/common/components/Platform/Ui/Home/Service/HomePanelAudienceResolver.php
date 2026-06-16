<?php

namespace common\components\Platform\Ui\Home\Service;

use common\components\Platform\Core\Permission\BioenlaceAccessChecker;
use common\models\User;
use Yii;

/**
 * Resuelve audiencia del panel: staff (sesión operativa), patient o fallback.
 */
final class HomePanelAudienceResolver
{
    public const STAFF = 'staff';
    public const PATIENT = 'patient';
    public const FALLBACK = 'fallback';

    private HomePanelManifest $manifest;

    public function __construct(?HomePanelManifest $manifest = null)
    {
        $this->manifest = $manifest ?? new HomePanelManifest();
    }

    public function resolve(): string
    {
        if (Yii::$app->user->isGuest) {
            return self::FALLBACK;
        }

        $userId = (int) Yii::$app->user->id;
        if (BioenlaceAccessChecker::isSuperadminUserId($userId)) {
            return self::STAFF;
        }

        $encounterClass = Yii::$app->user->getEncounterClass();
        if ($encounterClass !== null && $encounterClass !== '') {
            return self::STAFF;
        }

        $idEfector = (int) Yii::$app->user->getIdEfector();
        $idServicio = (int) Yii::$app->user->getServicioActual();
        if ($idEfector > 0 && $idServicio > 0) {
            return self::STAFF;
        }

        $hasStaffRole = $this->hasStaffAudienceRole();
        $hasPatientRole = $this->hasPatientAudienceRole();
        $idPersona = (int) Yii::$app->user->getIdPersona();

        if ($hasPatientRole && !$hasStaffRole && $idPersona > 0) {
            return self::PATIENT;
        }

        if ($hasStaffRole) {
            return self::STAFF;
        }

        if ($idPersona > 0 && $hasPatientRole) {
            return self::PATIENT;
        }

        return self::FALLBACK;
    }

    private function hasStaffAudienceRole(): bool
    {
        $roles = $this->manifest->audienceStaffRoles();

        return $roles !== [] && User::hasRole($roles, false);
    }

    private function hasPatientAudienceRole(): bool
    {
        $role = $this->manifest->audiencePatientRole();

        return User::hasRole($role, false);
    }
}
