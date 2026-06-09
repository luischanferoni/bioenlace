<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC: representación paciente — tutela (tutor) y gestión staff.
 */
class m260616_110000_api_person_representation_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    private const TUTOR_ROUTES = [
        '/api/person-representation/solicitar-menor-como-tutor',
        '/api/person-representation/mis-vinculos-como-tutor',
    ];

    private const STAFF_ROUTES = [
        '/api/person-representation/verificar-vinculo-para-staff',
        '/api/person-representation/bloquear-para-staff',
        '/api/person-representation/revocar-para-staff',
        '/api/person-representation/vinculos-paciente-para-staff',
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
        foreach (array_merge(self::TUTOR_ROUTES, self::STAFF_ROUTES) as $route) {
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

        $routes = array_merge(self::TUTOR_ROUTES, self::STAFF_ROUTES);
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
            'description' => 'API representación paciente',
            'rule_name' => null,
            'data' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->execute();
    }
}
