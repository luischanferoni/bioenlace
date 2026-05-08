<?php

use yii\db\Migration;

/**
 * Enlaza turnos con `profesional_efector_servicio` (además de `id_rrhh_servicio_asignado` legado).
 *
 * - Columna nullable: permite convivencia hasta retirar RRHH clásico.
 * - Backfill: JOIN por `profesional_efector_servicio.legacy_rrhh_servicio_id`.
 */
class m260508_000002_turnos_id_profesional_efector_servicio extends Migration
{
    public function safeUp()
    {
        $turnos = $this->db->schema->getTableSchema('{{%turnos}}', true);
        if ($turnos === null) {
            return;
        }
        if (isset($turnos->columns['id_profesional_efector_servicio'])) {
            return;
        }

        $this->addColumn(
            '{{%turnos}}',
            'id_profesional_efector_servicio',
            $this->integer()->null()->after('id_rrhh_servicio_asignado')
        );
        $this->createIndex(
            'idx_turnos_id_profesional_efector_servicio',
            '{{%turnos}}',
            'id_profesional_efector_servicio'
        );

        $pes = $this->db->schema->getTableSchema('{{%profesional_efector_servicio}}', true);
        if ($pes !== null && isset($turnos->columns['id_rrhh_servicio_asignado'])) {
            $this->execute(<<<SQL
UPDATE {{%turnos}} t
INNER JOIN {{%profesional_efector_servicio}} pes
  ON pes.legacy_rrhh_servicio_id = t.id_rrhh_servicio_asignado
 AND pes.deleted_at IS NULL
SET t.id_profesional_efector_servicio = pes.id
WHERE t.id_rrhh_servicio_asignado > 0
  AND t.id_profesional_efector_servicio IS NULL
SQL);
        }
    }

    public function safeDown()
    {
        $turnos = $this->db->schema->getTableSchema('{{%turnos}}', true);
        if ($turnos === null || !isset($turnos->columns['id_profesional_efector_servicio'])) {
            return;
        }
        $this->dropIndex('idx_turnos_id_profesional_efector_servicio', '{{%turnos}}');
        $this->dropColumn('{{%turnos}}', 'id_profesional_efector_servicio');
    }
}
