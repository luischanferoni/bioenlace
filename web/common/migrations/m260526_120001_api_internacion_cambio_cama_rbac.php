<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC: cambio de cama internación (UI JSON + API).
 */
class m260526_120001_api_internacion_cambio_cama_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    private const ROUTES = [
        '/api/clinical/internacion/cambio-cama-formulario',
    ];

    private const INHERIT_FROM = '/api/clinical/internacion/alta-formulario';

    public function safeUp()
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
        foreach (self::ROUTES as $route) {
            if (!(new Query())->from($authItem)->where(['name' => $route])->exists($this->db)) {
                $this->db->createCommand()->insert($authItem, [
                    'name' => $route,
                    'type' => self::ROUTE_TYPE,
                    'description' => 'API internación — cambio de cama',
                    'rule_name' => null,
                    'data' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->execute();
            }
        }

        $parents = (new Query())
            ->select('parent')
            ->from($childTable)
            ->where(['child' => self::INHERIT_FROM])
            ->column($this->db);

        foreach ($parents as $parent) {
            foreach (self::ROUTES as $ghost) {
                if ((new Query())->from($childTable)->where([
                    'parent' => $parent,
                    'child' => $ghost,
                ])->exists($this->db)) {
                    continue;
                }
                $this->db->createCommand()->insert($childTable, [
                    'parent' => $parent,
                    'child' => $ghost,
                ])->execute();
            }
        }
    }

    public function safeDown()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($childTable, true) !== null) {
            $this->db->createCommand()->delete($childTable, ['child' => self::ROUTES])->execute();
        }
        if ($this->db->schema->getTableSchema($authItem, true) !== null) {
            $this->db->createCommand()->delete($authItem, ['name' => self::ROUTES])->execute();
        }
    }
}
