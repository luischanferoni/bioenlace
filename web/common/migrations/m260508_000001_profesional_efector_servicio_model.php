<?php

use yii\db\Migration;

/**
 * Nuevo modelo de asignación profesional–efector–servicio y agenda por asignación.
 *
 * - `profesional_efector_servicio`: reemplazo conceptual de `rrhh_efector` + `rrhh_servicio`
 *   (manteniendo `id_persona` como base actual; `id_profesional_salud` queda nullable para futura sincronización Nación).
 * - `profesional_efector_servicio_agenda`: agenda por asignación (impedir si servicio.acepta_turnos != 'SI' vía trigger externo).
 * - `profesional_efector_servicio_condicion_laboral`: condición laboral por asignación (histórico).
 *
 * Este cambio crea y backfillea datos desde tablas actuales, sin crear triggers (se aplican manualmente).
 */
class m260508_000001_profesional_efector_servicio_model extends Migration
{
    public function safeUp()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        // 1) profesional_efector_servicio
        if ($this->db->schema->getTableSchema('{{%profesional_efector_servicio}}', true) === null) {
            $this->createTable('{{%profesional_efector_servicio}}', [
                'id' => $this->primaryKey(),
                'id_persona' => $this->integer()->notNull(),
                'id_profesional_salud' => $this->integer()->null(),
                'id_efector' => $this->integer()->notNull(),
                'id_servicio' => $this->integer()->notNull(),

                // Backfill/compat: apunta a `rrhh_servicio.id` cuando proviene del modelo viejo
                'legacy_rrhh_servicio_id' => $this->integer()->null(),

                'created_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
                'updated_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'),
                'deleted_at' => $this->dateTime()->null(),
                'created_by' => $this->integer()->null(),
                'updated_by' => $this->integer()->null(),
                'deleted_by' => $this->integer()->null(),
            ], $tableOptions);

            $this->createIndex(
                'ux_pes_persona_efector_servicio_alive',
                '{{%profesional_efector_servicio}}',
                ['id_persona', 'id_efector', 'id_servicio', 'deleted_at'],
                true
            );
            $this->createIndex(
                'ux_pes_legacy_rrhh_servicio_id',
                '{{%profesional_efector_servicio}}',
                ['legacy_rrhh_servicio_id'],
                true
            );

