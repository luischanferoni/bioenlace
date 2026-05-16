<?php

use yii\db\Migration;

/**
 * Agenda por versiones: intervalo fijo (15/20/30/45/60), vigente_desde y conflictos de turnos.
 */
class m260516_000001_agenda_intervalo_versioning extends Migration
{
    public function safeUp()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        if ($this->db->schema->getTableSchema('{{%profesional_efector_servicio_agenda_version}}', true) === null) {
            $this->createTable('{{%profesional_efector_servicio_agenda_version}}', [
                'id' => $this->primaryKey(),
                'id_profesional_efector_servicio' => $this->integer()->notNull(),
                'id_efector' => $this->integer()->notNull(),
                'vigente_desde' => $this->date()->notNull(),
                'intervalo_minutos' => $this->smallInteger()->notNull(),
                'formas_atencion' => $this->string(32)->notNull(),
                'cupo_pacientes' => $this->integer()->null(),
                'acepta_consultas_online' => $this->boolean()->notNull()->defaultValue(false),
                'lunes_2' => $this->text()->null(),
                'martes_2' => $this->text()->null(),
                'miercoles_2' => $this->text()->null(),
                'jueves_2' => $this->text()->null(),
                'viernes_2' => $this->text()->null(),
                'sabado_2' => $this->text()->null(),
                'domingo_2' => $this->text()->null(),
                'created_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
                'created_by' => $this->integer()->null(),
            ], $tableOptions);

            $this->createIndex(
                'ux_pes_agenda_version_pes_vigente',
                '{{%profesional_efector_servicio_agenda_version}}',
                ['id_profesional_efector_servicio', 'vigente_desde'],
                true
            );
            $this->createIndex(
                'ix_pes_agenda_version_pes_vigente_desde',
                '{{%profesional_efector_servicio_agenda_version}}',
                ['id_profesional_efector_servicio', 'vigente_desde']
            );

            $this->addForeignKey(
                'fk_pes_agenda_version_pes',
                '{{%profesional_efector_servicio_agenda_version}}',
                'id_profesional_efector_servicio',
                '{{%profesional_efector_servicio}}',
                'id',
                'RESTRICT',
                'RESTRICT'
            );
            $this->addForeignKey(
                'fk_pes_agenda_version_efector',
                '{{%profesional_efector_servicio_agenda_version}}',
                'id_efector',
                '{{%efectores}}',
                'id_efector',
                'RESTRICT',
                'RESTRICT'
            );
        }

        $agenda = $this->db->schema->getTableSchema('{{%profesional_efector_servicio_agenda}}', true);
        if ($agenda !== null && !isset($agenda->columns['intervalo_minutos'])) {
            $this->addColumn(
                '{{%profesional_efector_servicio_agenda}}',
                'intervalo_minutos',
                $this->smallInteger()->null()->after('duracion_slot_minutos')
            );
        }

        $turnos = $this->db->schema->getTableSchema('{{%turnos}}', true);
        if ($turnos !== null) {
            if (!isset($turnos->columns['id_agenda_version'])) {
                $this->addColumn('{{%turnos}}', 'id_agenda_version', $this->integer()->null());
            }
            if (!isset($turnos->columns['intervalo_minutos_reserva'])) {
                $this->addColumn('{{%turnos}}', 'intervalo_minutos_reserva', $this->smallInteger()->null());
            }
            if (!isset($turnos->columns['hora_fin'])) {
                $this->addColumn('{{%turnos}}', 'hora_fin', $this->time()->null());
            }
        }

        if ($this->db->schema->getTableSchema('{{%turno_agenda_conflicto}}', true) === null) {
            $this->createTable('{{%turno_agenda_conflicto}}', [
                'id' => $this->primaryKey(),
                'id_turno' => $this->integer()->notNull(),
                'id_agenda_version' => $this->integer()->notNull(),
                'estado' => $this->string(24)->notNull()->defaultValue('pendiente'),
                'opcion_hora_antes' => $this->time()->null(),
                'opcion_hora_despues' => $this->time()->null(),
                'hora_elegida' => $this->time()->null(),
                'created_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
                'updated_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'),
            ], $tableOptions);

            $this->createIndex('ux_turno_agenda_conflicto_turno_version', '{{%turno_agenda_conflicto}}', ['id_turno', 'id_agenda_version'], true);
            $this->createIndex('ix_turno_agenda_conflicto_estado', '{{%turno_agenda_conflicto}}', ['estado']);

