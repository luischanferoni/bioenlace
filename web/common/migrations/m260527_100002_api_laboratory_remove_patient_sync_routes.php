<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Retira RBAC de sincronización expuesta al paciente (ingesta solo consola/cron).
 */
class m260527_100002_api_laboratory_remove_patient_sync_routes extends Migration
{
    private const ROUTES = [
        '/api/clinical/laboratory-result/sincronizar',
        '/api/clinical/laboratory-results/sincronizar',
        '/api/clinical/laboratory-result/sincronizar-como-paciente',
        '/api/clinical/laboratory-results/sincronizar-como-paciente',
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
                'description' => 'API laboratorio sync paciente (legacy)',
                'rule_name' => null,
                'data' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->execute();
        }
    }
}
