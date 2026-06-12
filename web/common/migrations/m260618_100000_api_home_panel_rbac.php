<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Panel de inicio unificado: /api/home/panel reemplaza /api/pacientes/listar y tablero HTTP.
 */
class m260618_100000_api_home_panel_rbac extends Migration
{
    private const ROUTE_TYPE = 3;

    private const PANEL_ROUTE = '/api/home/panel';

    /** @var array<string, string> child => parent */
    private const INHERIT = [
        '/api/clinical/emergency-guardia/ingresar' => self::PANEL_ROUTE,
        '/api/clinical/emergency-guardia/registrar-triage' => self::PANEL_ROUTE,
        '/api/clinical/emergency-guardia/ver' => self::PANEL_ROUTE,
        '/api/clinical/emergency-guardia/asignar' => self::PANEL_ROUTE,
        '/api/clinical/emergency-guardia/iniciar-atencion' => self::PANEL_ROUTE,
        '/api/clinical/emergency-guardia/derivar' => self::PANEL_ROUTE,
        '/api/clinical/emergency-guardia/finalizar' => self::PANEL_ROUTE,
        '/api/clinical/emergency-guardia/indicadores-resumen' => self::PANEL_ROUTE,
        '/api/clinical/emergency-guardia/listar-efectores-derivacion' => self::PANEL_ROUTE,
        '/api/clinical/emergency-guardia/resumen-clinico' => self::PANEL_ROUTE,
        '/api/clinical/emergency-guardia/crear-pedido' => self::PANEL_ROUTE,
        '/api/clinical/emergency-guardia/solicitar-internacion' => self::PANEL_ROUTE,
        '/api/clinical/emergency-guardia/indicadores-export-csv' => self::PANEL_ROUTE,
        '/api/clinical/emergency-guardia/sla-config' => self::PANEL_ROUTE,
        '/api/clinical/emergency-guardia/elegir-paciente-triage' => self::PANEL_ROUTE,
        '/api/clinical/emergency-guardia/registrar-triage-formulario' =>
            '/api/clinical/emergency-guardia/registrar-triage',
        '/api/turnos/indicadores-agenda' => self::PANEL_ROUTE,
        '/api/clinical/internacion/mapa-camas' => self::PANEL_ROUTE,
        '/api/clinical/care-plans/adherencia-resumen-staff' => '/api/clinical/care-plan/active',
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
        $this->ensureRoute($authItem, self::PANEL_ROUTE, $now);

        foreach (array_keys(self::INHERIT) as $route) {
            $this->ensureRoute($authItem, $route, $now);
        }

        $legacyParents = array_unique(array_filter([
            '/api/pacientes/listar',
            '/api/clinical/emergency-guardia/tablero',
        ]));
        foreach ($legacyParents as $legacy) {
            $this->inheritFrom($childTable, $legacy, self::PANEL_ROUTE);
        }

        foreach (self::INHERIT as $childRoute => $parentRoute) {
            $this->inheritFrom($childTable, $parentRoute, $childRoute);
        }

        foreach ($legacyParents as $legacy) {
            $this->deleteRoute($childTable, $authItem, $legacy);
        }
    }

    public function safeDown()
    {
    }

    private function ensureRoute(string $authItem, string $route, int $now): void
    {
        if ((new Query())->from($authItem)->where(['name' => $route])->exists($this->db)) {
            return;
        }
        $this->db->createCommand()->insert($authItem, [
            'name' => $route,
            'type' => self::ROUTE_TYPE,
            'description' => 'API home panel',
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

    private function deleteRoute(string $childTable, string $authItem, string $route): void
    {
        $this->db->createCommand()->delete($childTable, ['child' => $route])->execute();
        $this->db->createCommand()->delete($childTable, ['parent' => $route])->execute();
        $this->db->createCommand()->delete($authItem, ['name' => $route])->execute();
    }
}
