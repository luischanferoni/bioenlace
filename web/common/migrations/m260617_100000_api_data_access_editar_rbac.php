<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC: edición dispersa staff (/api/editar).
 */
class m260617_100000_api_data_access_editar_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    private const ROUTE = '/api/editar';

    private const PARENT = '/api/listar';

    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            echo "m260617_100000: omitido (driver {$this->db->driverName}).\n";

            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null) {
            echo "m260617_100000: sin tabla auth_item, omitido.\n";

            return;
        }

        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($childTable, true) === null) {
            echo "m260617_100000: sin tabla auth_item_child, omitido.\n";

            return;
        }

        $now = time();
        $this->ensureRoute($authItem, self::ROUTE, $now);
        $this->inheritFrom($childTable, self::PARENT, self::ROUTE);
    }

    public function safeDown()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');

        if ($this->db->schema->getTableSchema($childTable, true) !== null) {
            $this->db->createCommand()->delete($childTable, ['child' => self::ROUTE])->execute();
        }
        if ($this->db->schema->getTableSchema($authItem, true) !== null) {
            $this->db->createCommand()->delete($authItem, ['name' => self::ROUTE])->execute();
        }
    }

    private function ensureRoute(string $authItem, string $name, int $now): void
    {
        if ((new Query())->from($authItem)->where(['name' => $name])->exists($this->db)) {
            return;
        }

        $row = [
            'name' => $name,
            'type' => self::ROUTE_TYPE,
            'description' => 'API edición dispersa staff (DataAccess /editar)',
            'rule_name' => null,
            'data' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if ($this->columnExists($authItem, 'group_code')) {
            $row['group_code'] = 'recursos_humanos';
        }

        $this->db->createCommand()->insert($authItem, $row)->execute();
    }

    private function inheritFrom(string $childTable, string $parentRoute, string $newRoute): void
    {
        $parents = (new Query())
            ->select('parent')
            ->from($childTable)
            ->where(['child' => $parentRoute])
            ->column($this->db);

        foreach ($parents as $parent) {
            if ((new Query())->from($childTable)->where([
                'parent' => $parent,
                'child' => $newRoute,
            ])->exists($this->db)) {
                continue;
            }
            $this->db->createCommand()->insert($childTable, [
                'parent' => $parent,
                'child' => $newRoute,
            ])->execute();
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $schema = $this->db->getTableSchema($table, true);

        return $schema !== null && isset($schema->columns[$column]);
    }
}