            $this->addForeignKey('fk_pes_persona', '{{%profesional_efector_servicio}}', 'id_persona', '{{%personas}}', 'id_persona', 'RESTRICT', 'RESTRICT');
            $this->addForeignKey('fk_pes_efector', '{{%profesional_efector_servicio}}', 'id_efector', '{{%efectores}}', 'id_efector', 'RESTRICT', 'RESTRICT');
            $this->addForeignKey('fk_pes_servicio', '{{%profesional_efector_servicio}}', 'id_servicio', '{{%servicios}}', 'id_servicio', 'RESTRICT', 'RESTRICT');
            // `profesional_salud` no tiene PK simple estable (unique compuesta),
            // por lo que no se agrega FK para `id_profesional_salud` en esta etapa.
        }

        // 2) profesional_efector_servicio_agenda
        if ($this->db->schema->getTableSchema('{{%profesional_efector_servicio_agenda}}', true) === null) {
            $this->createTable('{{%profesional_efector_servicio_agenda}}', [
                'id' => $this->primaryKey(),
                'id_profesional_efector_servicio' => $this->integer()->notNull(),
                'id_efector' => $this->integer()->notNull(),

                // Campos de agenda (mirror de agenda_rrhh)
                'formas_atencion' => $this->string(32)->notNull(),
                'cupo_pacientes' => $this->integer()->null(),
                'duracion_slot_minutos' => $this->integer()->null(),
                'acepta_consultas_online' => $this->boolean()->notNull()->defaultValue(false),

                'lunes_2' => $this->text()->null(),
                'martes_2' => $this->text()->null(),
                'miercoles_2' => $this->text()->null(),
                'jueves_2' => $this->text()->null(),
                'viernes_2' => $this->text()->null(),
                'sabado_2' => $this->text()->null(),
                'domingo_2' => $this->text()->null(),

                'created_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
                'updated_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'),
                'deleted_at' => $this->dateTime()->null(),
                'created_by' => $this->integer()->null(),
                'updated_by' => $this->integer()->null(),
                'deleted_by' => $this->integer()->null(),
            ], $tableOptions);

            $this->createIndex(
                'ux_pes_agenda_pes_alive',
                '{{%profesional_efector_servicio_agenda}}',
                ['id_profesional_efector_servicio', 'deleted_at'],
                true
            );

            $this->addForeignKey(
                'fk_pes_agenda_pes',
                '{{%profesional_efector_servicio_agenda}}',
                'id_profesional_efector_servicio',
                '{{%profesional_efector_servicio}}',
                'id',
                'RESTRICT',
                'RESTRICT'
            );
            $this->addForeignKey(
                'fk_pes_agenda_efector',
                '{{%profesional_efector_servicio_agenda}}',
                'id_efector',
                '{{%efectores}}',
                'id_efector',
                'RESTRICT',
                'RESTRICT'
            );
        }

        // 3) profesional_efector_servicio_condicion_laboral (histórico simple)
        if ($this->db->schema->getTableSchema('{{%profesional_efector_servicio_condicion_laboral}}', true) === null) {
            $this->createTable('{{%profesional_efector_servicio_condicion_laboral}}', [
                'id' => $this->primaryKey(),
                'id_profesional_efector_servicio' => $this->integer()->notNull(),
                'id_condicion_laboral' => $this->integer()->notNull(),
                'fecha_inicio' => $this->date()->null(),
                'fecha_fin' => $this->date()->null(),

                'created_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
                'updated_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'),
                'deleted_at' => $this->dateTime()->null(),
                'created_by' => $this->integer()->null(),
                'updated_by' => $this->integer()->null(),
                'deleted_by' => $this->integer()->null(),
            ], $tableOptions);

            $this->createIndex('idx_pes_cl_pes', '{{%profesional_efector_servicio_condicion_laboral}}', 'id_profesional_efector_servicio');
            $this->addForeignKey(
                'fk_pes_cl_pes',
                '{{%profesional_efector_servicio_condicion_laboral}}',
                'id_profesional_efector_servicio',
                '{{%profesional_efector_servicio}}',
                'id',
                'RESTRICT',
                'RESTRICT'
            );
        }

        // 4) Backfill desde modelo actual (si existen tablas legacy)
        $hasRrhhEfector = $this->db->schema->getTableSchema('{{%rrhh_efector}}', true) !== null;
        $hasRrhhServicio = $this->db->schema->getTableSchema('{{%rrhh_servicio}}', true) !== null;
        if ($hasRrhhEfector && $hasRrhhServicio) {
            // Crear PES por cada rrhh_servicio vivo, usando persona+efector desde rrhh_efector.
            // Nota: rrhh_servicio tiene soft delete; filtramos deleted_at IS NULL.
            $this->execute(<<<SQL
INSERT INTO profesional_efector_servicio
  (id_persona, id_efector, id_servicio, legacy_rrhh_servicio_id, created_at, updated_at, deleted_at, created_by, updated_by, deleted_by)
SELECT
  re.id_persona,
  re.id_efector,
  rs.id_servicio,
  rs.id,
  COALESCE(rs.created_at, CURRENT_TIMESTAMP),
  COALESCE(rs.updated_at, CURRENT_TIMESTAMP),
  rs.deleted_at,
  rs.created_by,
  rs.updated_by,
  rs.deleted_by
FROM rrhh_servicio rs
JOIN rrhh_efector re ON re.id_rr_hh = rs.id_rr_hh
WHERE rs.deleted_at IS NULL
  AND re.deleted_at IS NULL
ON DUPLICATE KEY UPDATE
  updated_at = VALUES(updated_at);
SQL);

            // Backfill agenda: agenda_rrhh.id_rrhh_servicio_asignado -> PES.legacy_rrhh_servicio_id
            $hasAgenda = $this->db->schema->getTableSchema('{{%agenda_rrhh}}', true) !== null;
            if ($hasAgenda) {
                $this->execute(<<<SQL
INSERT INTO profesional_efector_servicio_agenda
  (id_profesional_efector_servicio, id_efector, formas_atencion, cupo_pacientes, duracion_slot_minutos, acepta_consultas_online,
   lunes_2, martes_2, miercoles_2, jueves_2, viernes_2, sabado_2, domingo_2,
   created_at, updated_at, deleted_at, created_by, updated_by, deleted_by)
SELECT
  pes.id,
  a.id_efector,
  a.formas_atencion,
  a.cupo_pacientes,
  a.duracion_slot_minutos,
  COALESCE(a.acepta_consultas_online, 0),
  a.lunes_2, a.martes_2, a.miercoles_2, a.jueves_2, a.viernes_2, a.sabado_2, a.domingo_2,
  COALESCE(a.created_at, CURRENT_TIMESTAMP),
  COALESCE(a.updated_at, CURRENT_TIMESTAMP),
  a.deleted_at,
  a.created_by,
  a.updated_by,
  a.deleted_by
FROM agenda_rrhh a
JOIN profesional_efector_servicio pes ON pes.legacy_rrhh_servicio_id = a.id_rrhh_servicio_asignado
WHERE a.deleted_at IS NULL
ON DUPLICATE KEY UPDATE
  updated_at = VALUES(updated_at);
SQL);
            }

            // Backfill condición laboral si existe rrhh_laboral
            $hasLaboral = $this->db->schema->getTableSchema('{{%rrhh_laboral}}', true) !== null;
            if ($hasLaboral) {
                $this->execute(<<<SQL
INSERT INTO profesional_efector_servicio_condicion_laboral
  (id_profesional_efector_servicio, id_condicion_laboral, fecha_inicio, fecha_fin, created_at, updated_at, deleted_at, created_by, updated_by, deleted_by)
SELECT
  pes.id,
  rl.id_condicion_laboral,
  rl.fecha_inicio,
  rl.fecha_fin,
  COALESCE(rl.created_at, CURRENT_TIMESTAMP),
  COALESCE(rl.updated_at, CURRENT_TIMESTAMP),
  rl.deleted_at,
  rl.created_by,
  rl.updated_by,
  rl.deleted_by
FROM rrhh_laboral rl
JOIN rrhh_efector re ON re.id_rr_hh = rl.id_rr_hh
JOIN profesional_efector_servicio pes
  ON pes.id_persona = re.id_persona
 AND pes.id_efector = re.id_efector
WHERE rl.deleted_at IS NULL
  AND re.deleted_at IS NULL;
SQL);
            }
        }
    }

    public function safeDown()
    {
        // No revertimos backfill; solo eliminamos tablas nuevas si existen.
        if ($this->db->schema->getTableSchema('{{%profesional_efector_servicio_condicion_laboral}}', true) !== null) {
            $this->dropTable('{{%profesional_efector_servicio_condicion_laboral}}');
        }
        if ($this->db->schema->getTableSchema('{{%profesional_efector_servicio_agenda}}', true) !== null) {
            $this->dropForeignKey('fk_pes_agenda_pes', '{{%profesional_efector_servicio_agenda}}');
            $this->dropForeignKey('fk_pes_agenda_efector', '{{%profesional_efector_servicio_agenda}}');
            $this->dropTable('{{%profesional_efector_servicio_agenda}}');
        }
        if ($this->db->schema->getTableSchema('{{%profesional_efector_servicio}}', true) !== null) {
            // FKs pueden no existir según entorno, dropeamos condicionalmente.
            foreach (['fk_pes_prof_salud', 'fk_pes_servicio', 'fk_pes_efector', 'fk_pes_persona'] as $fk) {
                try { $this->dropForeignKey($fk, '{{%profesional_efector_servicio}}'); } catch (\Throwable $e) { /* ignore */ }
            }
            $this->dropTable('{{%profesional_efector_servicio}}');
        }
    }
}

