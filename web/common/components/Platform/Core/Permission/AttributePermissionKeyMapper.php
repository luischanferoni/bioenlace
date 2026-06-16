<?php

namespace common\components\Platform\Core\Permission;

use common\components\Platform\Core\DataAccess\AttributeGroupCatalog;
use common\components\Platform\Core\DataAccess\QueryOperation;

/**
 * Mapeo operaciones DataAccess ↔ claves de permiso del catálogo (Entidad.atributo.read|info|edit).
 *
 * @deprecated Convivencia integridad; no usar en autorización runtime de dominios migrados.
 */
final class AttributePermissionKeyMapper
{
    public static function queryOperationToCatalogOp(string $queryOperation): string
    {
        $queryOperation = mb_strtolower(trim($queryOperation), 'UTF-8');
        if ($queryOperation === QueryOperation::WRITE) {
            return 'edit';
        }
        if ($queryOperation === QueryOperation::AGGREGATE) {
            return 'info';
        }

        return 'read';
    }

    public static function permissionKey(string $entity, string $attribute, string $catalogOp): string
    {
        return trim($entity) . '.' . trim($attribute) . '.' . trim($catalogOp);
    }

    /**
     * Claves de permiso atómico para un grupo legacy (todos los atributos del grupo).
     *
     * @return list<string>
     */
    public static function permissionKeysForGroup(string $entityGroupKey, string $queryOperation): array
    {
        $entityGroupKey = trim($entityGroupKey);
        if ($entityGroupKey === '' || strpos($entityGroupKey, '.') === false) {
            return [];
        }

        $catalog = new AttributeGroupCatalog();
        $attributes = $catalog->getEntityGroupAttributes($entityGroupKey);
        if ($attributes === []) {
            return [];
        }

        [$entity] = explode('.', $entityGroupKey, 2);
        $catalogOp = self::queryOperationToCatalogOp($queryOperation);
        $keys = [];
        foreach ($attributes as $attribute) {
            $attribute = trim((string) $attribute);
            if ($attribute !== '') {
                $keys[] = self::permissionKey($entity, $attribute, $catalogOp);
            }
        }

        return $keys;
    }
}
