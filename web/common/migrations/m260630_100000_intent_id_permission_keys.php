<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Permisos RBAC de intents: clave = intent_id (reemplaza permisos lógicos Entidad.operacion).
 */
class m260630_100000_intent_id_permission_keys extends Migration
{
    private const PERMISSION_TYPE = 2;

    /** @var array<string, list<string>> legacy permission => intent_id(s) */
    private const LEGACY_TO_INTENTS = [
        'Turno.create' => [
            'turnos.crear-como-paciente',
            'turnos.crear-para-paciente-flow',
            'turnos.crear-sobreturno-flow',
        ],
        'Turno.reprogramar' => [
            'turnos.modificar-como-paciente-flow',
            'turnos.reubicar-como-paciente-flow',
        ],
        'Turno.cancel' => [
            'turnos.cancelar-como-paciente-flow',
            'turnos.cancelar-para-paciente-flow',
        ],
        'Turno.confirmar_asistencia' => 'turnos.confirmar-asistencia-flow',
        'Turno.marcar_no_presentado' => 'turnos.no-se-presento-flow',
        'Turno.view_day' => 'turnos.ver-agenda-dia-profesional-flow',
        'Turno.view_policy' => 'turnos.consultar-politica-autogestion-flow',
        'Turno.view_occupancy' => 'turnos.consultar-ocupacion-dia-flow',
        'Turno.view_indicators' => 'turnos.indicadores-agenda-flow',
        'Turno.view_conflicts' => 'turnos.conflicto-agenda-flow',
        'ProfesionalEfectorServicioAgenda.resolve_conflicts' => 'profesional-agenda.resolver-conflictos-flow',
        'ProfesionalEfectorServicio.create' => 'profesional-efector-servicio.crear-flow',
        'Internacion.discharge' => 'internacion.alta-estructurada-flow',
        'Internacion.change_bed' => 'internacion.cambio-cama-flow',
        'Internacion.create' => 'internacion.ingreso-flow',
        'Internacion.view_map' => 'internacion.mapa-camas-flow',
        'InternacionEpicrisisPlantilla.admin' => 'internacion.epicrisis-plantilla-admin',
        'GuardiaEpisode.triage' => 'urgencias.triage-paciente-guardia',
        'GuardiaEpisode.view_board' => 'urgencias.ver-tablero-guardia',
        'Licencia.create' => [
            'licencia.cargar-como-profesional-flow',
            'licencia.cargar-para-profesional-flow',
        ],
        'Atencion.view_mine' => 'atencion.mis-atenciones-como-paciente',
        'Atencion.view_last' => 'atencion.ver-ultima-como-paciente',
        'Atencion.request' => 'atencion.necesito-atencion',
        'Receta.view' => 'receta.ver-recetas-como-paciente',
        'Laboratorio.view' => 'laboratorio.ver-resultados-como-paciente',
        'Tratamiento.view_reminders' => 'tratamiento.recordatorios-como-paciente',
        'Tratamiento.view_adherence' => 'tratamiento.adherencia-resumen-staff',
        'CarePack.pre_consultation' => 'care-packs.asistencia-pre-consulta-flow',
        'Persona.link_minor' => 'personas.vincular-menor-flow',
        'Persona.designate_representative' => 'personas.designar-representante-flow',
        'DataAccess.edit' => 'data-access.editar',
        'DataAccess.info' => 'data-access.info',
        'DataAccess.list' => 'data-access.listar',
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
        foreach (self::LEGACY_TO_INTENTS as $legacy => $intentIds) {
            if (!is_array($intentIds)) {
                $intentIds = [$intentIds];
            }
            foreach ($intentIds as $intentId) {
                $this->ensurePermission($authItem, $intentId, $now);
                $this->migrateRoleGrants($childTable, $legacy, $intentId);
            }
            $this->migratePermissionChildren($childTable, $legacy, (string) $intentIds[0]);
        }
    }

    public function safeDown()
    {
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

    private function migrateRoleGrants(string $childTable, string $legacy, string $intentId): void
    {
        $parents = (new Query())
            ->select('parent')
            ->from($childTable)
            ->where(['child' => $legacy])
            ->column($this->db);

        foreach ($parents as $parent) {
            if (!is_string($parent) || $parent === '') {
                continue;
            }
            if ((new Query())->from($childTable)->where(['parent' => $parent, 'child' => $intentId])->exists($this->db)) {
                continue;
            }
            $this->db->createCommand()->insert($childTable, [
                'parent' => $parent,
                'child' => $intentId,
            ])->execute();
        }
    }

    private function migratePermissionChildren(string $childTable, string $legacy, string $intentId): void
    {
        $children = (new Query())
            ->select('child')
            ->from($childTable)
            ->where(['parent' => $legacy])
            ->column($this->db);

        foreach ($children as $child) {
            if (!is_string($child) || $child === '') {
                continue;
            }
            if ((new Query())->from($childTable)->where(['parent' => $intentId, 'child' => $child])->exists($this->db)) {
                continue;
            }
            $this->db->createCommand()->insert($childTable, [
                'parent' => $intentId,
                'child' => $child,
            ])->execute();
        }
    }
}
