<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC Fase 2 receta electrónica: UI detalle, PDF y verificación.
 */
class m260529_100001_api_electronic_prescription_phase2_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    private const ROUTES = [
        '/api/clinical/electronic-prescription/ver-receta-como-paciente',
        '/api/clinical/electronic-prescription/descargar-pdf-como-paciente',
        '/api/clinical/electronic-prescription/verificar-receta',
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
            if ((new Query())->from($authItem)->where(['name' => $route])->exists($this->db)) {
                continue;
            }
            $this->db->createCommand()->insert($authItem, [
                'name' => $route,
                'type' => self::ROUTE_TYPE,
                'description' => 'API receta electrónica (Fase 2)',
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
