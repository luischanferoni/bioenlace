<?php

namespace common\components\Platform\Ui\Home\Service;

use common\components\Platform\Core\Product\ProductMetadataPaths;
use Symfony\Component\Yaml\Yaml;

/**
 * Resuelve layout y secciones del panel desde home-panel-manifest.yaml.
 */
final class HomePanelManifest
{
    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    /**
     * @return array{
     *   layout: string,
     *   title: string,
     *   sections: list<array{id: string, provider: string, kind: string, poll_interval_seconds?: int}>
     * }
     */
    public function resolve(string $audience, ?string $encounterClass, ?string $role = null): array
    {
        $manifest = $this->load();
        if ($audience === HomePanelAudienceResolver::PATIENT) {
            return $this->normalizePanel($manifest['panels']['patient'] ?? $manifest['panels']['fallback'] ?? []);
        }

        if ($audience === HomePanelAudienceResolver::STAFF) {
            return $this->resolveForStaff($encounterClass, $role);
        }

        return $this->normalizePanel($manifest['panels']['fallback'] ?? []);
    }

    /**
     * Panel staff = base por encounter + overlay del rol (si existe).
     *
     * @return array{
     *   layout: string,
     *   title: string,
     *   sections: list<array{id: string, provider: string, kind: string, poll_interval_seconds?: int}>
     * }
     */
    public function resolveForStaff(?string $encounterClass, ?string $role = null): array
    {
        $base = $this->normalizePanel($this->staffBasePanel($encounterClass));
        $overlay = $this->roleOverlay($role, $encounterClass);
        if ($overlay === null) {
            return $base;
        }

        return $this->applyRoleOverlay($base, $overlay);
    }

    /**
     * @return array<string, mixed>
     */
    private function staffBasePanel(?string $encounterClass): array
    {
        $manifest = $this->load();
        $operations = $manifest['panels']['staff_operations'] ?? $manifest['panels']['fallback'] ?? [];

        if ($encounterClass === null || $encounterClass === '') {
            return $operations;
        }

        $staff = $manifest['panels']['staff'] ?? [];
        if (!isset($staff[$encounterClass]) || !is_array($staff[$encounterClass])) {
            return $operations;
        }

        $panel = $staff[$encounterClass];
        $resolved = HomePanelStaffPanelSliceRegistry::resolve($encounterClass, $panel);

        return $resolved !== null ? $resolved : $panel;
    }

    /**
     * Overlay declarado en `panels.staff_by_role.<rol>.<encounter>`; si no hay para el encounter,
     * se usa la clave vacía (sesión sin encounter).
     *
     * @return array<string, mixed>|null
     */
    private function roleOverlay(?string $role, ?string $encounterClass): ?array
    {
        if ($role === null || $role === '') {
            return null;
        }

        $byRole = $this->load()['panels']['staff_by_role'] ?? [];
        if (!is_array($byRole) || !isset($byRole[$role]) || !is_array($byRole[$role])) {
            return null;
        }

        $overlays = $byRole[$role];
        $key = $encounterClass !== null && $encounterClass !== '' ? $encounterClass : '';
        if (isset($overlays[$key]) && is_array($overlays[$key])) {
            return $overlays[$key];
        }

        if ($key !== '' && isset($overlays['']) && is_array($overlays[''])) {
            return $overlays[''];
        }

        return null;
    }

    /**
     * @param array{layout: string, title: string, sections: list<array<string, mixed>>} $base
     * @param array<string, mixed> $overlay
     * @return array{layout: string, title: string, sections: list<array<string, mixed>>}
     */
    private function applyRoleOverlay(array $base, array $overlay): array
    {
        $sections = isset($overlay['sections']) && is_array($overlay['sections'])
            ? $this->normalizeSections($overlay['sections'])
            : $base['sections'];

        if (isset($overlay['sections_append']) && is_array($overlay['sections_append'])) {
            $sections = array_merge($sections, $this->normalizeSections($overlay['sections_append']));
        }

        $excluded = [];
        if (isset($overlay['exclude_sections']) && is_array($overlay['exclude_sections'])) {
            foreach ($overlay['exclude_sections'] as $id) {
                $id = trim((string) $id);
                if ($id !== '') {
                    $excluded[$id] = true;
                }
            }
        }
        if ($excluded !== []) {
            $sections = array_values(array_filter(
                $sections,
                static fn (array $section): bool => !isset($excluded[$section['id']])
            ));
        }

        return [
            'layout' => (string) ($overlay['layout'] ?? $base['layout']),
            'title' => (string) ($overlay['title'] ?? $base['title']),
            'sections' => $sections,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function load(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $path = ProductMetadataPaths::homePanelManifestFile();
        if (!is_file($path)) {
            self::$cache = [];

            return self::$cache;
        }
        self::$cache = Yaml::parseFile($path);

        return self::$cache;
    }

    /**
     * @param array<string, mixed> $panel
     * @return array{layout: string, title: string, sections: list<array{id: string, provider: string, kind: string, poll_interval_seconds?: int}>}
     */
    private function normalizePanel(array $panel): array
    {
        return [
            'layout' => (string) ($panel['layout'] ?? 'cards'),
            'title' => (string) ($panel['title'] ?? 'Inicio'),
            'sections' => $this->normalizeSections($panel['sections'] ?? []),
        ];
    }

    /**
     * @param mixed $rows
     * @return list<array{id: string, provider: string, kind: string, poll_interval_seconds?: int}>
     */
    private function normalizeSections($rows): array
    {
        if (!is_array($rows)) {
            return [];
        }

        $sections = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = isset($row['id']) ? (string) $row['id'] : '';
            $provider = isset($row['provider']) ? (string) $row['provider'] : '';
            $kind = isset($row['kind']) ? (string) $row['kind'] : $provider;
            if ($id === '' || $provider === '') {
                continue;
            }
            $section = [
                'id' => $id,
                'provider' => $provider,
                'kind' => $kind,
            ];
            if (isset($row['poll_interval_seconds'])) {
                $section['poll_interval_seconds'] = (int) $row['poll_interval_seconds'];
            }
            $sections[] = $section;
        }

        return $sections;
    }

    /**
     * @return list<string>
     */
    public function audienceStaffRoles(): array
    {
        $manifest = $this->load();
        $raw = $manifest['audience']['staff_roles'] ?? [];
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $role) {
            $role = trim((string) $role);
            if ($role !== '') {
                $out[] = $role;
            }
        }

        return $out;
    }

