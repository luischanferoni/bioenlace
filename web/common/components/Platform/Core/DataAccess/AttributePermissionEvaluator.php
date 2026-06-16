<?php

namespace common\components\Platform\Core\DataAccess;

use common\components\Platform\Assistant\Catalog\YamlIntentCatalogService;
use common\components\Platform\Core\DataAccess\QueryOperation;
use common\components\Platform\Core\Permission\AttributePermissionKeyMapper;
use Yii;

/**
 * Evalúa si un rol del usuario puede usar un grupo de atributos en una operación.
 *
 * @deprecated Modelo Entidad.atributo.read|info|edit; autorizar por intent_id en dominios migrados.
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
