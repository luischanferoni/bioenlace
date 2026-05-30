<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Re-sincroniza herencia RBAC UI ↔ API de resumen atención paciente.
 *
 * Si el rol Paciente recibió `listar-atenciones-como-paciente` después de m260601_100002,
 * las rutas UI/alias HTTP no quedaron enlazadas; este migrate las repone (idempotente).
 */
class m260605_100002_resync_encounter_patient_summary_ui_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    private const GHOST_UI_ROUTES = [
        '/api/clinical/encounter-patient-summary/mis-atenciones-como-paciente',
        '/api/clinical/encounter-patient-summary/ver-resumen-atencion-como-paciente',
        '/api/clinical/encounter-patient-summary/ultima-atencion-ui-como-paciente',
    ];

    private const UI_FROM_API = [
        '/api/clinical/encounter-patient-summary/mis-atenciones-como-paciente' =>
            '/api/clinical/encounter-patient-summary/listar-atenciones-como-paciente',
        '/api/clinical/encounter-patient-summary/ver-resumen-atencion-como-paciente' =>
            '/api/clinical/encounter-patient-summary/ver-resumen-como-paciente',
        '/api/clinical/encounter-patient-summary/ultima-atencion-ui-como-paciente' =>
            '/api/clinical/encounter-patient-summary/ultima-atencion-como-paciente',
    ];

    private const HTTP_ALIASES = [
        '/api/clinical/encounter/mis-atenciones-como-paciente' =>
            '/api/clinical/encounter-patient-summary/mis-atenciones-como-paciente',
        '/api/clinical/encounter/ver-resumen-atencion-como-paciente' =>
            '/api/clinical/encounter-patient-summary/ver-resumen-atencion-como-paciente',
        '/api/clinical/encounter/ultima-atencion-ui-como-paciente' =>
            '/api/clinical/encounter-patient-summary/ultima-atencion-ui-como-paciente',
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

        foreach (self::GHOST_UI_ROUTES as $route) {
            $this->ensureRoute($authItem, $route, $now);
        }

        if (!$hasChild) {
            return;
        }

        foreach (self::UI_FROM_API as $uiRoute => $apiRoute) {
            $this->inheritFrom($childTable, $apiRoute, $uiRoute);
        }
        foreach (self::HTTP_ALIASES as $alias => $ghost) {
            $this->ensureRoute($authItem, $alias, $now);
            $this->inheritFrom($childTable, $ghost, $alias);
        }
    }

    public function safeDown()
    {
        // Sin down: solo repone enlaces; no retirar permisos ya otorgados.
    }

    private function ensureRoute(string $authItem, string $route, int $now): void
    {
        if ((new Query())->from($authItem)->where(['name' => $route])->exists($this->db)) {
            return;
        }
        $this->db->createCommand()->insert($authItem, [
            'name' => $route,
            'type' => self::ROUTE_TYPE,
            'description' => 'API resumen atención paciente (UI)',
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
