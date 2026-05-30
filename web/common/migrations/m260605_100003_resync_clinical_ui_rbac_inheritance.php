<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Re-sincroniza herencia RBAC UI/ghost → API base (idempotente).
 *
 * Cubre rutas cuyo `rbac_route` en intents YAML debe apuntar al permiso API padre;
 * las hijas se re-enlazan por si el rol recibió el padre después del migrate original.
 */
class m260605_100003_resync_clinical_ui_rbac_inheritance extends Migration
{
    private const ROUTE_TYPE = 3;

    /** @var array<string, string> child => parent */
    private const INHERIT = [
        // Laboratorio (m260524, m260525, m260526)
        '/api/clinical/laboratory-results/mis-resultados-como-paciente' =>
            '/api/clinical/laboratory-results/mis-resultados',
        '/api/clinical/laboratory-results/sincronizar-como-paciente' =>
            '/api/clinical/laboratory-results/sincronizar',
        '/api/clinical/laboratory-results/ver-informe-como-paciente' =>
            '/api/clinical/laboratory-results/mis-resultados-como-paciente',
        '/api/clinical/laboratory-results/descargar-pdf-como-paciente' =>
            '/api/clinical/laboratory-results/mis-resultados',
        '/api/clinical/laboratory-result/mis-resultados' =>
            '/api/clinical/laboratory-results/mis-resultados',
        '/api/clinical/laboratory-result/sincronizar' =>
            '/api/clinical/laboratory-results/sincronizar',
        '/api/clinical/laboratory-result/mis-resultados-como-paciente' =>
            '/api/clinical/laboratory-results/mis-resultados-como-paciente',
        '/api/clinical/laboratory-result/sincronizar-como-paciente' =>
            '/api/clinical/laboratory-results/sincronizar-como-paciente',
        '/api/clinical/laboratory-result/ver-informe-como-paciente' =>
            '/api/clinical/laboratory-results/ver-informe-como-paciente',
        '/api/clinical/laboratory-result/descargar-pdf-como-paciente' =>
            '/api/clinical/laboratory-results/descargar-pdf-como-paciente',
        // Care plan recordatorios (m260531)
        '/api/clinical/care-plan/preferencias-recordatorios-como-paciente' =>
            '/api/clinical/care-plans/preferencias-recordatorios-como-paciente',
        '/api/clinical/care-plan/actualizar-preferencias-recordatorios-como-paciente' =>
            '/api/clinical/care-plans/preferencias-recordatorios-como-paciente',
        '/api/clinical/care-plan/recordatorios-como-paciente' =>
            '/api/clinical/care-plans/recordatorios-como-paciente',
        // Guardia / agenda / adherencia (m260603, m260604_100000)
        '/api/clinical/emergency-guardia/tablero' => '/api/pacientes/listar',
        '/api/clinical/emergency-guardia/ingresar' => '/api/pacientes/listar',
        '/api/clinical/emergency-guardia/registrar-triage' => '/api/pacientes/listar',
        '/api/clinical/emergency-guardia/ver' => '/api/pacientes/listar',
        '/api/clinical/emergency-guardia/elegir-paciente-triage' =>
            '/api/clinical/emergency-guardia/tablero',
        '/api/clinical/emergency-guardia/registrar-triage-formulario' =>
            '/api/clinical/emergency-guardia/registrar-triage',
        '/api/turnos/indicadores-agenda' => '/api/pacientes/listar',
        '/api/clinical/care-plans/adherencia-resumen-staff' => '/api/clinical/care-plan/active',
        // Internación (m260604_100002, m260526)
        '/api/clinical/internacion/mapa-camas' => '/api/pacientes/listar',
        '/api/clinical/internacion/indicadores-resumen' =>
            '/api/clinical/internacion/mapa-camas',
        '/api/clinical/internacion/marcar-estado' => '/api/clinical/internacion/mapa-camas',
        '/api/clinical/internacion/alta-formulario' =>
            '/api/clinical/episode-of-care/by-internacion',
        '/api/clinical/internacion/ingreso-formulario' =>
            '/api/clinical/internacion/mapa-camas',
        '/api/clinical/internacion/cambio-cama-formulario' =>
            '/api/clinical/internacion/alta-formulario',
        // Turnos slots días (m260522)
        '/api/turnos/slots-dias-disponibles-como-paciente' =>
            '/api/turnos/slots-disponibles-como-paciente',
    ];

    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null
            || $this->db->schema->getTableSchema($childTable, true) === null) {
            return;
        }

        $now = time();
        foreach (array_keys(self::INHERIT) as $route) {
            $this->ensureRoute($authItem, $route, $now);
        }

        foreach (self::INHERIT as $childRoute => $parentRoute) {
            $this->inheritFrom($childTable, $parentRoute, $childRoute);
        }
    }

    public function safeDown()
    {
        // Sin down: solo repone enlaces.
    }

    private function ensureRoute(string $authItem, string $route, int $now): void
    {
        if ((new Query())->from($authItem)->where(['name' => $route])->exists($this->db)) {
            return;
        }
        $this->db->createCommand()->insert($authItem, [
            'name' => $route,
            'type' => self::ROUTE_TYPE,
            'description' => 'API (resync herencia UI)',
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
