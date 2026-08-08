<?php

namespace common\components\Domain\Clinical\Emergency\Service;

use common\components\Platform\Ui\Home\Service\HomePanelManifest;
use common\models\User;

/**
 * Capacidades de UI del tablero EMER (roles desde home_panel_manifest.yaml).
 * No sustituye RBAC de API; define quién ve CTAs de triage en clientes.
 */
final class GuardiaBoardCapabilityService
{
    /** @var HomePanelManifest */
    private $manifest;

    public function __construct(?HomePanelManifest $manifest = null)
    {
        $this->manifest = $manifest ?? new HomePanelManifest();
    }

    /**
     * Triage / re-triage en tablero (roles en home_panel_manifest.yaml).
     */
    public function canTriage(): bool
    {
        $roles = $this->manifest->emergencyTriageRoles();
        if ($roles === []) {
            return false;
        }

        return User::hasRole($roles, true);
    }
}
