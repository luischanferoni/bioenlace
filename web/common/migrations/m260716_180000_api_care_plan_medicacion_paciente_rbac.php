<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC: medicación del CarePlan y confirmar renovación (paciente / flow consultas-seguimiento).
 */
class m260716_180000_api_care_plan_medicacion_paciente_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    private const PARENT_ROUTE = '/api/clinical/care-plan/ver-tratamiento-paciente';

    /** @var list<string> */
    private const ROUTES = [
        '/api/clinical/care-plan/medicamentos-como-paciente',
        '/api/clinical/care-plan/confirmar-renovacion-como-paciente',
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
        foreach (self::ROUTES as $route) {
            if (!(new Query())->from($authItem)->where(['name' => $route])->exists($this->db)) {
                $this->db->createCommand()->insert($authItem, [
                    'name' => $route,
                    'type' => self::ROUTE_TYPE,
                    'description' => 'API clinical care-plan medicación paciente',
                    'rule_name' => null,
                    'data' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->execute();
            }

            $parents = (new Query())
                ->select('parent')
                ->from($childTable)
                ->where(['child' => self::PARENT_ROUTE])
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
        if ($this->db->schema->getTableSchema($childTable, true) !== null) {
            $this->delete($childTable, ['child' => self::ROUTES]);
        }
        if ($this->db->schema->getTableSchema($authItem, true) !== null) {
            $this->delete($authItem, ['name' => self::ROUTES]);
        }
    }
}
