<?php

namespace common\components\Platform\Core\Permission;

use yii\db\Query;

/**
 * Sincroniza capabilities declarativas → auth_item / auth_item_child.
 */
final class CapabilityPermissionSyncService
{
    private const ROUTE_TYPE = 3;

    private const PERMISSION_TYPE = 2;

    /**
     * @return list<array{key: string, kind: string, description: string, routes: list<string>}>
     */
    public function collectDefinitions(): array
    {
        $out = [];
        foreach (CapabilityManifestIndex::all() as $id => $meta) {
            $out[] = [
                'key' => $id,
                'kind' => 'capability',
                'description' => (string) ($meta['description'] ?? $id),
                'routes' => is_array($meta['routes'] ?? null) ? $meta['routes'] : [],
            ];
        }

        return $out;
    }

    /**
     * @return array{
     *   created: int,
     *   linked: int,
     *   role_grants: int,
     *   intent_links: int,
     *   panel_propagated: int,
     *   skipped: int,
     *   errors: list<string>
     * }
     */
    public function sync(
        bool $applyDefaultRoles = false,
        bool $linkRelatedIntents = true,
        bool $propagateFromHomePanel = false
    ): array {
        $db = \Yii::$app->db;
        $authItem = $db->schema->getRawTableName('{{%auth_item}}');
        $childTable = $db->schema->getRawTableName('{{%auth_item_child}}');
        if ($db->schema->getTableSchema($authItem, true) === null) {
            return $this->emptyResult(['Tabla auth_item no disponible']);
        }

        $hasChild = $db->schema->getTableSchema($childTable, true) !== null;
        $now = time();
        $created = 0;
        $linked = 0;
        $roleGrants = 0;
        $intentLinks = 0;
        $panelPropagated = 0;
        $skipped = 0;
        $errors = [];
        $allRoutes = [];

        foreach (CapabilityManifestIndex::all() as $capabilityId => $meta) {
            if (!$this->authItemExists($authItem, $capabilityId)) {
                try {
                    $description = trim((string) ($meta['description'] ?? ''));
                    $db->createCommand()->insert($authItem, [
                        'name' => $capabilityId,
                        'type' => self::PERMISSION_TYPE,
                        'description' => $description !== '' ? $description : $capabilityId,
                        'rule_name' => null,
                        'data' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->execute();
                    $created++;
                } catch (\Throwable $e) {
                    $errors[] = $capabilityId . ': ' . $e->getMessage();
                }
            } else {
                $skipped++;
            }

            if (!$hasChild) {
                continue;
            }

            foreach ($meta['routes'] ?? [] as $route) {
                if (!is_string($route)) {
                    continue;
                }
                $route = '/' . ltrim(trim($route), '/');
                if ($route === '/') {
                    continue;
                }
                $allRoutes[$route] = true;
                $this->ensureRouteAuthItem($authItem, $route, $now);
                if ($this->ensureChildLink($childTable, $capabilityId, $route)) {
                    $linked++;
                }
            }

            if ($applyDefaultRoles) {
                foreach ($meta['default_roles'] ?? [] as $role) {
                    if (!is_string($role) || trim($role) === '') {
                        continue;
                    }
                    if ($this->ensureChildLink($childTable, trim($role), $capabilityId)) {
                        $roleGrants++;
                    }
                }
            }

            if ($linkRelatedIntents) {
                foreach ($meta['related_intents'] ?? [] as $intentId) {
                    if (!is_string($intentId) || trim($intentId) === '') {
                        continue;
                    }
                    $intentId = trim($intentId);
                    if ($this->ensureChildLink($childTable, $intentId, $capabilityId)) {
                        $intentLinks++;
                    }
                }
            }
        }

        if ($propagateFromHomePanel && $hasChild) {
            $panelRoute = '/api/home/panel';
            foreach (array_keys($allRoutes) as $route) {
                if ($route === $panelRoute) {
                    continue;
                }
                $panelPropagated += $this->propagateRolesFromRoute($childTable, $panelRoute, $route);
            }
        }

        if ($created > 0 || $linked > 0 || $roleGrants > 0 || $intentLinks > 0 || $panelPropagated > 0) {
            BioenlaceRbacRevision::bump();
        }

        return [
            'created' => $created,
            'linked' => $linked,
            'role_grants' => $roleGrants,
            'intent_links' => $intentLinks,
            'panel_propagated' => $panelPropagated,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Roles/intents con acceso a ruta padre reciben enlace directo a ruta hija.
     */
    public function propagateRolesFromRoute(string $parentRoute, string $childRoute): int
    {
        $childTable = \Yii::$app->db->schema->getRawTableName('{{%auth_item_child}}');
        if (\Yii::$app->db->schema->getTableSchema($childTable, true) === null) {
            return 0;
        }

        $added = 0;
        $parents = (new Query())
            ->select('parent')
            ->from($childTable)
            ->where(['child' => '/' . ltrim(trim($parentRoute), '/')])
            ->column(\Yii::$app->db);

        $childRoute = '/' . ltrim(trim($childRoute), '/');
        foreach ($parents as $parent) {
            if (!is_string($parent) || $parent === '') {
                continue;
            }
            if ($this->ensureChildLink($childTable, $parent, $childRoute)) {
                $added++;
            }
        }

        if ($added > 0) {
            BioenlaceRbacRevision::bump();
        }

        return $added;
    }

    /**
     * @param list<string> $errors
     * @return array{
     *   created: int,
     *   linked: int,
     *   role_grants: int,
     *   intent_links: int,
     *   panel_propagated: int,
     *   skipped: int,
     *   errors: list<string>
     * }
     */
    private function emptyResult(array $errors): array
    {
        return [
            'created' => 0,
            'linked' => 0,
            'role_grants' => 0,
            'intent_links' => 0,
            'panel_propagated' => 0,
            'skipped' => 0,
            'errors' => $errors,
        ];
    }

    private function authItemExists(string $authItem, string $name): bool
    {
        return (new Query())
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
            'type' => self::ROUTE_TYPE,
            'description' => 'Ruta API (capability sync)',
            'rule_name' => null,
            'data' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->execute();
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
