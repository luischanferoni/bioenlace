<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC: listar efectores derivación (si m260603_100002 se aplicó antes de incluir esta ruta).
 */
class m260603_100004_api_emergency_listar_efectores_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    private const GHOST_ROUTE = '/api/clinical/emergency-guardia/listar-efectores-derivacion';

    private const INHERIT_FROM = '/api/clinical/emergency-guardia/tablero';

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
        if (!(new Query())->from($authItem)->where(['name' => self::GHOST_ROUTE])->exists($this->db)) {
            $this->db->createCommand()->insert($authItem, [
                'name' => self::GHOST_ROUTE,
                'type' => self::ROUTE_TYPE,
                'description' => 'API urgencias: efectores para derivación',
                'rule_name' => null,
                'data' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->execute();
        }

        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($childTable, true) === null) {
            return;
        }

        if (!(new Query())->from($authItem)->where(['name' => self::INHERIT_FROM])->exists($this->db)) {
            return;
        }

        if (!(new Query())->from($childTable)->where([
            'parent' => self::INHERIT_FROM,
            'child' => self::GHOST_ROUTE,
        ])->exists($this->db)) {
            $this->db->createCommand()->insert($childTable, [
                'parent' => self::INHERIT_FROM,
                'child' => self::GHOST_ROUTE,
            ])->execute();
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
            $this->db->createCommand()->delete($childTable, ['child' => self::GHOST_ROUTE])->execute();
        }
        if ($this->db->schema->getTableSchema($authItem, true) !== null) {
            $this->db->createCommand()->delete($authItem, ['name' => self::GHOST_ROUTE])->execute();
        }
    }
}