            $this->addForeignKey(
                'fk_turno_agenda_conflicto_turno',
                '{{%turno_agenda_conflicto}}',
                'id_turno',
                '{{%turnos}}',
                'id_turnos',
                'CASCADE',
                'RESTRICT'
            );
            $this->addForeignKey(
                'fk_turno_agenda_conflicto_version',
                '{{%turno_agenda_conflicto}}',
                'id_agenda_version',
                '{{%profesional_efector_servicio_agenda_version}}',
                'id',
                'RESTRICT',
                'RESTRICT'
            );
        }

        $this->backfillAgendaVersions();
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk_turno_agenda_conflicto_version', '{{%turno_agenda_conflicto}}');
        $this->dropForeignKey('fk_turno_agenda_conflicto_turno', '{{%turno_agenda_conflicto}}');
        $this->dropTable('{{%turno_agenda_conflicto}}');

        $turnos = $this->db->schema->getTableSchema('{{%turnos}}', true);
        if ($turnos !== null) {
            if (isset($turnos->columns['hora_fin'])) {
                $this->dropColumn('{{%turnos}}', 'hora_fin');
            }
            if (isset($turnos->columns['intervalo_minutos_reserva'])) {
                $this->dropColumn('{{%turnos}}', 'intervalo_minutos_reserva');
            }
            if (isset($turnos->columns['id_agenda_version'])) {
                $this->dropColumn('{{%turnos}}', 'id_agenda_version');
            }
        }

        $agenda = $this->db->schema->getTableSchema('{{%profesional_efector_servicio_agenda}}', true);
        if ($agenda !== null && isset($agenda->columns['intervalo_minutos'])) {
            $this->dropColumn('{{%profesional_efector_servicio_agenda}}', 'intervalo_minutos');
        }

        $this->dropForeignKey('fk_pes_agenda_version_efector', '{{%profesional_efector_servicio_agenda_version}}');
        $this->dropForeignKey('fk_pes_agenda_version_pes', '{{%profesional_efector_servicio_agenda_version}}');
        $this->dropTable('{{%profesional_efector_servicio_agenda_version}}');
    }

    private function backfillAgendaVersions(): void
    {
        $rows = $this->db->createCommand(
            'SELECT * FROM {{%profesional_efector_servicio_agenda}} WHERE deleted_at IS NULL'
        )->queryAll();
        foreach ($rows as $row) {
            $intervalo = $this->resolveIntervaloFromRow($row);
            $this->update(
                '{{%profesional_efector_servicio_agenda}}',
                ['intervalo_minutos' => $intervalo],
                ['id' => (int) $row['id']]
            );

            $exists = (int) $this->db->createCommand(
                'SELECT COUNT(*) FROM {{%profesional_efector_servicio_agenda_version}}
                 WHERE id_profesional_efector_servicio = :pes AND vigente_desde = :vd',
                [
                    ':pes' => (int) $row['id_profesional_efector_servicio'],
                    ':vd' => '2000-01-01',
                ]
            )->queryScalar();
            if ($exists > 0) {
                continue;
            }

            $this->insert('{{%profesional_efector_servicio_agenda_version}}', [
                'id_profesional_efector_servicio' => (int) $row['id_profesional_efector_servicio'],
                'id_efector' => (int) $row['id_efector'],
                'vigente_desde' => '2000-01-01',
                'intervalo_minutos' => $intervalo,
                'formas_atencion' => (string) $row['formas_atencion'],
                'cupo_pacientes' => $row['cupo_pacientes'],
                'acepta_consultas_online' => (int) ($row['acepta_consultas_online'] ?? 0),
                'lunes_2' => $row['lunes_2'],
                'martes_2' => $row['martes_2'],
                'miercoles_2' => $row['miercoles_2'],
                'jueves_2' => $row['jueves_2'],
                'viernes_2' => $row['viernes_2'],
                'sabado_2' => $row['sabado_2'],
                'domingo_2' => $row['domingo_2'],
            ]);
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function resolveIntervaloFromRow(array $row): int
    {
        $dur = isset($row['duracion_slot_minutos']) ? (int) $row['duracion_slot_minutos'] : 0;
        if (in_array($dur, [15, 20, 30, 45, 60], true)) {
            return $dur;
        }
        $cupo = isset($row['cupo_pacientes']) ? (int) $row['cupo_pacientes'] : 0;
        if ($cupo > 0) {
            $derived = (int) round(60 / $cupo);
            if (in_array($derived, [15, 20, 30, 45, 60], true)) {
                return $derived;
            }
            if ($derived < 15) {
                return 15;
            }
            if ($derived <= 22) {
                return 20;
            }
            if ($derived <= 37) {
                return 30;
            }
            if ($derived <= 52) {
                return 45;
            }

            return 60;
        }

        return 15;
    }
}
