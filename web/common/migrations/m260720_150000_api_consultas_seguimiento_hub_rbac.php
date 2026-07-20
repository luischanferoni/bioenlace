<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC: hub Control/Seguimiento + acciones provisionales por condición.
 */
class m260720_150000_api_consultas_seguimiento_hub_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    private const API_PARENT_ROUTE = '/api/consultas-seguimiento/paso';

    /** @var list<array{route: string, description: string}> */
    private const ROUTES = [
        [
            'route' => '/api/consultas-seguimiento/hub',
            'description' => 'API consultas-seguimiento: hub control/seguimiento',
        ],
        [
            'route' => '/api/consultas-seguimiento/condicion-acciones',
            'description' => 'API consultas-seguimiento: acciones por condición',
        ],
    ];

    public function safeUp(): void
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null
            || $this->db->schema->getTableSchema($childTable, true) === null) {
            return;
        }

        $now = time();
        foreach (self::ROUTES as $def) {
            $route = $def['route'];
            if (!(new Query())->from($authItem)->where(['name' => $route])->exists($this->db)) {
                $this->db->createCommand()->insert($authItem, [
                    'name' => $route,
                    'type' => self::ROUTE_TYPE,
                    'description' => $def['description'],
                    'rule_name' => null,
                    'data' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->execute();
            }

            $parents = (new Query())
                ->select('parent')
                ->from($childTable)
                ->where(['child' => self::API_PARENT_ROUTE])
                ->column($this->db);

            foreach ($parents as $parent) {
                if (!is_string($parent) || $parent === '') {
                    continue;
                }
                $exists = (new Query())
                    ->from($childTable)
                    ->where(['parent' => $parent, 'child' => $route])
                    ->exists($this->db);
                if ($exists) {
                    continue;
                }
                $this->db->createCommand()->insert($childTable, [
                    'parent' => $parent,
                    'child' => $route,
                ])->execute();
            }
        }
    }

    public function safeDown(): void
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null
            || $this->db->schema->getTableSchema($childTable, true) === null) {
            return;
        }

        foreach (self::ROUTES as $def) {
            $route = $def['route'];
            $this->db->createCommand()->delete($childTable, ['child' => $route])->execute();
            $this->db->createCommand()->delete($authItem, ['name' => $route])->execute();
        }
    }
}
