<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC: operaciones guardia Fase 4 (asignar, atención, derivar, finalizar, indicadores).
 */
class m260603_100002_api_emergency_guardia_operaciones_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    private const GHOST_ROUTES = [
        '/api/clinical/emergency-guardia/indicadores-resumen',
        '/api/clinical/emergency-guardia/listar-efectores-derivacion',
        '/api/clinical/emergency-guardia/asignar',
        '/api/clinical/emergency-guardia/iniciar-atencion',
        '/api/clinical/emergency-guardia/derivar',
        '/api/clinical/emergency-guardia/finalizar',
    ];

    private const INHERIT_FROM = [
        '/api/clinical/emergency-guardia/tablero',
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
        $hasChild = $this->db->schema->getTableSchema($childTable, true) !== null;
        $now = time();

        foreach (self::GHOST_ROUTES as $route) {
            $this->ensureRoute($authItem, $route, $now);
        }

        if ($hasChild) {
            foreach (self::GHOST_ROUTES as $ghost) {
                foreach (self::INHERIT_FROM as $parentRoute) {
                    $this->inheritFrom($childTable, $parentRoute, $ghost);
                }
            }
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
            $this->db->createCommand()->delete($childTable, ['child' => self::GHOST_ROUTES])->execute();
        }
        $this->db->createCommand()->delete($authItem, ['name' => self::GHOST_ROUTES])->execute();
    }

    private function ensureRoute(string $authItem, string $route, int $now): void
    {
        if ((new Query())->from($authItem)->where(['name' => $route])->exists($this->db)) {
            return;
        }
        $this->db->createCommand()->insert($authItem, [
            'name' => $route,
            'type' => self::ROUTE_TYPE,
            'description' => 'API urgencias guardia (operaciones)',
            'rule_name' => null,
            'data' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->execute();
    }

    private function inheritFrom(string $childTable, string $parentRoute, string $childRoute): void
    {
        $parents = (new Query())
            ->select('parent')
            ->from($childTable)
            ->where(['child' => $parentRoute])
            ->column($this->db);

        foreach ($parents as $parent) {
            if ((new Query())->from($childTable)->where([
                'parent' => $parent,
                'child' => $childRoute,
            ])->exists($this->db)) {
                continue;
            }
            $this->db->createCommand()->insert($childTable, [
                'parent' => $parent,
                'child' => $childRoute,
            ])->execute();
        }
    }
}
