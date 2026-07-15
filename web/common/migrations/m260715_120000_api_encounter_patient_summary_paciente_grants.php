<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Otorga al paciente las rutas JSON de resumen de atención (detalle / última).
 *
 * m260601_100001 solo creaba auth_item; el intent enlaza listar, pero el push móvil
 * llama ver-resumen-como-paciente sin X-Flow-Intent-Id → 403.
 */
class m260715_120000_api_encounter_patient_summary_paciente_grants extends Migration
{
    private const ROUTE_TYPE = 3;

    private const PERMISSION_TYPE = 2;

    private const ROLE_PACIENTE = 'paciente';

    private const API_PARENT_ROUTE = '/api/turnos/crear-como-paciente';

    private const INTENT_PARENT = 'turnos.crear-como-paciente';

    private const LISTAR_ROUTE = '/api/clinical/encounter-patient-summary/listar-atenciones-como-paciente';

    private const VER_RESUMEN_ROUTE = '/api/clinical/encounter-patient-summary/ver-resumen-como-paciente';

    private const ULTIMA_ROUTE = '/api/clinical/encounter-patient-summary/ultima-atencion-como-paciente';

    /** @var list<string> */
    private const API_ROUTES = [
        self::LISTAR_ROUTE,
        self::VER_RESUMEN_ROUTE,
        self::ULTIMA_ROUTE,
    ];

    private const INTENT_MIS_ATENCIONES = 'atencion.mis-atenciones-como-paciente';

    private const INTENT_ULTIMA = 'atencion.ver-ultima-como-paciente';

    /** UI JSON hereda padres de las rutas API base. */
    private const UI_FROM_API = [
        '/api/clinical/encounter-patient-summary/mis-atenciones-como-paciente' => self::LISTAR_ROUTE,
        '/api/clinical/encounter-patient-summary/ver-resumen-atencion-como-paciente' => self::VER_RESUMEN_ROUTE,
        '/api/clinical/encounter-patient-summary/ultima-atencion-ui-como-paciente' => self::ULTIMA_ROUTE,
    ];

    /** Path HTTP público (urlManager) → ruta ghost del controller. */
    private const HTTP_ALIASES = [
        '/api/clinical/encounter/listar-atenciones-como-paciente' => self::LISTAR_ROUTE,
        '/api/clinical/encounter/ver-resumen-como-paciente' => self::VER_RESUMEN_ROUTE,
        '/api/clinical/encounter/ultima-atencion-como-paciente' => self::ULTIMA_ROUTE,
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
        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null
            || $this->db->schema->getTableSchema($childTable, true) === null) {
            return;
        }

        $now = time();

        foreach (self::API_ROUTES as $route) {
            $this->ensureRoute($authItem, $route, $now, 'API resumen atención paciente');
            $this->inheritFrom($childTable, self::API_PARENT_ROUTE, $route);
            $this->ensureChild($childTable, self::ROLE_PACIENTE, $route);
        }

        // Quien ya tenía listar (p. ej. vía intent) también obtiene detalle / última.
        $this->inheritFrom($childTable, self::LISTAR_ROUTE, self::VER_RESUMEN_ROUTE);
        $this->inheritFrom($childTable, self::LISTAR_ROUTE, self::ULTIMA_ROUTE);

        $this->ensurePermission($authItem, self::INTENT_MIS_ATENCIONES, $now);
        $this->ensurePermission($authItem, self::INTENT_ULTIMA, $now);
        $this->inheritFrom($childTable, self::INTENT_PARENT, self::INTENT_MIS_ATENCIONES);
        $this->inheritFrom($childTable, self::INTENT_PARENT, self::INTENT_ULTIMA);
        $this->ensureChild($childTable, self::ROLE_PACIENTE, self::INTENT_MIS_ATENCIONES);
        $this->ensureChild($childTable, self::ROLE_PACIENTE, self::INTENT_ULTIMA);

        $this->ensureChild($childTable, self::INTENT_MIS_ATENCIONES, self::LISTAR_ROUTE);
        $this->ensureChild($childTable, self::INTENT_MIS_ATENCIONES, self::VER_RESUMEN_ROUTE);
        $this->ensureChild($childTable, self::INTENT_ULTIMA, self::ULTIMA_ROUTE);

        foreach (self::UI_FROM_API as $uiRoute => $apiRoute) {
            $this->ensureRoute($authItem, $uiRoute, $now, 'API resumen atención paciente (UI)');
            $this->inheritFrom($childTable, $apiRoute, $uiRoute);
        }

        foreach (self::HTTP_ALIASES as $alias => $ghost) {
            $this->ensureRoute($authItem, $alias, $now, 'API resumen atención paciente (HTTP)');
            $this->inheritFrom($childTable, $ghost, $alias);
        }

        $this->bumpRbacRevision();
    }

    public function safeDown()
    {
        // Solo reponía grants; no retirar permisos ya otorgados al rol.
    }

    private function ensureRoute(string $authItem, string $route, int $now, string $description): void
    {
        if ((new Query())->from($authItem)->where(['name' => $route])->exists($this->db)) {
            return;
        }
        $this->db->createCommand()->insert($authItem, [
            'name' => $route,
            'type' => self::ROUTE_TYPE,
            'description' => $description,
            'rule_name' => null,
            'data' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->execute();
    }

    private function ensurePermission(string $authItem, string $intentId, int $now): void
    {
        if ((new Query())->from($authItem)->where(['name' => $intentId])->exists($this->db)) {
            return;
        }
        $this->db->createCommand()->insert($authItem, [
            'name' => $intentId,
            'type' => self::PERMISSION_TYPE,
            'description' => 'Intent ' . $intentId,
            'rule_name' => null,
            'data' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->execute();
    }

    private function inheritFrom(string $childTable, string $parentChild, string $newChild): void
    {
        $parents = (new Query())
            ->select('parent')
            ->from($childTable)
            ->where(['child' => $parentChild])
            ->column($this->db);

        foreach ($parents as $parent) {
            if (!is_string($parent) || $parent === '') {
                continue;
            }
            $this->ensureChild($childTable, $parent, $newChild);
        }
    }

    private function ensureChild(string $childTable, string $parent, string $child): void
    {
        if ((new Query())->from($childTable)->where([
            'parent' => $parent,
            'child' => $child,
        ])->exists($this->db)) {
            return;
        }
        $this->db->createCommand()->insert($childTable, [
            'parent' => $parent,
            'child' => $child,
        ])->execute();
    }

    private function bumpRbacRevision(): void
    {
        try {
            if (class_exists(\common\components\Platform\Core\Permission\BioenlaceRbacRevision::class)) {
                \common\components\Platform\Core\Permission\BioenlaceRbacRevision::bump();
            }
        } catch (\Throwable $e) {
            // Migración no debe fallar por cache de revisión.
        }
    }
}
