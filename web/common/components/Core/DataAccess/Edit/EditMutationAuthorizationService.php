<?php

namespace common\components\Core\DataAccess\Edit;

use common\components\Core\DataAccess\AttributeGroupCatalog;
use common\components\Core\DataAccess\AttributePermissionEvaluator;
use common\components\Core\DataAccess\EditSurfaceAuthorizationService;
use common\components\Core\DataAccess\PermissionContext;
use common\components\Core\DataAccess\QueryOperation;
use common\components\Core\DataAccess\QuerySpec;
use common\components\Core\DataAccess\ScopeCheckerRegistry;
use yii\web\ForbiddenHttpException;

/**
 * Revalida permisos write y allowlist de campos al aplicar mutación.
 */
final class EditMutationAuthorizationService
{
    private AttributeGroupCatalog $catalog;
    private AttributePermissionEvaluator $permissions;
    private EditSurfaceAuthorizationService $surfaceAuth;

    public function __construct(
        ?AttributeGroupCatalog $catalog = null,
        ?AttributePermissionEvaluator $permissions = null,
        ?EditSurfaceAuthorizationService $surfaceAuth = null
    ) {
        $this->catalog = $catalog ?? new AttributeGroupCatalog();
        $this->permissions = $permissions ?? new AttributePermissionEvaluator();
        $this->surfaceAuth = $surfaceAuth ?? new EditSurfaceAuthorizationService($this->catalog, $this->permissions);
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

        if (!$this->permissions->can($ctx, $group, QueryOperation::WRITE)) {
            throw new ForbiddenHttpException('No tenés permiso de escritura sobre ' . $group . '.');
        }

        $checkerId = $this->permissions->scopeCheckerFor($ctx, $group);
        if ($checkerId !== null && $checkerId !== '') {
            $spec = QuerySpec::fromParams('edit_mutation', $params);
            ScopeCheckerRegistry::get($checkerId)->assertAndResolve($spec, $ctx);
        }
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

        if (!$this->permissions->can($ctx, $group, QueryOperation::WRITE)) {
            throw new ForbiddenHttpException('No tenés permiso de escritura sobre ' . $group . '.');
        }

        $checkerId = $this->permissions->scopeCheckerFor($ctx, $group);
        if ($checkerId !== null && $checkerId !== '') {
            $spec = QuerySpec::fromParams('edit_mutation', $params);
            ScopeCheckerRegistry::get($checkerId)->assertAndResolve($spec, $ctx);
        }

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
}
