<?php

namespace common\components\Core\Permission;

use Yii;
use yii\db\Query;
use yii\rbac\Item;

/**
 * Asignación rol ↔ permiso lógico (auth_item_child, parent=rol, child=permiso).
 */
final class RolePermissionAssignmentService
{
    /**
     * @return list<string>
     */
    public function listRoleNames(): array
    {
        if (!Yii::$app->has('authManager')) {
            return [];
        }

        return array_keys(Yii::$app->authManager->getRoles());
    }

    /**
     * Permisos lógicos (type=2) asignados directamente al rol.
     *
     * @return array<string, true>
     */
    public function assignedPermissionKeysForRole(string $roleName): array
    {
        $roleName = trim($roleName);
        if ($roleName === '') {
            return [];
        }

        $rows = (new Query())
            ->select(['c.child'])
            ->from(['c' => '{{%auth_item_child}}', 'i' => '{{%auth_item}}'])
            ->where('c.parent = :role AND c.child = i.name AND i.type = :type', [
                ':role' => $roleName,
                ':type' => Item::TYPE_PERMISSION,
            ])
            ->column();

        $out = [];
        foreach ($rows as $child) {
            if (is_string($child) && $child !== '') {
                $out[$child] = true;
            }
        }

        return $out;
    }

    /**
     * @return list<array{key: string, kind: string, description: string, assigned: bool, in_auth_item: bool}>
     */
    public function catalogPermissionsForRole(string $roleName): array
    {
        $assigned = $this->assignedPermissionKeysForRole($roleName);
        $sync = new CatalogPermissionSyncService();
        $rows = [];
        foreach ($sync->collectDefinitions() as $def) {
            $key = $def['key'];
            $rows[] = [
                'key' => $key,
                'kind' => $def['kind'],
                'description' => $def['description'],
                'assigned' => isset($assigned[$key]),
                'in_auth_item' => $this->permissionExists($key),
            ];
        }

        return $rows;
    }

    /**
     * @param list<string> $permissionKeys
     */
    public function saveRolePermissions(string $roleName, array $permissionKeys): void
    {
        $roleName = trim($roleName);
        if ($roleName === '' || !Yii::$app->has('authManager')) {
            throw new \InvalidArgumentException('Rol inválido.');
        }

        $role = Yii::$app->authManager->getRole($roleName);
        if ($role === null) {
            throw new \InvalidArgumentException('Rol no encontrado: ' . $roleName);
        }

        $desired = [];
        foreach ($permissionKeys as $key) {
            $key = trim((string) $key);
            if ($key !== '') {
                $desired[$key] = true;
            }
        }

        $current = $this->assignedPermissionKeysForRole($roleName);

        foreach (array_keys($current) as $key) {
            if (!isset($desired[$key])) {
                $this->revoke($roleName, $key);
            }
        }

        foreach (array_keys($desired) as $key) {
            if (!isset($current[$key])) {
                $this->grant($roleName, $key);
            }
        }
    }

    public function grant(string $roleName, string $permissionKey): void
    {
        if (!$this->permissionExists($permissionKey)) {
            throw new \InvalidArgumentException('Permiso no registrado en auth_item: ' . $permissionKey);
        }

        $childTable = Yii::$app->authManager->itemChildTable;
        if ((new Query())->from($childTable)->where(['parent' => $roleName, 'child' => $permissionKey])->exists()) {
            return;
        }

        Yii::$app->db->createCommand()->insert($childTable, [
            'parent' => $roleName,
            'child' => $permissionKey,
        ])->execute();
    }

    public function revoke(string $roleName, string $permissionKey): void
    {
        Yii::$app->db->createCommand()->delete(Yii::$app->authManager->itemChildTable, [
            'parent' => $roleName,
            'child' => $permissionKey,
        ])->execute();
    }

    private function permissionExists(string $key): bool
    {
        return (new Query())
            ->from('{{%auth_item}}')
            ->where(['name' => $key, 'type' => Item::TYPE_PERMISSION])
            ->exists();
    }
}
