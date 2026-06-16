<?php

namespace common\components\Platform\Core\Permission;

use common\components\Platform\Core\Product\ProductMetadataPaths;
use Symfony\Component\Yaml\Yaml;
use yii\db\Query;
use yii\rbac\Item;

/**
 * Copia grants rol→permiso según intent-grant-migration-map.yaml.
 */
final class IntentGrantMigrationService
{
    /**
     * @return array{created_permissions: int, role_grants: int, errors: list<string>}
     */
    public function migrate(): array
    {
        $map = $this->loadMap();
        $db = \Yii::$app->db;
        $authItem = $db->schema->getRawTableName('{{%auth_item}}');
        $childTable = $db->schema->getRawTableName('{{%auth_item_child}}');
        if ($db->schema->getTableSchema($authItem, true) === null
            || $db->schema->getTableSchema($childTable, true) === null) {
            return ['created_permissions' => 0, 'role_grants' => 0, 'errors' => ['Tablas auth_item no disponibles']];
        }

        $now = time();
        $createdPermissions = 0;
        $roleGrants = 0;
        $errors = [];

        foreach ($map['role_grant_sources'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $source = trim((string) ($row['source_permission'] ?? ''));
            $targets = $row['intent_targets'] ?? [];
            if ($source === '' || !is_array($targets)) {
                continue;
            }

            foreach ($targets as $intentId) {
                $intentId = trim((string) $intentId);
                if ($intentId === '') {
                    continue;
                }
                if ($this->ensurePermission($authItem, $intentId, $now)) {
                    $createdPermissions++;
                }
                $roleGrants += $this->copyRoleGrants($childTable, $source, $intentId, $errors);
            }
        }

        return [
            'created_permissions' => $createdPermissions,
            'role_grants' => $roleGrants,
            'errors' => $errors,
        ];
    }

    /**
     * @return array{role_grant_sources: list<array<string, mixed>>, attribute_grant_sources: list<array<string, mixed>>}
     */
    private function loadMap(): array
    {
        $file = ProductMetadataPaths::intentGrantMigrationMapFile();
        if (!is_file($file)) {
            return ['role_grant_sources' => [], 'attribute_grant_sources' => []];
        }

        try {
            $parsed = Yaml::parseFile($file);
        } catch (\Throwable $e) {
            return ['role_grant_sources' => [], 'attribute_grant_sources' => []];
        }

        if (!is_array($parsed)) {
            return ['role_grant_sources' => [], 'attribute_grant_sources' => []];
        }

        return [
            'role_grant_sources' => is_array($parsed['role_grant_sources'] ?? null) ? $parsed['role_grant_sources'] : [],
            'attribute_grant_sources' => is_array($parsed['attribute_grant_sources'] ?? null) ? $parsed['attribute_grant_sources'] : [],
        ];
    }

    private function ensurePermission(string $authItem, string $intentId, int $now): bool
    {
        if ((new Query())->from($authItem)->where(['name' => $intentId])->exists()) {
            return false;
        }

        \Yii::$app->db->createCommand()->insert($authItem, [
            'name' => $intentId,
            'type' => Item::TYPE_PERMISSION,
            'description' => 'Intent ' . $intentId,
            'rule_name' => null,
            'data' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->execute();

        return true;
    }

    /**
     * @param list<string> $errors
     */
    private function copyRoleGrants(string $childTable, string $source, string $intentId, array &$errors): int
    {
        $parents = (new Query())
            ->select('parent')
            ->from($childTable)
            ->where(['child' => $source])
            ->column();

        $added = 0;
        foreach ($parents as $parent) {
            if (!is_string($parent) || $parent === '') {
                continue;
            }
            if ((new Query())->from($childTable)->where(['parent' => $parent, 'child' => $intentId])->exists()) {
                continue;
            }
            try {
                \Yii::$app->db->createCommand()->insert($childTable, [
                    'parent' => $parent,
                    'child' => $intentId,
                ])->execute();
                $added++;
            } catch (\Throwable $e) {
                $errors[] = 'No se pudo copiar grant ' . $parent . ' → ' . $intentId . ': ' . $e->getMessage();
            }
        }

        return $added;
    }
}
