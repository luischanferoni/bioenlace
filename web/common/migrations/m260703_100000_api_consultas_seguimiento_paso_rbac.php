<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC: pasos UI intake consultas y seguimiento (hereda de crear turno paciente).
 */
class m260703_100000_api_consultas_seguimiento_paso_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    private const API_ROUTE = '/api/consultas-seguimiento/paso';

    private const API_PARENT_ROUTE = '/api/turnos/crear-como-paciente';

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
        if (!(new Query())->from($authItem)->where(['name' => self::API_ROUTE])->exists($this->db)) {
            $this->db->createCommand()->insert($authItem, [
                'name' => self::API_ROUTE,
                'type' => self::ROUTE_TYPE,
                'description' => 'API consultas-seguimiento: paso intake UI',
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
                ->where(['parent' => $parent, 'child' => self::API_ROUTE])
                ->exists($this->db);
            if ($exists) {
                continue;
            }
            $this->db->createCommand()->insert($childTable, [
                'parent' => $parent,
                'child' => self::API_ROUTE,
            ])->execute();
        }
    }

    public function safeDown(): void
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($childTable, true) !== null) {
            $this->delete($childTable, ['child' => self::API_ROUTE]);
        }
        if ($this->db->schema->getTableSchema($authItem, true) !== null) {
            $this->delete($authItem, ['name' => self::API_ROUTE]);
        }
    }
}
