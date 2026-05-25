<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC: resumen de atención paciente (ApiGhost: clinical/encounter-patient-summary/*).
 */
class m260601_100001_api_encounter_patient_summary_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    /** Rutas que chequea ApiGhost (controller id). */
    private const GHOST_ROUTES = [
        '/api/clinical/encounter-patient-summary/listar-atenciones-como-paciente',
        '/api/clinical/encounter-patient-summary/ver-resumen-como-paciente',
        '/api/clinical/encounter-patient-summary/ultima-atencion-como-paciente',
    ];

    /** Alias HTTP (documentación / permisos heredados). */
    private const HTTP_ALIASES = [
        '/api/clinical/encounter/listar-atenciones-como-paciente' =>
            '/api/clinical/encounter-patient-summary/listar-atenciones-como-paciente',
        '/api/clinical/encounter/ver-resumen-como-paciente' =>
            '/api/clinical/encounter-patient-summary/ver-resumen-como-paciente',
        '/api/clinical/encounter/ultima-atencion-como-paciente' =>
            '/api/clinical/encounter-patient-summary/ultima-atencion-como-paciente',
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
            foreach (self::HTTP_ALIASES as $alias => $ghost) {
                $this->ensureRoute($authItem, $alias, $now);
                $this->inheritFrom($childTable, $ghost, $alias);
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

        $routes = array_merge(self::GHOST_ROUTES, array_keys(self::HTTP_ALIASES));
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
            'description' => 'API resumen atención paciente',
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
