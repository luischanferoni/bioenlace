<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC Fase 4: preferencias recordatorios + ghost care-plan (singular controller id).
 */
class m260531_100001_api_care_plan_reminder_phase4_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    private const NEW_ROUTES = [
        '/api/clinical/care-plans/preferencias-recordatorios-como-paciente',
        '/api/clinical/care-plan/preferencias-recordatorios-como-paciente',
        '/api/clinical/care-plan/actualizar-preferencias-recordatorios-como-paciente',
    ];

    /** @var array<string, string> ghost => ruta HTTP plural ya usada en permisos */
    private const GHOST_FROM_PLURAL = [
        '/api/clinical/care-plan/preferencias-recordatorios-como-paciente' =>
            '/api/clinical/care-plans/preferencias-recordatorios-como-paciente',
        '/api/clinical/care-plan/actualizar-preferencias-recordatorios-como-paciente' =>
            '/api/clinical/care-plans/preferencias-recordatorios-como-paciente',
        '/api/clinical/care-plan/recordatorios-como-paciente' =>
            '/api/clinical/care-plans/recordatorios-como-paciente',
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

        foreach (self::NEW_ROUTES as $route) {
            $this->ensureRoute($authItem, $route, $now);
        }

        foreach (self::GHOST_FROM_PLURAL as $ghost => $plural) {
            $this->ensureRoute($authItem, $ghost, $now);
            if ($hasChild) {
                $this->inheritFrom($childTable, $plural, $ghost);
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

        $routes = array_merge(self::NEW_ROUTES, array_keys(self::GHOST_FROM_PLURAL));
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
            'description' => 'API care plan recordatorios (paciente)',
            'rule_name' => null,
            'data' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->execute();
    }

    private function inheritFrom(string $childTable, string $parentRoute, string $ghostRoute): void
    {
        $parents = (new Query())
            ->select('parent')
            ->from($childTable)
            ->where(['child' => $parentRoute])
            ->column($this->db);

        foreach ($parents as $parent) {
            if ((new Query())->from($childTable)->where([
                'parent' => $parent,
                'child' => $ghostRoute,
            ])->exists($this->db)) {
                continue;
            }
            $this->db->createCommand()->insert($childTable, [
                'parent' => $parent,
                'child' => $ghostRoute,
            ])->execute();
        }
    }
}
