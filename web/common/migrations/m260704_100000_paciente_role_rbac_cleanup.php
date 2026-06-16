<?php

use yii\db\Migration;

/**
 * Revoca permisos staff heredados o asignados por error al rol paciente (solo app móvil paciente).
 */
class m260704_100000_paciente_role_rbac_cleanup extends Migration
{
    private const ROLE_PACIENTE = 'paciente';

    /** @var list<string> Permisos/intents de operación staff — no aplican al rol paciente. */
    private const REVOKE_FROM_PACIENTE = [
        'GuardiaEpisode.view_board',
        'GuardiaEpisode.triage',
        'urgencias.ver-tablero-guardia',
        'turnos.indicadores-agenda-flow',
        'Turno.view_indicators',
        'Turno.view_occupancy',
        'turnos.consultar-ocupacion-dia-flow',
        'turnos.crear-para-paciente-flow',
        'turnos.crear-sobreturno-flow',
        'turnos.cancelar-para-paciente-flow',
        'tratamiento.adherencia-resumen-staff',
        'Tratamiento.view_adherence',
        'Internacion.create',
        'internacion.ingreso-flow',
        'internacion.mapa-camas-flow',
        'Internacion.view_map',
        'internacion.cambio-cama-flow',
        'internacion.alta-estructurada-flow',
        'Internacion.change_bed',
        'Internacion.discharge',
        'internacion.epicrisis-plantilla-admin',
        'InternacionEpicrisisPlantilla.admin',
        'data-access.editar',
        'data-access.info',
        'data-access.listar',
        'DataAccess.edit',
        'DataAccess.info',
        'DataAccess.list',
        'front_listar_asignaciones_pes',
        'front_listar_mis_turnos',
        'info_listar',
        'profesional-efector-servicio.crear-flow',
        'profesional-agenda.resolver-conflictos-flow',
        'turnos.ver-agenda-dia-profesional-flow',
        'turnos.conflicto-agenda-flow',
        'turnos.no-se-presento-flow',
        'licencia.cargar-como-profesional-flow',
        'licencia.cargar-para-profesional-flow',
        'Licencia.create',
        'ExpedienteLegalGenerar',
    ];

    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($childTable, true) === null) {
            return;
        }

        $this->db->createCommand()->delete($childTable, [
            'parent' => self::ROLE_PACIENTE,
            'child' => self::REVOKE_FROM_PACIENTE,
        ])->execute();
    }

    public function safeDown()
    {
    }
}
