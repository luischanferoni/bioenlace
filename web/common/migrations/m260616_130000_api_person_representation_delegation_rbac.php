<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC: delegación paciente → representante (régimen B).
 */
class m260616_130000_api_person_representation_delegation_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    private const PATIENT_ROUTES = [
        '/api/person-representation/designar-representante',
        '/api/person-representation/revocar-representante',
        '/api/person-representation/mis-representantes',
        '/api/person-representation/preferencias-como-paciente',
    ];

    private const REPRESENTATIVE_ROUTES = [
        '/api/person-representation/pacientes-a-cargo',
    ];

    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null) {
            return;
        }

        $now = time();
        foreach (array_merge(self::PATIENT_ROUTES, self::REPRESENTATIVE_ROUTES) as $route) {
            $this->ensureRoute($authItem, $route, $now);
        }
    }

    public function safeDown()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null) {
            return;
        }

        $routes = array_merge(self::PATIENT_ROUTES, self::REPRESENTATIVE_ROUTES);
        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($childTable, true) !== null) {
            $this->db->createCommand()->delete($childTable, ['child' => $routes])->execute();
        }
        $this->db->createCommand()->delete($authItem, ['name' => $routes])->execute();
    }

    private function ensureRoute(string $authItem, string $route, int $now): void
    {
        if ((new Query())->from($authItem)->where(['name' => $route])->exists($this->db)) {
            return;
        }
        $this->db->createCommand()->insert($authItem, [
            'name' => $route,
            'type' => self::ROUTE_TYPE,
            'description' => 'API delegación representación paciente',
            'rule_name' => null,
            'data' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->execute();
    }
}
