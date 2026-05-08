<?php

use yii\db\Migration;

/**
 * Enlaza tablas consumidoras con `profesional_efector_servicio` (PES).
 *
 * Backfill:
 * - consultas: desde turno vinculado; si no, tupla id_rr_hh + id_efector + id_servicio vía rrhh_efector + PES.
 * - consultas_derivaciones: misma tupla cuando hay id_rr_hh.
 * - documentos_externos: legacy_rrhh_servicio_id (= rrhh_servicio.id en id_rrhh_servicio).
 * - guardia: legacy por id_rrhh_asignado si coincide con rrhh_servicio.id; si no, id_rr_hh + id_efector + primer rrhh_servicio del RRHH.
 */
class m260508_000003_consumidores_id_profesional_efector_servicio extends Migration
{
    public function safeUp()
    {
        $pes = $this->db->schema->getTableSchema('{{%profesional_efector_servicio}}', true);
        if ($pes === null) {
            return;
        }

        $this->ensureColumn('{{%consultas}}', 'id_profesional_efector_servicio', 'consultas');
        $this->ensureColumn('{{%consultas_derivaciones}}', 'id_profesional_efector_servicio', 'consultas_derivaciones');
        $this->ensureColumn('{{%documentos_externos}}', 'id_profesional_efector_servicio', 'documentos_externos');
        $this->ensureColumn('{{%guardia}}', 'id_profesional_efector_servicio', 'guardia');

        $turnos = $this->db->schema->getTableSchema('{{%turnos}}', true);
        if ($turnos !== null && isset($turnos->columns['id_profesional_efector_servicio'])) {
            $this->execute(<<<SQL
UPDATE {{%consultas}} c
INNER JOIN {{%turnos}} t ON t.id_turnos = c.id_turnos
SET c.id_profesional_efector_servicio = t.id_profesional_efector_servicio
WHERE c.id_turnos IS NOT NULL AND c.id_turnos <> 0
  AND t.id_profesional_efector_servicio IS NOT NULL
  AND c.id_profesional_efector_servicio IS NULL
SQL);
        }

        $re = $this->db->schema->getTableSchema('{{%rrhh_efector}}', true);
        if ($re !== null) {
            $this->execute(<<<SQL
UPDATE {{%consultas}} c
INNER JOIN {{%rrhh_efector}} re
  ON re.id_rr_hh = c.id_rr_hh AND re.id_efector = c.id_efector AND re.deleted_at IS NULL
INNER JOIN {{%profesional_efector_servicio}} pes
  ON pes.id_persona = re.id_persona
 AND pes.id_efector = c.id_efector
 AND pes.id_servicio = c.id_servicio
 AND pes.deleted_at IS NULL
SET c.id_profesional_efector_servicio = pes.id
WHERE c.id_rr_hh IS NOT NULL AND c.id_rr_hh <> 0
  AND c.id_efector IS NOT NULL
  AND c.id_servicio IS NOT NULL
  AND c.id_profesional_efector_servicio IS NULL
SQL);

            $this->execute(<<<SQL
UPDATE {{%consultas_derivaciones}} d
INNER JOIN {{%rrhh_efector}} re
  ON re.id_rr_hh = d.id_rr_hh AND re.id_efector = d.id_efector AND re.deleted_at IS NULL
INNER JOIN {{%profesional_efector_servicio}} pes
  ON pes.id_persona = re.id_persona
 AND pes.id_efector = d.id_efector
 AND pes.id_servicio = d.id_servicio
 AND pes.deleted_at IS NULL
SET d.id_profesional_efector_servicio = pes.id
WHERE d.id_rr_hh IS NOT NULL AND d.id_rr_hh <> 0
  AND d.id_efector IS NOT NULL
  AND d.id_servicio IS NOT NULL
  AND d.id_profesional_efector_servicio IS NULL
SQL);
        }

        $rsTbl = $this->db->schema->getTableSchema('{{%rrhh_servicio}}', true);
        if ($rsTbl !== null) {
            $this->execute(<<<SQL
UPDATE {{%documentos_externos}} x
INNER JOIN {{%profesional_efector_servicio}} pes
  ON pes.legacy_rrhh_servicio_id = x.id_rrhh_servicio AND pes.deleted_at IS NULL
SET x.id_profesional_efector_servicio = pes.id
WHERE x.id_rrhh_servicio IS NOT NULL AND x.id_rrhh_servicio <> 0
  AND x.id_profesional_efector_servicio IS NULL
SQL);

            $this->execute(<<<SQL
UPDATE {{%guardia}} g
INNER JOIN {{%rrhh_servicio}} rs ON rs.id = g.id_rrhh_asignado AND rs.deleted_at IS NULL
INNER JOIN {{%rrhh_efector}} re
  ON re.id_rr_hh = rs.id_rr_hh AND re.id_efector = g.id_efector AND re.deleted_at IS NULL
INNER JOIN {{%profesional_efector_servicio}} pes ON pes.legacy_rrhh_servicio_id = rs.id AND pes.deleted_at IS NULL
SET g.id_profesional_efector_servicio = pes.id
WHERE g.id_rrhh_asignado IS NOT NULL AND g.id_rrhh_asignado <> 0
  AND g.id_efector IS NOT NULL
  AND g.id_profesional_efector_servicio IS NULL
SQL);

            $this->execute(<<<SQL
UPDATE {{%guardia}} g
INNER JOIN {{%rrhh_efector}} re
  ON re.id_rr_hh = g.id_rrhh_asignado AND re.id_efector = g.id_efector AND re.deleted_at IS NULL
INNER JOIN (
  SELECT id_rr_hh, MIN(id) AS id_rs
  FROM {{%rrhh_servicio}}
  WHERE deleted_at IS NULL
  GROUP BY id_rr_hh
) pick ON pick.id_rr_hh = re.id_rr_hh
INNER JOIN {{%profesional_efector_servicio}} pes ON pes.legacy_rrhh_servicio_id = pick.id_rs AND pes.deleted_at IS NULL
SET g.id_profesional_efector_servicio = pes.id
WHERE g.id_rrhh_asignado IS NOT NULL AND g.id_rrhh_asignado <> 0
  AND g.id_efector IS NOT NULL
  AND g.id_profesional_efector_servicio IS NULL
  AND NOT EXISTS (
    SELECT 1 FROM {{%rrhh_servicio}} rs0
    WHERE rs0.id = g.id_rrhh_asignado AND rs0.deleted_at IS NULL
  )
SQL);
        }
    }

    public function safeDown()
    {
        $this->dropColumnIfExists('{{%consultas}}', 'id_profesional_efector_servicio', 'consultas');
        $this->dropColumnIfExists('{{%consultas_derivaciones}}', 'id_profesional_efector_servicio', 'consultas_derivaciones');
        $this->dropColumnIfExists('{{%documentos_externos}}', 'id_profesional_efector_servicio', 'documentos_externos');
        $this->dropColumnIfExists('{{%guardia}}', 'id_profesional_efector_servicio', 'guardia');
    }

    private function ensureColumn(string $table, string $column, string $idxPrefix): void
    {
        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema === null || isset($schema->columns[$column])) {
            return;
        }
        $this->addColumn($table, $column, $this->integer()->null());
        $this->createIndex("idx_{$idxPrefix}_{$column}", $table, $column);
    }

    private function dropColumnIfExists(string $table, string $column, string $idxPrefix): void
    {
        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema === null || !isset($schema->columns[$column])) {
            return;
        }
        $this->dropIndex("idx_{$idxPrefix}_{$column}", $table);
        $this->dropColumn($table, $column);
    }
}
