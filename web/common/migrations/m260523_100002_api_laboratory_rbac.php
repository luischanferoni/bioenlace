<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC rutas API laboratorio externo.
 */
class m260523_100002_api_laboratory_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    private const ROUTES = [
        '/api/clinical/laboratory-results/mis-resultados',
        '/api/clinical/laboratory-results/sincronizar',
        '/api/clinical/encounter/laboratory-results',
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
        foreach (self::ROUTES as $route) {
            $exists = (new Query())->from($authItem)->where(['name' => $route])->exists($this->db);
            if ($exists) {
                continue;
            }
            $this->db->createCommand()->insert($authItem, [
                'name' => $route,
                'type' => self::ROUTE_TYPE,
                'description' => 'API laboratorio externo FHIR',
                'rule_name' => null,
                'data' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->execute();
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

        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($childTable, true) !== null) {
            $this->db->createCommand()->delete($childTable, ['child' => self::ROUTES])->execute();
        }
        $this->db->createCommand()->delete($authItem, ['name' => self::ROUTES])->execute();
    }
}
