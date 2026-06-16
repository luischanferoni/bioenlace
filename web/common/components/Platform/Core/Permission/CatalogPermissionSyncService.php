<?php

namespace common\components\Platform\Core\Permission;

use yii\db\Query;
use yii\rbac\Item;

/**
 * Sincroniza permisos lógicos del catálogo declarativo con auth_item / auth_item_child.
 */
final class CatalogPermissionSyncService
{
    /**
     * @return list<array{key: string, kind: string, description: string, legacy_route: string, intent_id: string}>
     */
    public function collectDefinitions(): array
    {
        $catalog = new PermissionCatalogService();
        $out = [];
        $seen = [];

        foreach ($catalog->listIntents() as $row) {
            $key = trim((string) ($row['key'] ?? ''));
            if ($key === '' || strncmp($key, '/api/', 5) === 0 || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = [
                'key' => $key,
                'kind' => 'intent',
                'description' => trim((string) ($row['action_name'] ?? $row['intent_id'] ?? $key)),
                'legacy_route' => trim((string) ($row['rbac_route'] ?? '')),
                'intent_id' => (string) ($row['intent_id'] ?? ''),
            ];
        }

        usort($out, static fn (array $a, array $b): int => strcmp($a['key'], $b['key']));

        return $out;
    }

    /**
     * @return array{created: int, linked: int, role_grants: int, skipped: int, errors: list<string>}
     */
    public function sync(bool $inheritRoleGrantsFromRoutes = true): array
    {
        $db = \Yii::$app->db;
        $authItem = $db->schema->getRawTableName('{{%auth_item}}');
        $childTable = $db->schema->getRawTableName('{{%auth_item_child}}');
        if ($db->schema->getTableSchema($authItem, true) === null) {
            return [
                'created' => 0,
                'linked' => 0,
                'role_grants' => 0,
                'skipped' => 0,
                'errors' => ['Tabla auth_item no disponible'],
            ];
        }

        $hasChild = $db->schema->getTableSchema($childTable, true) !== null;
        $now = time();
        $created = 0;
        $linked = 0;
        $roleGrants = 0;
        $skipped = 0;
        $errors = [];

        foreach ($this->collectDefinitions() as $def) {
            $key = $def['key'];
            if ($this->authItemExists($authItem, $key)) {
                $skipped++;
            } else {
                try {
                    $db->createCommand()->insert($authItem, [
                        'name' => $key,
                        'type' => 2,
                        'description' => $def['description'] !== '' ? $def['description'] : $key,
                        'rule_name' => null,
                        'data' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->execute();
                    $created++;
                } catch (\Throwable $e) {
                    $errors[] = $key . ': ' . $e->getMessage();
                }
            }

            $route = trim((string) ($def['legacy_route'] ?? ''));
            if ($route === '' || !$hasChild) {
                continue;
            }
            $route = '/' . ltrim($route, '/');
            $this->ensureRouteAuthItem($authItem, $route, $now);
            if ($this->ensureChildLink($childTable, $key, $route)) {
                $linked++;
            }
            if ($inheritRoleGrantsFromRoutes) {
                $roleGrants += $this->inheritRoleGrantsFromRoute($childTable, $route, $key);
            }
        }

        return [
            'created' => $created,
            'linked' => $linked,
            'role_grants' => $roleGrants,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Asigna permisos lógicos a roles que alcanzan un ítem RBAC (permiso o ruta).
     *
     * @param list<string> $permissionKeys
     */
    public function grantPermissionsToRolesWithAccessToItem(string $sourceItemName, array $permissionKeys): int
    {
        $childTable = \Yii::$app->db->schema->getRawTableName('{{%auth_item_child}}');
        if (\Yii::$app->db->schema->getTableSchema($childTable, true) === null) {
            return 0;
        }

        $added = 0;
        foreach ($this->resolveRoleNamesWithAccessToItem($sourceItemName) as $role) {
            foreach ($permissionKeys as $permissionKey) {
                $permissionKey = trim($permissionKey);
                if ($permissionKey === '') {
                    continue;
                }
                if ($this->ensureChildLink($childTable, $role, $permissionKey)) {
                    $added++;
                }
            }
        }

        return $added;
    }

    private function authItemExists(string $authItem, string $name): bool
    {
        return (new \yii\db\Query())
            ->from($authItem)
            ->where(['name' => $name])
            ->exists(\Yii::$app->db);
    }

    private function ensureRouteAuthItem(string $authItem, string $route, int $now): void
    {
        if ($this->authItemExists($authItem, $route)) {
            return;
        }
        \Yii::$app->db->createCommand()->insert($authItem, [
            'name' => $route,
            'type' => 3,
            'description' => 'Ruta API (legacy)',
            'rule_name' => null,
            'data' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->execute();
    }

    private function ensureChildLink(string $childTable, string $parent, string $child): bool
    {
        if ((new \yii\db\Query())->from($childTable)->where(['parent' => $parent, 'child' => $child])->exists(\Yii::$app->db)) {
            return false;
        }
        \Yii::$app->db->createCommand()->insert($childTable, [
            'parent' => $parent,
            'child' => $child,
        ])->execute();

        return true;
    }

    /**
     * Roles (type=1) que alcanzan un ítem RBAC subiendo la jerarquía auth_item_child.
     *
     * @return list<string>
     */
    public function resolveRoleNamesWithAccessToItem(string $itemName): array
    {
        $itemName = trim($itemName);
        if ($itemName === '') {
            return [];
        }

        $db = \Yii::$app->db;
        $authItem = $db->schema->getRawTableName('{{%auth_item}}');
        $childTable = $db->schema->getRawTableName('{{%auth_item_child}}');
        if ($db->schema->getTableSchema($authItem, true) === null
            || $db->schema->getTableSchema($childTable, true) === null) {
            return [];
        }

        $roles = [];
        $queue = [$itemName];
        $seen = [];

        while ($queue !== []) {
            $current = array_shift($queue);
            if (isset($seen[$current])) {
                continue;
            }
            $seen[$current] = true;

            $parents = (new Query())
                ->select('parent')
                ->from($childTable)
                ->where(['child' => $current])
                ->column($db);

            foreach ($parents as $parent) {
                if (!is_string($parent) || $parent === '') {
                    continue;
                }
                $type = (new Query())
                    ->select('type')
                    ->from($authItem)
                    ->where(['name' => $parent])
                    ->scalar($db);
                if ((int) $type === Item::TYPE_ROLE) {
                    $roles[$parent] = true;
                } else {
                    $queue[] = $parent;
                }
            }
        }

        ksort($roles);

        return array_keys($roles);
    }

    /**
     * Roles que tenían la ruta legacy (directa o vía permiso intermedio) reciben el permiso lógico.
     */
    private function inheritRoleGrantsFromRoute(string $childTable, string $route, string $permissionKey): int
    {
        $added = 0;
        foreach ($this->resolveRoleNamesWithAccessToItem($route) as $role) {
            if ($this->ensureChildLink($childTable, $role, $permissionKey)) {
                $added++;
            }
        }

        return $added;
    }

    /**
     * Elimina permisos atómicos Entidad.atributo.{read|info|edit} de auth_item (fase 6).
     * Ejecutar tras catalog-permission/migrate-grants y validar integridad.
     *
     * @return array{dry_run: bool, candidates: list<string>, removed: int, errors: list<string>}
     */
    public function pruneLegacyAttributeAuthItems(bool $dryRun = true): array
    {
        $db = \Yii::$app->db;
        $authItem = $db->schema->getRawTableName('{{%auth_item}}');
        $childTable = $db->schema->getRawTableName('{{%auth_item_child}}');
        if ($db->schema->getTableSchema($authItem, true) === null) {
            return [
                'dry_run' => $dryRun,
                'candidates' => [],
                'removed' => 0,
                'errors' => ['Tabla auth_item no disponible'],
            ];
        }

        $hasChild = $db->schema->getTableSchema($childTable, true) !== null;
        $candidates = [];
        foreach ((new PermissionCatalogService())->listAttributes() as $row) {
            $key = trim((string) ($row['key'] ?? ''));
            if ($key !== '' && $this->authItemExists($authItem, $key)) {
                $candidates[] = $key;
            }
        }
        sort($candidates);

        if ($dryRun) {
            return [
                'dry_run' => true,
                'candidates' => $candidates,
                'removed' => 0,
                'errors' => [],
            ];
        }

        $removed = 0;
        $errors = [];
        foreach ($candidates as $key) {
            try {
                if ($hasChild) {
                    $db->createCommand()->delete($childTable, ['child' => $key])->execute();
                    $db->createCommand()->delete($childTable, ['parent' => $key])->execute();
                }
                $deleted = $db->createCommand()->delete($authItem, ['name' => $key, 'type' => 2])->execute();
                if ($deleted > 0) {
                    $removed += $deleted;
                }
            } catch (\Throwable $e) {
                $errors[] = $key . ': ' . $e->getMessage();
            }
        }

        return [
            'dry_run' => false,
            'candidates' => $candidates,
            'removed' => $removed,
            'errors' => $errors,
        ];
    }
}
