<?php

namespace common\components\Core\Permission;

use common\models\rbac\AuthRole;
use Yii;
use yii\db\Query;
use yii\rbac\Item;

/**
 * Alta, edición y baja de roles RBAC (auth_item type=rol) e intents asignados al rol.
 */
final class RbacRoleAdminService
{
    /** Roles de sistema que no se pueden eliminar. */
    public const PROTECTED_ROLE_NAMES = [
        'paciente',
    ];

    /**
     * @return list<AuthRole>
     */
    public function listAll(): array
    {
        return AuthRole::find()->orderBy(['name' => SORT_ASC])->all();
    }

    public function find(string $name): ?AuthRole
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        return AuthRole::findOne(['name' => $name]);
    }

    public function create(string $name, string $description = ''): void
    {
        $name = $this->normalizeRoleName($name);
        $description = trim($description);
        if (!Yii::$app->has('authManager')) {
            throw new \InvalidArgumentException('authManager no disponible.');
        }
        if ($this->find($name) !== null) {
            throw new \InvalidArgumentException('Ya existe un rol con ese nombre.');
        }

        $role = Yii::$app->authManager->createRole($name);
        $role->description = $description !== '' ? $description : $name;
        if (!Yii::$app->authManager->add($role)) {
            throw new \RuntimeException('No se pudo crear el rol.');
        }
    }

    public function updateDescription(string $name, string $description): void
    {
        $name = trim($name);
        $role = $this->find($name);
        if ($role === null) {
            throw new \InvalidArgumentException('Rol no encontrado.');
        }

        $description = trim($description);
        Yii::$app->db->createCommand()->update('{{%auth_item}}', [
            'description' => $description !== '' ? $description : $name,
            'updated_at' => time(),
        ], ['name' => $name, 'type' => Item::TYPE_ROLE])->execute();
    }

    public function delete(string $name): void
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Rol inválido.');
        }
        if (in_array($name, self::PROTECTED_ROLE_NAMES, true)) {
            throw new \InvalidArgumentException('El rol «' . $name . '» es de sistema y no se puede eliminar.');
        }
        if (!Yii::$app->has('authManager')) {
            throw new \InvalidArgumentException('authManager no disponible.');
        }

        $assignmentTable = Yii::$app->authManager->assignmentTable;
        if ((new Query())->from($assignmentTable)->where(['item_name' => $name])->exists()) {
            throw new \InvalidArgumentException('El rol tiene usuarios asignados. Revocá las asignaciones antes de eliminarlo.');
        }

        $childTable = Yii::$app->authManager->itemChildTable;
        if ((new Query())->from($childTable)->where(['child' => $name])->exists()) {
            throw new \InvalidArgumentException('El rol es hijo de otro ítem RBAC y no se puede eliminar desde aquí.');
        }

        Yii::$app->db->createCommand()->delete($childTable, ['parent' => $name])->execute();

        $role = Yii::$app->authManager->getRole($name);
        if ($role === null) {
            throw new \InvalidArgumentException('Rol no encontrado.');
        }
        if (!Yii::$app->authManager->remove($role)) {
            throw new \RuntimeException('No se pudo eliminar el rol.');
        }
    }

    /**
     * @return list<array{key: string, description: string, assigned: bool, in_auth_item: bool}>
     */
    public function intentPermissionsForRole(string $roleName): array
    {
        $assignment = new RolePermissionAssignmentService();
        $assigned = $assignment->assignedPermissionKeysForRole($roleName);
        $rows = [];
        foreach ((new PermissionCatalogService())->listIntents() as $intent) {
            $key = trim((string) ($intent['key'] ?? ''));
            if ($key === '' || strncmp($key, '/api/', 5) === 0) {
                continue;
            }
            $rows[] = [
                'key' => $key,
                'description' => trim((string) ($intent['action_name'] ?? $intent['intent_id'] ?? $key)),
                'assigned' => isset($assigned[$key]),
                'in_auth_item' => $assignment->permissionExistsInAuthItem($key),
            ];
        }

        return $rows;
    }

    /**
     * @param list<string> $permissionKeys solo claves intent del catálogo
     */
    public function saveIntentPermissionsForRole(string $roleName, array $permissionKeys): void
    {
        $allowed = [];
        foreach ((new PermissionCatalogService())->listIntents() as $intent) {
            $key = trim((string) ($intent['key'] ?? ''));
            if ($key !== '' && strncmp($key, '/api/', 5) !== 0) {
                $allowed[$key] = true;
            }
        }

        $desired = [];
        foreach ($permissionKeys as $key) {
            $key = trim((string) $key);
            if ($key !== '' && isset($allowed[$key])) {
                $desired[] = $key;
            }
        }

        $assignment = new RolePermissionAssignmentService();
        $current = array_keys($assignment->assignedPermissionKeysForRole($roleName));
        $currentIntents = array_filter($current, static fn (string $k): bool => isset($allowed[$k]));

        foreach ($currentIntents as $key) {
            if (!in_array($key, $desired, true)) {
                $assignment->revoke($roleName, $key);
            }
        }
        foreach ($desired as $key) {
            if (!in_array($key, $currentIntents, true)) {
                $assignment->grant($roleName, $key);
            }
        }
    }

    private function normalizeRoleName(string $name): string
    {
        $name = trim($name);
        if ($name === '' || !preg_match('/^[A-Za-z0-9_.-]+$/', $name)) {
            throw new \InvalidArgumentException('Nombre de rol inválido. Use letras, números, punto, guión o guión bajo.');
        }

        return $name;
    }
}
