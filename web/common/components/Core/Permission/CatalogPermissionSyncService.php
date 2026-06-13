<?php

namespace common\components\Core\Permission;

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

        foreach ($catalog->listAttributes() as $row) {
            $key = trim((string) ($row['key'] ?? ''));
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = [
                'key' => $key,
                'kind' => (string) ($row['kind'] ?? 'attribute'),
                'description' => $key,
                'legacy_route' => '',
                'intent_id' => '',
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
     * Sync catálogo + migración de data_access_role_grant → auth_item.
     *
     * @return array<string, mixed>
     */
    public function syncAll(bool $inheritRoleGrantsFromRoutes = true, bool $deactivateLegacyGrants = false): array
    {
        $catalog = $this->sync($inheritRoleGrantsFromRoutes);
        $grants = (new DataAccessGrantMigratorService())->migrate($deactivateLegacyGrants);

        return [
            'catalog' => $catalog,
            'grants' => $grants,
        ];
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
     * Roles que tenían la ruta legacy reciben también el permiso lógico.
     */
    private function inheritRoleGrantsFromRoute(string $childTable, string $route, string $permissionKey): int
    {
        $roles = (new \yii\db\Query())
            ->select('parent')
            ->from($childTable)
            ->where(['child' => $route])
            ->column(\Yii::$app->db);

        $added = 0;
        foreach ($roles as $role) {
            if (!is_string($role) || $role === '') {
                continue;
            }
            if ((new \yii\db\Query())->from('{{%auth_item}}')->where(['name' => $role, 'type' => 1])->exists(\Yii::$app->db)) {
                if ($this->ensureChildLink($childTable, $role, $permissionKey)) {
                    $added++;
                }
            }
        }

        return $added;
    }
}
