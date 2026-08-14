<?php

namespace common\components\Domain\Clinical\Emergency\Service;

use common\components\Platform\Core\Permission\CapabilityAccessService;
use common\components\Platform\Ui\Home\Service\HomePanelManifest;
use common\models\User;
use Yii;

/**
 * Capacidades de UI del tablero EMER: RBAC ({@see CapabilityAccessService}) + exclusiones UX del manifiesto.
 */
final class GuardiaBoardCapabilityService
{
    /** @var HomePanelManifest */
    private $manifest;

    public function __construct(?HomePanelManifest $manifest = null)
    {
        $this->manifest = $manifest ?? new HomePanelManifest();
    }

    public function canTriage(): bool
    {
        if (!$this->userCanEmergencyCapability('triage')) {
            return false;
        }

        return !$this->userHasAnyRole($this->manifest->emergencyCapabilityUiExcludeRoles('triage'), false);
    }

    public function canIngresar(): bool
    {
        return $this->userCanEmergencyCapability('ingreso');
    }

    public function canIngresarConDni(): bool
    {
        if (!$this->canIngresar()) {
            return false;
        }

        return $this->manifest->allowsEmergencyIngresoDniForCurrentClient();
    }

    public function canAtender(): bool
    {
        if ($this->userHasAnyRole($this->manifest->emergencyAtenderExcludeRoles(), false)) {
            return false;
        }

        return $this->userCanEmergencyCapability('atender');
    }

    public function canDocumentar(): bool
    {
        return $this->userCanEmergencyCapability('documentar');
    }

    /** Retiro administrativo (admisión / ventanilla). */
    public function canRetiroAdministrativo(): bool
    {
        return $this->userCanEmergencyCapability('retiro_administrativo');
    }

    /** Egreso clínico (médico). */
    public function canRetiroClinico(): bool
    {
        return $this->userCanEmergencyCapability('retiro_clinico');
    }

    /**
     * ¿Puede registrar «Paciente se retiró» en algún caso del tablero?
     */
    public function canRetiroEnTablero(): bool
    {
        return $this->canRetiroAdministrativo()
            || $this->canRetiroClinico()
            || $this->canTriage()
            || $this->canIngresar()
            || $this->canAtender();
    }

    private function userCanEmergencyCapability(string $manifestKey): bool
    {
        $capabilityId = $this->manifest->emergencyCapabilityId($manifestKey);
        if ($capabilityId === '') {
            return false;
        }

        $userId = $this->currentUserId();
        if ($userId <= 0) {
            return false;
        }

        return CapabilityAccessService::userCanExecuteCapability($userId, $capabilityId);
    }

    private function currentUserId(): int
    {
        if (!Yii::$app->has('user', true) || Yii::$app->user->isGuest) {
            return 0;
        }

        return (int) Yii::$app->user->id;
    }

    /**
     * @param list<string> $roles
     */
    private function userHasAnyRole(array $roles, bool $superAdminAllowed = true): bool
    {
        if ($roles === []) {
            return false;
        }

        return User::hasRole($roles, $superAdminAllowed);
    }
}
