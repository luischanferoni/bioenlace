<?php

use yii\db\Migration;

/**
 * Segundo lote: `id_profesional_efector_servicio` en tablas del inventario RRHH restantes.
 *
 * Heurística habitual cuando solo hay `id_rr_hh`: primer `rrhh_servicio.id` (MIN) del RRHH → PES.legacy.
 */
class m260508_000004_consumidores_pes_lote2 extends Migration
{
    public function safeUp()
    {
        $pes = $this->db->schema->getTableSchema('{{%profesional_efector_servicio}}', true);
        if ($pes === null) {
            return;
        }

        $this->ensureColumn('{{%atenciones_enfermeria}}', 'id_profesional_efector_servicio', 'atenciones_enfermeria');
        $this->ensureColumn('{{%consultas_suministro_medicamento}}', 'id_profesional_efector_servicio', 'cons_sum_med');
        $this->ensureColumn('{{%seg_nivel_internacion}}', 'id_profesional_efector_servicio', 'seg_nivel_internacion');
        $this->ensureColumn('{{%encuesta_parches_mamarios}}', 'id_profesional_efector_servicio', 'encuesta_parches');
        $this->ensureColumn('{{%persona_programa}}', 'id_profesional_efector_servicio', 'persona_programa');
        $this->ensureColumn('{{%persona_programa_diabetes}}', 'id_profesional_efector_servicio', 'persona_prog_diab');
        $this->ensureColumn('{{%dispensa_programa_diabetes}}', 'id_profesional_efector_servicio', 'dispensa_prog_diab');
        $this->ensureColumn('{{%sumar_autofacturacion}}', 'id_profesional_efector_servicio', 'sumar_autofact');
        $this->ensureColumn('{{%abreviaturas_rrhh}}', 'id_profesional_efector_servicio', 'abrev_rrhh');

        $snip = $this->db->schema->getTableSchema('{{%seg_nivel_internacion_practica}}', true);
        if ($snip !== null) {
            $this->ensureColumn('{{%seg_nivel_internacion_practica}}', 'id_profesional_efector_servicio_solicita', 'snip_pes_sol');
            $this->ensureColumn('{{%seg_nivel_internacion_practica}}', 'id_profesional_efector_servicio_realiza', 'snip_pes_rea');
        }

        $pickSql = '(SELECT id_rr_hh, MIN(id) AS id_rs FROM {{%rrhh_servicio}} WHERE deleted_at IS NULL GROUP BY id_rr_hh) pick';

        // --- atenciones_enfermeria ---
        $this->execute(<<<SQL
UPDATE {{%atenciones_enfermeria}} a
INNER JOIN {{%consultas}} c ON c.id_consulta = a.id_consulta
SET a.id_profesional_efector_servicio = c.id_profesional_efector_servicio
WHERE c.id_profesional_efector_servicio IS NOT NULL
  AND a.id_profesional_efector_servicio IS NULL
SQL);
        $this->execute(<<<SQL
UPDATE {{%atenciones_enfermeria}} a
INNER JOIN {{%profesional_efector_servicio}} pes
  ON pes.legacy_rrhh_servicio_id = a.id_rrhh_servicio AND pes.deleted_at IS NULL
SET a.id_profesional_efector_servicio = pes.id
WHERE a.id_rrhh_servicio IS NOT NULL AND a.id_rrhh_servicio <> 0
  AND a.id_profesional_efector_servicio IS NULL
SQL);
        $this->execute(<<<SQL
UPDATE {{%atenciones_enfermeria}} a
INNER JOIN {{%consultas}} c ON c.id_consulta = a.id_consulta
INNER JOIN {{%rrhh_efector}} re
  ON re.id_rr_hh = a.id_rr_hh AND re.id_efector = c.id_efector AND re.deleted_at IS NULL
INNER JOIN {{%profesional_efector_servicio}} pes
  ON pes.id_persona = re.id_persona AND pes.id_efector = c.id_efector AND pes.id_servicio = c.id_servicio AND pes.deleted_at IS NULL
SET a.id_profesional_efector_servicio = pes.id
WHERE a.id_rr_hh IS NOT NULL AND a.id_rr_hh <> 0
  AND c.id_efector IS NOT NULL AND c.id_servicio IS NOT NULL
  AND a.id_profesional_efector_servicio IS NULL
SQL);

        // --- consultas_suministro_medicamento ---
        $this->execute(<<<SQL
UPDATE {{%consultas_suministro_medicamento}} s
INNER JOIN {{%consultas}} c ON c.id_consulta = s.id_consulta
SET s.id_profesional_efector_servicio = c.id_profesional_efector_servicio
WHERE c.id_profesional_efector_servicio IS NOT NULL
  AND s.id_profesional_efector_servicio IS NULL
SQL);
        $this->execute(<<<SQL
UPDATE {{%consultas_suministro_medicamento}} s
INNER JOIN {{%consultas}} c ON c.id_consulta = s.id_consulta
INNER JOIN {{%rrhh_efector}} re
  ON re.id_rr_hh = s.id_rrhh AND re.id_efector = c.id_efector AND re.deleted_at IS NULL
INNER JOIN {{%profesional_efector_servicio}} pes
  ON pes.id_persona = re.id_persona AND pes.id_efector = c.id_efector AND pes.id_servicio = c.id_servicio AND pes.deleted_at IS NULL
SET s.id_profesional_efector_servicio = pes.id
WHERE s.id_rrhh IS NOT NULL AND s.id_rrhh <> 0
  AND c.id_efector IS NOT NULL AND c.id_servicio IS NOT NULL
  AND s.id_profesional_efector_servicio IS NULL
SQL);

        // --- seg_nivel_internacion: id_rrhh = rrhh_servicio.id ---
        $this->execute(<<<SQL
UPDATE {{%seg_nivel_internacion}} i
INNER JOIN {{%profesional_efector_servicio}} pes
  ON pes.legacy_rrhh_servicio_id = i.id_rrhh AND pes.deleted_at IS NULL
SET i.id_profesional_efector_servicio = pes.id
WHERE i.id_rrhh IS NOT NULL AND i.id_rrhh <> 0
  AND i.id_profesional_efector_servicio IS NULL
SQL);

        // --- encuesta_parches_mamarios ---
        $this->execute(<<<SQL
UPDATE {{%encuesta_parches_mamarios}} e
INNER JOIN {{%rrhh_efector}} re
  ON re.id_rr_hh = e.id_rr_hh AND re.id_efector = e.id_efector AND re.deleted_at IS NULL
INNER JOIN $pickSql ON pick.id_rr_hh = re.id_rr_hh
INNER JOIN {{%profesional_efector_servicio}} pes ON pes.legacy_rrhh_servicio_id = pick.id_rs AND pes.deleted_at IS NULL
SET e.id_profesional_efector_servicio = pes.id
WHERE e.id_rr_hh IS NOT NULL AND e.id_rr_hh <> 0
  AND e.id_efector IS NOT NULL
  AND e.id_profesional_efector_servicio IS NULL
SQL);

        // --- persona_programa (solo id_rrhh_efector = id_rr_hh) ---
        $this->execute(<<<SQL
UPDATE {{%persona_programa}} pp
INNER JOIN $pickSql ON pick.id_rr_hh = pp.id_rrhh_efector
INNER JOIN {{%profesional_efector_servicio}} pes ON pes.legacy_rrhh_servicio_id = pick.id_rs AND pes.deleted_at IS NULL
SET pp.id_profesional_efector_servicio = pes.id
WHERE pp.id_rrhh_efector IS NOT NULL AND pp.id_rrhh_efector <> 0
  AND pp.id_profesional_efector_servicio IS NULL
SQL);

        // --- persona_programa_diabetes ---
        $this->execute(<<<SQL
UPDATE {{%persona_programa_diabetes}} ppd
INNER JOIN {{%rrhh_efector}} re
  ON re.id_rr_hh = ppd.id_rrhh_efector AND re.id_efector = ppd.id_efector AND re.deleted_at IS NULL
INNER JOIN $pickSql ON pick.id_rr_hh = re.id_rr_hh
INNER JOIN {{%profesional_efector_servicio}} pes ON pes.legacy_rrhh_servicio_id = pick.id_rs AND pes.deleted_at IS NULL
SET ppd.id_profesional_efector_servicio = pes.id
WHERE ppd.id_rrhh_efector IS NOT NULL AND ppd.id_rrhh_efector <> 0
  AND ppd.id_efector IS NOT NULL
  AND ppd.id_profesional_efector_servicio IS NULL
SQL);

        // --- dispensa_programa_diabetes (efector desde ficha diabetes) ---
        $this->execute(<<<SQL
UPDATE {{%dispensa_programa_diabetes}} d
INNER JOIN {{%persona_programa_diabetes}} ppd ON ppd.id = d.id_persona_programa_diabetes
INNER JOIN {{%rrhh_efector}} re
  ON re.id_rr_hh = d.id_rrhh_efector AND re.id_efector = ppd.id_efector AND re.deleted_at IS NULL
INNER JOIN $pickSql ON pick.id_rr_hh = re.id_rr_hh
INNER JOIN {{%profesional_efector_servicio}} pes ON pes.legacy_rrhh_servicio_id = pick.id_rs AND pes.deleted_at IS NULL
SET d.id_profesional_efector_servicio = pes.id
WHERE d.id_rrhh_efector IS NOT NULL AND d.id_rrhh_efector <> 0
  AND ppd.id_efector IS NOT NULL
  AND d.id_profesional_efector_servicio IS NULL
SQL);

        // --- sumar_autofacturacion ---
        $sf = $this->db->schema->getTableSchema('{{%sumar_autofacturacion}}', true);
        if ($sf !== null) {
            $this->execute(<<<SQL
UPDATE {{%sumar_autofacturacion}} x
INNER JOIN {{%consultas}} c ON c.id_consulta = x.id_consulta
SET x.id_profesional_efector_servicio = c.id_profesional_efector_servicio
WHERE c.id_profesional_efector_servicio IS NOT NULL
  AND x.id_profesional_efector_servicio IS NULL
SQL);
            $this->execute(<<<SQL
UPDATE {{%sumar_autofacturacion}} x
INNER JOIN {{%consultas}} c ON c.id_consulta = x.id_consulta
INNER JOIN {{%rrhh_efector}} re
  ON re.id_rr_hh = x.id_rr_hh AND re.id_efector = c.id_efector AND re.deleted_at IS NULL
INNER JOIN {{%profesional_efector_servicio}} pes
  ON pes.id_persona = re.id_persona AND pes.id_efector = c.id_efector AND pes.id_servicio = c.id_servicio AND pes.deleted_at IS NULL
SET x.id_profesional_efector_servicio = pes.id
WHERE x.id_rr_hh IS NOT NULL AND x.id_rr_hh <> 0
  AND c.id_efector IS NOT NULL AND c.id_servicio IS NOT NULL
  AND x.id_profesional_efector_servicio IS NULL
SQL);
        }

        // --- abreviaturas_rrhh ---
        $this->execute(<<<SQL
UPDATE {{%abreviaturas_rrhh}} ab
INNER JOIN $pickSql ON pick.id_rr_hh = ab.id_rr_hh
INNER JOIN {{%profesional_efector_servicio}} pes ON pes.legacy_rrhh_servicio_id = pick.id_rs AND pes.deleted_at IS NULL
SET ab.id_profesional_efector_servicio = pes.id
WHERE ab.id_rr_hh IS NOT NULL AND ab.id_rr_hh <> 0
  AND ab.id_profesional_efector_servicio IS NULL
SQL);

        // --- seg_nivel_internacion_practica (opcional: no existe en todos los entornos) ---
        if ($snip !== null) {
            $this->execute(<<<SQL
UPDATE {{%seg_nivel_internacion_practica}} p
INNER JOIN $pickSql ON pick.id_rr_hh = p.id_rrhh_solicita
INNER JOIN {{%profesional_efector_servicio}} pes ON pes.legacy_rrhh_servicio_id = pick.id_rs AND pes.deleted_at IS NULL
SET p.id_profesional_efector_servicio_solicita = pes.id
WHERE p.id_rrhh_solicita IS NOT NULL AND p.id_rrhh_solicita <> 0
  AND p.id_profesional_efector_servicio_solicita IS NULL
SQL);
            $this->execute(<<<SQL
UPDATE {{%seg_nivel_internacion_practica}} p
INNER JOIN $pickSql ON pick.id_rr_hh = p.id_rrhh_realiza
INNER JOIN {{%profesional_efector_servicio}} pes ON pes.legacy_rrhh_servicio_id = pick.id_rs AND pes.deleted_at IS NULL
SET p.id_profesional_efector_servicio_realiza = pes.id
WHERE p.id_rrhh_realiza IS NOT NULL AND p.id_rrhh_realiza <> 0
  AND p.id_profesional_efector_servicio_realiza IS NULL
SQL);
        }
    }

