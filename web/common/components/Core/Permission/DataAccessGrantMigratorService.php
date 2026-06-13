<?php

namespace common\components\Core\Permission;

use common\components\Core\DataAccess\QueryOperation;
use Yii;
use yii\db\Query;

/**
 * Migra grants legacy data_access_role_grant → auth_item + auth_item_child (rol → Entidad.atributo.op).
 * No-op si la tabla legacy ya fue eliminada (m260627).
 */
final class DataAccessGrantMigratorService
{
    /**
     * @return array{
     *   permissions_created: int,
     *   role_links_added: int,
     *   grants_processed: int,
     *   grants_skipped: int,
     *   deactivated: int,
     *   warnings: list<string>,
     *   errors: list<string>
     * }
     */
    public function migrate(bool $deactivateLegacyGrants = false): array
    {
        $db = Yii::$app->db;
        $authItem = $db->schema->getRawTableName('{{%auth_item}}');
        $childTable = $db->schema->getRawTableName('{{%auth_item_child}}');
        $grantTable = $db->schema->getRawTableName('{{%data_access_role_grant}}');

        if ($db->schema->getTableSchema($authItem, true) === null
            || $db->schema->getTableSchema($childTable, true) === null) {
            return [
                'permissions_created' => 0,
                'role_links_added' => 0,
                'grants_processed' => 0,
                'grants_skipped' => 0,
                'deactivated' => 0,
                'warnings' => [],
                'errors' => ['Tablas auth_item / auth_item_child no disponibles'],
            ];
        }

        if ($db->schema->getTableSchema($grantTable, true) === null) {
            return [
                'permissions_created' => 0,
                'role_links_added' => 0,
                'grants_processed' => 0,
                'grants_skipped' => 0,
                'deactivated' => 0,
                'warnings' => ['Tabla data_access_role_grant no existe; nada que migrar.'],
                'errors' => [],
            ];
        }

        $now = time();
        $permissionsCreated = 0;
        $roleLinksAdded = 0;
        $grantsProcessed = 0;
        $grantsSkipped = 0;
        $deactivated = 0;
        $warnings = [];
        $errors = [];

        $rows = (new Query())
            ->from($grantTable)
            ->where(['active' => 1])
            ->all($db);

        foreach ($rows as $grant) {
            $grantId = (int) ($grant['id'] ?? 0);
            $roleName = trim((string) ($grant['role_name'] ?? ''));
            $groupKey = trim((string) ($grant['entity_group_key'] ?? ''));
            $operations = self::parseOperationsCsv((string) ($grant['operations_csv'] ?? ''));
            if ($roleName === '' || $groupKey === '' || $operations === []) {
                $grantsSkipped++;
                continue;
            }

            $permissionKeys = [];
            foreach ($operations as $queryOp) {
                if (!QueryOperation::isValid($queryOp)) {
                    continue;
                }
                foreach (AttributePermissionKeyMapper::permissionKeysForGroup($groupKey, $queryOp) as $key) {
                    $permissionKeys[$key] = true;
                }
            }

            if ($permissionKeys === []) {
                $grantsSkipped++;
                $warnings[] = 'Grant sin atributos resolubles: ' . $roleName . ' / ' . $groupKey;

                continue;
            }

            $grantsProcessed++;
            foreach (array_keys($permissionKeys) as $permKey) {
                if (!$this->authItemExists($authItem, $permKey)) {
                    try {
                        $db->createCommand()->insert($authItem, [
                            'name' => $permKey,
                            'type' => 2,
                            'description' => 'Migrado desde data_access_role_grant (' . $groupKey . ')',
                            'rule_name' => null,
                            'data' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ])->execute();
                        $permissionsCreated++;
                    } catch (\Throwable $e) {
                        $errors[] = $permKey . ': ' . $e->getMessage();
                    }
                }

                if ($this->ensureChildLink($childTable, $roleName, $permKey)) {
                    $roleLinksAdded++;
                }
            }

            if ($deactivateLegacyGrants && $grantId > 0) {
                $notas = trim((string) ($grant['notas'] ?? '') . ' [migrado a auth_item ' . date('Y-m-d') . ']');
                $updated = $db->createCommand()->update($grantTable, [
                    'active' => 0,
                    'notas' => $notas,
                ], ['id' => $grantId])->execute();
                if ($updated > 0) {
                    $deactivated++;
                }
            }
        }

        return [
            'permissions_created' => $permissionsCreated,
            'role_links_added' => $roleLinksAdded,
            'grants_processed' => $grantsProcessed,
            'grants_skipped' => $grantsSkipped,
            'deactivated' => $deactivated,
            'warnings' => $warnings,
            'errors' => $errors,
        ];
    }

    /**
     * @return list<string>
     */
    private static function parseOperationsCsv(string $csv): array
    {
        if ($csv === '') {
            return [];
        }

        $ops = array_values(array_unique(array_filter(array_map(static function ($op) {
            return mb_strtolower(trim((string) $op), 'UTF-8');
        }, explode(',', $csv)))));
        sort($ops);

        return $ops;
    }

    private function authItemExists(string $authItem, string $name): bool
    {
        return (new Query())
            ->from($authItem)
            ->where(['name' => $name])
            ->exists(Yii::$app->db);
    }

    private function ensureChildLink(string $childTable, string $parent, string $child): bool
    {
        if ((new Query())->from($childTable)->where(['parent' => $parent, 'child' => $child])->exists(Yii::$app->db)) {
            return false;
        }
        Yii::$app->db->createCommand()->insert($childTable, [
            'parent' => $parent,
            'child' => $child,
        ])->execute();

        return true;
    }
}
