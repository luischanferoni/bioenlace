<?php

namespace common\components\Core\Permission;

use Yii;
use yii\db\Query;
use yii\rbac\Item;

/**
 * Matriz rol ↔ permisos del catálogo declarativo vs auth_item.
 */
final class RolePermissionMatrixService
{
    /**
     * @return list<array{key: string, kind: string, source: string, in_auth_item: bool, roles: list<string>}>
     */
    public function buildMatrix(): array
    {
        $catalog = new PermissionCatalogService();
        $rows = [];

        foreach ($catalog->listIntents() as $intent) {
            $key = trim((string) ($intent['key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $rows[] = [
                'key' => $key,
                'kind' => 'intent',
                'source' => (string) ($intent['intent_id'] ?? ''),
                'in_auth_item' => $this->permissionExistsInAuthItem($key),
                'roles' => $this->rolesWithPermissionKey($key),
            ];
        }

        $seen = [];
        foreach ($catalog->listAttributes() as $attr) {
            $key = trim((string) ($attr['key'] ?? ''));
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $rows[] = [
                'key' => $key,
                'kind' => (string) ($attr['kind'] ?? 'attribute'),
                'source' => (string) ($attr['source'] ?? ''),
                'in_auth_item' => $this->permissionExistsInAuthItem($key),
                'roles' => $this->rolesWithPermissionKey($key),
            ];
        }

        usort($rows, static fn (array $a, array $b): int => strcmp($a['key'], $b['key']));

        return $rows;
    }

    /**
     * @return list<string>
     */
    public function listRoleNames(): array
    {
        if (!Yii::$app->has('authManager')) {
            return [];
        }
        $roles = Yii::$app->authManager->getRoles();

        return array_keys($roles);
    }

    private function permissionExistsInAuthItem(string $key): bool
    {
        if (strncmp($key, '/api/', 5) === 0) {
            return (new Query())
                ->from('{{%auth_item}}')
                ->where(['name' => $key, 'type' => Item::TYPE_PERMISSION])
                ->exists();
        }

        return (new Query())
            ->from('{{%auth_item}}')
            ->where(['name' => $key])
            ->exists();
    }

    /**
     * Roles con permiso lógico directo o vía ruta legacy enlazada.
     *
     * @return list<string>
     */
    private function rolesWithPermissionKey(string $permissionKey): array
    {
        $roles = $this->rolesWithDirectPermission($permissionKey);
        if (strncmp($permissionKey, '/api/', 5) === 0) {
            $roles = array_merge($roles, $this->rolesWithRoutePermission($permissionKey));
        } else {
            $routes = $this->legacyRoutesForLogicalPermission($permissionKey);
            foreach ($routes as $route) {
                $roles = array_merge($roles, $this->rolesWithRoutePermission($route));
            }
        }
        $roles = array_values(array_unique(array_filter($roles)));
        sort($roles);

        return $roles;
    }

    /**
     * @return list<string>
     */
    private function rolesWithDirectPermission(string $permissionKey): array
    {
        if (!Yii::$app->has('authManager')) {
            return [];
        }

        $auth = Yii::$app->authManager;
        $roles = [];
        foreach ($auth->getRoles() as $roleName => $_role) {
            $permissions = $auth->getPermissionsByRole((string) $roleName);
            if (isset($permissions[$permissionKey])) {
                $roles[] = (string) $roleName;
            }
        }

        return $roles;
    }

    /**
     * @return list<string>
     */
    private function legacyRoutesForLogicalPermission(string $permissionKey): array
    {
        if (!Yii::$app->has('authManager')) {
            return [];
        }

        $children = Yii::$app->authManager->getChildren($permissionKey);
        $routes = [];
        foreach ($children as $item) {
            if ((int) $item->type === 3) {
                $routes[] = $item->name;
            }
        }

        return $routes;
    }

    /**
     * @return list<string>
     */
    private function rolesWithRoutePermission(string $route): array
    {
        $childTable = Yii::$app->authManager->itemChildTable;
        $itemTable = Yii::$app->authManager->itemTable;
        $rows = (new Query())
            ->select(['parent'])
            ->from($childTable)
            ->where(['child' => $route])
            ->column();

        if ($rows === []) {
            $alt = preg_replace('#^/api/v\d+/#', '/api/', $route, 1);
            if (is_string($alt) && $alt !== $route) {
                $rows = (new Query())
                    ->select(['parent'])
                    ->from($childTable)
                    ->where(['child' => $alt])
                    ->column();
            }
        }

        $roles = [];
        foreach ($rows as $parent) {
            $type = (new Query())
                ->select(['type'])
                ->from($itemTable)
                ->where(['name' => $parent])
                ->scalar();
            if ((int) $type === Item::TYPE_ROLE) {
                $roles[] = (string) $parent;
            }
        }

        sort($roles);

        return array_values(array_unique($roles));
    }
}
