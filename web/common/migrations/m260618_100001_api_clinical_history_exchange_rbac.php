<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC: consulta estado export FHIR HC (staff).
 */
class m260618_100001_api_clinical_history_exchange_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    private const GHOST_ROUTES = [
        '/api/clinical/history-exchange/listar-por-encounter',
        '/api/clinical/history-exchange/ver-estado',
    ];

    private const INHERIT_FROM = [
        '/api/pacientes/historia-clinica',
        '/api/clinical/encounter/guardar',
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
            foreach (self::INHERIT_FROM as $parentRoute) {
                if (!(new Query())->from($authItem)->where(['name' => $parentRoute])->exists($this->db)) {
                    continue;
                }
                foreach (self::GHOST_ROUTES as $route) {
                    $this->inheritFrom($childTable, $parentRoute, $route);
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
            'description' => 'API export FHIR historia clínica (estado jobs)',
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
