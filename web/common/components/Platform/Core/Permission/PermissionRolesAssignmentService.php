<?php

namespace common\components\Platform\Core\Permission;

use Yii;
use yii\db\Query;
use yii\rbac\Item;

/**
 * Asignación permiso lógico del catálogo (intent o atributo) ↔ roles.
 */
final class PermissionRolesAssignmentService
{
    /**
     * @return list<string>
     */
    public function rolesWithPermission(string $permissionKey): array
    {
        return (new RolePermissionMatrixService())->buildMatrixRowRoles($permissionKey);
    }

    /**
     * @param list<string> $roleNames
     */
    public function saveRolesForPermission(string $permissionKey, array $roleNames): void
    {
        $permissionKey = trim($permissionKey);
        if ($permissionKey === '') {
            throw new \InvalidArgumentException('Permiso inválido.');
        }
        if (!$this->isCatalogPermission($permissionKey)) {
            throw new \InvalidArgumentException('El permiso no pertenece al catálogo declarativo.');
        }

        $assignment = new RolePermissionAssignmentService();
        if (!$assignment->permissionExistsInAuthItem($permissionKey)) {
            throw new \InvalidArgumentException('El permiso no está registrado en auth_item. Ejecutá sincronizar catálogo.');
        }

        $available = array_flip($assignment->listRoleNames());
        $desired = [];
        foreach ($roleNames as $role) {
            $role = trim((string) $role);
            if ($role !== '' && isset($available[$role])) {
                $desired[$role] = true;
            }
        }

        $current = array_flip($this->rolesWithDirectPermissionOnly($permissionKey));
        foreach (array_keys($current) as $role) {
            if (!isset($desired[$role])) {
                $assignment->revoke($role, $permissionKey);
            }
        }
        foreach (array_keys($desired) as $role) {
            if (!isset($current[$role])) {
                $assignment->grant($role, $permissionKey);
            }
        }
    }

    private function isCatalogPermission(string $permissionKey): bool
    {
        return (new PermissionCatalogService())->isIntentPermissionKey($permissionKey);
    }

    /**
     * @return list<string>
     */
    private function rolesWithDirectPermissionOnly(string $permissionKey): array
    {
        if (!Yii::$app->has('authManager')) {
            return [];
        }

        $childTable = Yii::$app->authManager->itemChildTable;
        $itemTable = Yii::$app->authManager->itemTable;
        $parents = (new Query())
            ->select(['c.parent'])
            ->from(['c' => $childTable, 'i' => $itemTable])
            ->where('c.parent = i.name AND i.type = :role AND c.child = :perm', [
                ':role' => Item::TYPE_ROLE,
                ':perm' => $permissionKey,
            ])
            ->column();

        return array_values(array_filter(array_map('strval', $parents)));
    }
}
