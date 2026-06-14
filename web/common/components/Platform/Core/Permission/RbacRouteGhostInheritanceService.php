<?php

namespace common\components\Platform\Core\Permission;

use yii\db\Query;

/**
 * Propaga rutas ghost: roles con acceso a ruta padre reciben enlace directo a ruta hija.
 *
 * Evita copiar permisos lógicos del catálogo como padres de rutas (contaminación RBAC).
 */
final class RbacRouteGhostInheritanceService
{
    public function __construct(
        private ?CatalogPermissionSyncService $sync = null
    ) {
        $this->sync = $sync ?? new CatalogPermissionSyncService();
    }

    /**
     * @param array<string, string> $childRouteToParentRoute
     */
    public function propagateChain(array $childRouteToParentRoute): int
    {
        $added = 0;
        foreach ($childRouteToParentRoute as $childRoute => $parentRoute) {
            $added += $this->propagateRolesFromRoute($parentRoute, $childRoute);
        }

        return $added;
    }

    public function propagateRolesFromRoute(string $parentRoute, string $childRoute): int
    {
        $parentRoute = '/' . ltrim(trim($parentRoute), '/');
        $childRoute = '/' . ltrim(trim($childRoute), '/');
        if ($parentRoute === '/' || $childRoute === '/') {
            return 0;
        }

        $db = \Yii::$app->db;
        $childTable = $db->schema->getRawTableName('{{%auth_item_child}}');
        if ($db->schema->getTableSchema($childTable, true) === null) {
            return 0;
        }

        $added = 0;
        foreach ($this->sync->resolveRoleNamesWithAccessToItem($parentRoute) as $role) {
            if ($this->ensureChildLink($childTable, $role, $childRoute)) {
                $added++;
            }
        }

        return $added;
    }

    private function ensureChildLink(string $childTable, string $parent, string $child): bool
    {
        if ((new Query())->from($childTable)->where(['parent' => $parent, 'child' => $child])->exists(\Yii::$app->db)) {
            return false;
        }
        \Yii::$app->db->createCommand()->insert($childTable, [
            'parent' => $parent,
            'child' => $child,
        ])->execute();

        return true;
    }
}
