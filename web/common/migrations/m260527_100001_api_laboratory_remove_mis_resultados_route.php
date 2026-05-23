<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Retira RBAC de GET JSON `mis-resultados` (sustituido por `mis-resultados-como-paciente`).
 */
class m260527_100001_api_laboratory_remove_mis_resultados_route extends Migration
{
    private const ROUTES = [
        '/api/clinical/laboratory-result/mis-resultados',
        '/api/clinical/laboratory-results/mis-resultados',
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

        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($childTable, true) !== null) {
            $this->db->createCommand()->delete($childTable, ['child' => self::ROUTES])->execute();
            $this->db->createCommand()->delete($childTable, ['parent' => self::ROUTES])->execute();
        }

        $this->db->createCommand()->delete($authItem, ['name' => self::ROUTES])->execute();
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

        $now = time();
        foreach (self::ROUTES as $route) {
            if ((new Query())->from($authItem)->where(['name' => $route])->exists($this->db)) {
                continue;
            }
            $this->db->createCommand()->insert($authItem, [
                'name' => $route,
                'type' => 3,
                'description' => 'API laboratorio externo FHIR (legacy)',
                'rule_name' => null,
                'data' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->execute();
        }
    }
}
