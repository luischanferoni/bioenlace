<?php

namespace common\components\Platform\Core\DataAccess\Edit;

use common\components\Platform\Core\DataAccess\AttributeGroupCatalog;
use common\components\Platform\Core\DataAccess\EditSurfaceAuthorizationService;
use common\components\Platform\Core\DataAccess\PermissionContext;
use common\components\Platform\Core\DataAccess\QuerySpec;
use common\components\Platform\Core\DataAccess\ScopeCheckerRegistry;
use yii\web\ForbiddenHttpException;

/**
 * Revalida permiso intent y allowlist de campos al aplicar mutación.
 */
final class EditMutationAuthorizationService
{
    private AttributeGroupCatalog $catalog;
    private EditSurfaceAuthorizationService $surfaceAuth;

    public function __construct(
        ?AttributeGroupCatalog $catalog = null,
        ?EditSurfaceAuthorizationService $surfaceAuth = null
    ) {
        $this->catalog = $catalog ?? new AttributeGroupCatalog();
        $this->surfaceAuth = $surfaceAuth ?? new EditSurfaceAuthorizationService($this->catalog);
    }

    /**
     * @param array<string, mixed> $aspectDef
     * @param array<string, mixed> $params
     */
    public function assertCanApplyOpenUiAspect(
        PermissionContext $ctx,
        string $surfaceId,
        string $aspectId,
        array $aspectDef,
        array $params
    ): void {
        if (!$this->surfaceAuth->userCanAccessAspect($ctx, $surfaceId, $aspectId, $params)) {
            throw new ForbiddenHttpException('No tenés permiso para modificar ese aspecto.');
        }

        $group = trim((string) ($aspectDef['attribute_group'] ?? ''));
        if ($group === '') {
            throw new ForbiddenHttpException('Aspecto sin grupo de atributos.');
        }

        $this->assertGroupScope($ctx, $group, $params);
    }

    /**
     * @param array<string, mixed> $aspectDef
     * @param array<string, mixed> $params
     * @param array<string, string> $fieldChanges
     */
    public function assertCanApplyScalarChanges(
        PermissionContext $ctx,
        string $surfaceId,
        string $aspectId,
        array $aspectDef,
        array $params,
        array $fieldChanges
    ): void {
        if ($fieldChanges === []) {
            return;
        }

        if (!$this->surfaceAuth->userCanAccessAspect($ctx, $surfaceId, $aspectId, $params)) {
            throw new ForbiddenHttpException('No tenés permiso para modificar ese aspecto.');
        }

        $group = trim((string) ($aspectDef['attribute_group'] ?? ''));
        if ($group === '') {
            throw new ForbiddenHttpException('Aspecto sin grupo de atributos.');
        }

        $this->assertGroupScope($ctx, $group, $params);

        $allowed = $this->allowedFieldsForAspect($aspectDef, $group);
        foreach (array_keys($fieldChanges) as $field) {
            if (!in_array($field, $allowed, true)) {
                throw new ForbiddenHttpException('Campo no editable: ' . $field);
            }
        }
    }

    /**
     * @param array<string, mixed> $aspectDef
     * @return list<string>
     */
    public function allowedFieldsForAspect(array $aspectDef, string $attributeGroup): array
    {
        $aspectFields = $aspectDef['fields'] ?? null;
        if (is_array($aspectFields) && $aspectFields !== []) {
            return array_values(array_filter(array_map(
                static fn ($f): string => trim((string) $f),
                $aspectFields
            ), static fn (string $f): bool => $f !== ''));
        }

        return $this->catalog->getEntityGroupAttributes($attributeGroup);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function assertGroupScope(PermissionContext $ctx, string $group, array $params): void
    {
        $checkerId = trim((string) ($this->catalog->getEntityGroupScopeChecker($group) ?? ''));
        if ($checkerId === '') {
            return;
        }

        $spec = QuerySpec::fromParams('edit_mutation', $params);
        ScopeCheckerRegistry::get($checkerId)->assertAndResolve($spec, $ctx);
    }
}
