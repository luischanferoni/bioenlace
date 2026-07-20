<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC: ABM care_protocol (superadmin bypassa; rutas registradas).
 */
class m260720_160001_api_care_protocol_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    private const ROUTES = [
        '/api/clinical/care-protocol/listar-admin',
        '/api/clinical/care-protocol/ver',
        '/api/clinical/care-protocol/crear',
        '/api/clinical/care-protocol/actualizar',
        '/api/clinical/care-protocol/desactivar',
        '/api/clinical/care-protocol/activar',
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
                'description' => 'API ABM protocolos de cuidado (superadmin)',
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
