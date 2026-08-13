<?php

namespace common\components\Domain\Clinical\Emergency\Service;

use common\components\Platform\Ui\Home\Service\HomePanelManifest;
use common\models\User;

/**
 * Capacidades de UI del tablero EMER (roles desde home_panel_manifest.yaml).
 * No sustituye RBAC de API; define quién ve CTAs (triage, ingreso, atender, nota).
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
        return $this->hasAnyManifestRole($this->manifest->emergencyTriageRoles());
    }

    /**
     * Ingreso de paciente a guardia (roles + canal en home_panel_manifest.yaml).
     */
    public function canIngresar(): bool
    {
        if (!$this->manifest->allowsEmergencyIngresoForCurrentClient()) {
            return false;
        }

        return $this->hasAnyManifestRole($this->manifest->emergencyIngresoRoles());
    }

    /**
     * Atender / iniciar-atencion (toma el caso). Roles en home_panel_manifest.yaml.
     * `atender_exclude_roles` (admisión) gana aunque el PES herede Medico.
     */
    public function canAtender(): bool
    {
        $exclude = $this->manifest->emergencyAtenderExcludeRoles();
        if ($exclude !== [] && $this->hasAnyManifestRole($exclude, false)) {
            return false;
        }

        return $this->hasAnyManifestRole($this->manifest->emergencyAtenderRoles());
    }

    /**
     * Abrir nota de encounter sin tomar el caso (enfermería).
     */
    public function canDocumentar(): bool
    {
        return $this->hasAnyManifestRole($this->manifest->emergencyDocumentarRoles());
    }

    /**
     * @param list<string> $roles
     */
    private function hasAnyManifestRole(array $roles, bool $superAdminAllowed = true): bool
    {
        if ($roles === []) {
            return false;
        }

        return User::hasRole($roles, $superAdminAllowed);
    }
}