    public function safeDown()
    {
        $this->dropPesColumn('{{%atenciones_enfermeria}}', 'id_profesional_efector_servicio', 'atenciones_enfermeria');
        $this->dropPesColumn('{{%consultas_suministro_medicamento}}', 'id_profesional_efector_servicio', 'cons_sum_med');
        $this->dropPesColumn('{{%seg_nivel_internacion}}', 'id_profesional_efector_servicio', 'seg_nivel_internacion');
        $this->dropPesColumn('{{%encuesta_parches_mamarios}}', 'id_profesional_efector_servicio', 'encuesta_parches');
        $this->dropPesColumn('{{%persona_programa}}', 'id_profesional_efector_servicio', 'persona_programa');
        $this->dropPesColumn('{{%persona_programa_diabetes}}', 'id_profesional_efector_servicio', 'persona_prog_diab');
        $this->dropPesColumn('{{%dispensa_programa_diabetes}}', 'id_profesional_efector_servicio', 'dispensa_prog_diab');
        $this->dropPesColumn('{{%sumar_autofacturacion}}', 'id_profesional_efector_servicio', 'sumar_autofact');
        $this->dropPesColumn('{{%abreviaturas_rrhh}}', 'id_profesional_efector_servicio', 'abrev_rrhh');
        $this->dropPesColumn('{{%seg_nivel_internacion_practica}}', 'id_profesional_efector_servicio_solicita', 'snip_pes_sol');
        $this->dropPesColumn('{{%seg_nivel_internacion_practica}}', 'id_profesional_efector_servicio_realiza', 'snip_pes_rea');
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

    private function dropPesColumn(string $table, string $column, string $idxPrefix): void
    {
        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema === null || !isset($schema->columns[$column])) {
            return;
        }
        $this->dropIndex("idx_{$idxPrefix}_{$column}", $table);
        $this->dropColumn($table, $column);
    }
}
