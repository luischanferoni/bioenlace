<?php

namespace common\components\Core\DataAccess;

use common\components\Assistant\Catalog\YamlIntentCatalogService;
use common\components\Core\DataAccess\QueryOperation;
use common\components\Core\Permission\AttributePermissionKeyMapper;
use Yii;

/**
 * Evalúa si un rol del usuario puede usar un grupo de atributos en una operación.
 * Fuente efectiva: permisos atómicos en auth_item (Entidad.atributo.read|info|edit).
 */
final class AttributePermissionEvaluator
{
    public function can(PermissionContext $ctx, string $entityGroupKey, string $operation): bool
    {
        if (!QueryOperation::isValid($operation)) {
            return false;
        }

        if (Yii::$app->user->isSuperadmin) {
            return true;
        }

        return $this->canViaAuthItem($ctx, $entityGroupKey, $operation);
    }

    private function canViaAuthItem(PermissionContext $ctx, string $entityGroupKey, string $operation): bool
    {
        if ($ctx->userId <= 0) {
            return false;
        }

        $keys = AttributePermissionKeyMapper::permissionKeysForGroup($entityGroupKey, $operation);
        if ($keys === []) {
            return false;
        }

        foreach ($keys as $permKey) {
            if (!YamlIntentCatalogService::userIdCanPermissionKey($ctx->userId, $permKey)) {
                return false;
            }
        }

        return true;
    }

    public function scopeCheckerFor(PermissionContext $ctx, string $entityGroupKey): ?string
    {
        if (Yii::$app->user->isSuperadmin) {
            return null;
        }

        $fromYaml = (new AttributeGroupCatalog())->getEntityGroupScopeChecker($entityGroupKey);
        if ($fromYaml !== null && $fromYaml !== '') {
            return $fromYaml;
        }

        return null;
    }
}
