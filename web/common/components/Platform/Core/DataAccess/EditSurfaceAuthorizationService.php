<?php

namespace common\components\Platform\Core\DataAccess;

use common\components\Platform\Core\Permission\BioenlaceAccessChecker;
use common\components\Platform\Core\Permission\IntentEditSurfaceIndex;

/**
 * Autorización de superficies editables vía intent enlazado.
 */
final class EditSurfaceAuthorizationService
{
    private AttributeGroupCatalog $catalog;

    public function __construct(?AttributeGroupCatalog $catalog = null)
    {
        $this->catalog = $catalog ?? new AttributeGroupCatalog();
    }

    public function userHasAnyEditableSurface(PermissionContext $ctx, array $params = []): bool
    {
        foreach ($this->catalog->listEditSurfacesForDisplay() as $surfaceId => $def) {
            if (!is_string($surfaceId) || !is_array($def)) {
                continue;
            }
            if ($this->userCanAccessEditSurface($ctx, $surfaceId, $params)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Grants intent en alguna superficie migrada (sin validar scope de sesión).
     * El scope se aplica al ejecutar /api/editar.
     */
    public function userHasAnyWriteGrantForEdit(PermissionContext $ctx): bool
    {
        foreach ($this->catalog->listEditSurfacesForDisplay() as $surfaceId => $def) {
            if (!is_string($surfaceId) || !is_array($def)) {
                continue;
            }
            if (!IntentEditSurfaceIndex::isSurfaceMigrated($surfaceId)) {
                continue;
            }
            foreach (IntentEditSurfaceIndex::intentsForSurface($surfaceId) as $intentId) {
                if (BioenlaceAccessChecker::userCanPermissionKey($ctx->userId, $intentId)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function listAspectIdsWithWriteGrant(PermissionContext $ctx, string $surfaceId): array
    {
        if ($this->resolveBoundIntent($ctx, $surfaceId) === null) {
            return [];
        }

        $surface = $this->catalog->getEditSurface($surfaceId);
        if ($surface === null) {
            return [];
        }

        $aspects = $surface['aspects'] ?? [];
        if (!is_array($aspects)) {
            return [];
        }

        $out = [];
        foreach ($aspects as $aspectId => $def) {
            if (is_string($aspectId)) {
                $out[] = $aspectId;
            }
        }

        return $out;
    }

    public function userCanAccessEditSurface(PermissionContext $ctx, string $surfaceId, array $params = []): bool
    {
        $surface = $this->catalog->getEditSurface($surfaceId);
        if ($surface === null) {
            return false;
        }

        if (!$this->assertSurfaceScope($surface, $ctx, $params)) {
            return false;
        }

        return $this->listEditableAspects($ctx, $surfaceId, $params) !== [];
    }

    /**
     * @param array<string, mixed> $params
     * @return list<array{id: string, label: string, kind: string, attribute_group: string}>
     */
    public function listEditableAspects(PermissionContext $ctx, string $surfaceId, array $params = []): array
    {
        $surface = $this->catalog->getEditSurface($surfaceId);
        if ($surface === null) {
            return [];
        }

        if (!$this->assertSurfaceScope($surface, $ctx, $params)) {
            return [];
        }

        if ($this->resolveBoundIntent($ctx, $surfaceId) === null) {
            return [];
        }

        $aspects = $surface['aspects'] ?? [];
        if (!is_array($aspects)) {
            return [];
        }

        $out = [];
        foreach ($aspects as $aspectId => $def) {
            if (!is_string($aspectId) || !is_array($def)) {
                continue;
            }
            $group = trim((string) ($def['attribute_group'] ?? ''));
            $out[] = [
                'id' => $aspectId,
                'label' => trim((string) ($def['label'] ?? $aspectId)) ?: $aspectId,
                'kind' => trim((string) ($def['kind'] ?? 'field_group')) ?: 'field_group',
                'attribute_group' => $group,
            ];
        }

        return $out;
    }

    public function userCanAccessAspect(
        PermissionContext $ctx,
        string $surfaceId,
        string $aspectId,
        array $params = []
    ): bool {
        foreach ($this->listEditableAspects($ctx, $surfaceId, $params) as $aspect) {
            if ($aspect['id'] === $aspectId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $surface
     * @param array<string, mixed> $params
     */
    private function assertSurfaceScope(array $surface, PermissionContext $ctx, array $params): bool
    {
        $checkerId = trim((string) ($surface['scope_checker'] ?? ''));
        if ($checkerId === '') {
            return false;
        }

        try {
            $resolver = $surface['subject_resolver'] ?? [];
            $metricId = is_array($resolver)
                ? trim((string) ($resolver['metric_id'] ?? 'edit_surface'))
                : 'edit_surface';
            if ($metricId === '') {
                $metricId = 'edit_surface';
            }
            $spec = QuerySpec::fromParams($metricId, $params);
            ScopeCheckerRegistry::get($checkerId)->assertAndResolve($spec, $ctx);
        } catch (\Throwable $e) {
            return false;
        }

        return true;
    }

    private function resolveBoundIntent(PermissionContext $ctx, string $surfaceId): ?string
    {
        foreach (IntentEditSurfaceIndex::intentsForSurface($surfaceId) as $intentId) {
            if (BioenlaceAccessChecker::userCanPermissionKey($ctx->userId, $intentId)) {
                return $intentId;
            }
        }

        return null;
    }
}