    public function audiencePatientRole(): string
    {
        $manifest = $this->load();
        $role = trim((string) ($manifest['audience']['patient_role'] ?? 'paciente'));

        return $role !== '' ? $role : 'paciente';
    }

    /**
     * capability_id RBAC para CTAs EMER (p. ej. triage → guardia.triage).
     */
    public function emergencyCapabilityId(string $manifestKey): string
    {
        $manifest = $this->load();
        $raw = $manifest['panels']['staff']['EMER']['capabilities'][$manifestKey] ?? null;
        if (is_string($raw)) {
            return trim($raw);
        }

        return '';
    }

    /**
     * Exclusiones UX sobre RBAC (p. ej. médico no triagea desde tablero).
     *
     * @return list<string>
     */
    public function emergencyCapabilityUiExcludeRoles(string $manifestKey): array
    {
        return $this->emergencyCapabilityRoles($manifestKey . '_ui_exclude_roles');
    }

    /**
     * @deprecated Preferir {@see emergencyCapabilityId()} + CapabilityAccessService
     * @return list<string>
     */
    public function emergencyTriageRoles(): array
    {
        return $this->legacyRolesForCapability('triage', 'triage_ui_exclude_roles');
    }

    /**
     * @deprecated Preferir {@see emergencyCapabilityId()} + CapabilityAccessService
     * @return list<string>
     */
    public function emergencyIngresoRoles(): array
    {
        return $this->legacyRolesForCapability('ingreso');
    }

    /**
     * Clientes que pueden identificar con DNI/Didit al ingresar (`web` / `mobile`).
     * Vacío = todos. Web suele quedar afuera: ahí solo conocido o NN.
     *
     * @return list<string>
     */
    public function emergencyIngresoDniClients(): array
    {
        return $this->emergencyCapabilityRoles('ingreso_dni_clients');
    }

    public function allowsEmergencyIngresoDniForCurrentClient(): bool
    {
        $allowed = $this->emergencyIngresoDniClients();
        if ($allowed === []) {
            return true;
        }
        $client = \common\components\Platform\Core\Service\ClientContextService::isWebClient()
            ? 'web'
            : 'mobile';

        return in_array($client, $allowed, true);
    }

    /**
     * @deprecated Preferir {@see emergencyCapabilityId()} + CapabilityAccessService
     * @return list<string>
     */
    public function emergencyAtenderRoles(): array
    {
        return $this->legacyRolesForCapability('atender');
    }

    /**
     * Roles que no ven Atender aunque hereden Medico vía PES (`servicios.item_name`).
     *
     * @return list<string>
     */
    public function emergencyAtenderExcludeRoles(): array
    {
        return $this->emergencyCapabilityRoles('atender_exclude_roles');
    }

    /**
     * @deprecated Preferir {@see emergencyCapabilityId()} + CapabilityAccessService
     * @return list<string>
     */
    public function emergencyDocumentarRoles(): array
    {
        return $this->legacyRolesForCapability('documentar');
    }

    /**
     * Roles efectivos = default_roles del capability YAML − ui_exclude (solo metadata).
     *
     * @return list<string>
     */
    private function legacyRolesForCapability(string $capabilityManifestKey, ?string $excludeKey = null): array
    {
        $capabilityId = $this->emergencyCapabilityId($capabilityManifestKey);
        if ($capabilityId === '') {
            return [];
        }

        $roles = \common\components\Platform\Core\Permission\CapabilityManifestIndex::defaultRolesForCapability($capabilityId);
        if ($excludeKey !== null) {
            $exclude = array_flip($this->emergencyCapabilityRoles($excludeKey));
            $roles = array_values(array_filter(
                $roles,
                static fn (string $role): bool => !isset($exclude[$role])
            ));
        }

        return $roles;
    }

    /**
     * @return list<string>
     */
    private function emergencyCapabilityRoles(string $key): array
    {
        $manifest = $this->load();
        $raw = $manifest['panels']['staff']['EMER']['capabilities'][$key] ?? [];
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $role) {
            $role = trim((string) $role);
            if ($role !== '') {
                $out[] = $role;
            }
        }

        return $out;
    }

    public static function resetCacheForTests(): void
    {
        self::$cache = null;
    }
}
