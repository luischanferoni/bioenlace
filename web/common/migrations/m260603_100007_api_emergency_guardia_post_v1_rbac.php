<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC: guardia post-v1 (pedidos, internación, SLA, CSV).
 */
class m260603_100007_api_emergency_guardia_post_v1_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    private const GHOST_ROUTES = [
        '/api/clinical/emergency-guardia/resumen-clinico',
        '/api/clinical/emergency-guardia/crear-pedido',
        '/api/clinical/emergency-guardia/solicitar-internacion',
        '/api/clinical/emergency-guardia/indicadores-export-csv',
        '/api/clinical/emergency-guardia/sla-config',
    ];

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
        foreach (self::GHOST_ROUTES as $route) {
            if (!(new Query())->from($authItem)->where(['name' => $route])->exists($this->db)) {
                $this->db->createCommand()->insert($authItem, [
                    'name' => $route,
                    'type' => self::ROUTE_TYPE,
                    'description' => 'API urgencias guardia (post-v1)',
                    'rule_name' => null,
                    'data' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->execute();
            }
        }

        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($childTable, true) === null) {
            return;
        }

        $parents = (new Query())
            ->select('parent')
            ->from($childTable)
            ->where(['child' => self::INHERIT_FROM])
            ->column($this->db);

        foreach ($parents as $parent) {
            foreach (self::GHOST_ROUTES as $ghost) {
                if ((new Query())->from($childTable)->where([
                    'parent' => $parent,
                    'child' => $ghost,
                ])->exists($this->db)) {
                    continue;
                }
                $this->db->createCommand()->insert($childTable, [
                    'parent' => $parent,
                    'child' => $ghost,
                ])->execute();
            }
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
            $this->db->createCommand()->delete($childTable, ['child' => self::GHOST_ROUTES])->execute();
        }
        if ($this->db->schema->getTableSchema($authItem, true) !== null) {
            $this->db->createCommand()->delete($authItem, ['name' => self::GHOST_ROUTES])->execute();
        }
    }
}
