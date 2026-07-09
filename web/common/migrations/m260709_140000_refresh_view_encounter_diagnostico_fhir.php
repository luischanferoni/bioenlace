<?php

use yii\db\Migration;

/**
 * Tras {@see m260526_150002_clinical_fhir_drop_legacy_child_tables}, la vista
 * `view_encounter_diagnostico` puede seguir apuntando a `diagnostico_consultas` eliminada.
 * Recrea la vista desde `clinical_condition` + `encounter` cuando el legacy ya no existe.
 */
class m260709_140000_refresh_view_encounter_diagnostico_fhir extends Migration
{
    public function safeUp()
    {
        if ($this->tableExists('{{%diagnostico_consultas}}')) {
            echo "    > m260709_140000: diagnostico_consultas legacy presente; vista sin cambios.\n";

            return;
        }

        if (!$this->tableExists('{{%clinical_condition}}') || !$this->tableExists('{{%encounter}}')) {
            echo "    > m260709_140000: omitida — faltan clinical_condition o encounter.\n";

            return;
        }

        $this->execute('DROP VIEW IF EXISTS view_encounter_diagnostico');
        $this->createViewFromClinicalCondition();
        echo "    > view_encounter_diagnostico recreada desde clinical_condition (FHIR).\n";
    }

    public function safeDown()
    {
        echo "    > m260709_140000: safeDown no restaura vista legacy.\n";

        return true;
    }

    private function tableExists(string $table): bool
    {
        return $this->db->schema->getTableSchema($table, true) !== null;
    }

    private function createViewFromClinicalCondition(): void
    {
        $cc = $this->rawTable('{{%clinical_condition}}');
        $enc = $this->rawTable('{{%encounter}}');
        $parentClassCase = $this->parentClassCaseSql('enc');

        $sql = <<<SQL
CREATE VIEW view_encounter_diagnostico AS
SELECT
    cc.id AS id,
    cc.encounter_id AS id_consulta,
    cc.code AS codigo,
    NULL AS tipo_diagnostico,
    'NO' AS cronico,
    NULL AS root_id,
    cc.clinical_status AS condition_clinical_status,
    cc.verification_status AS condition_verification_status,
    cc.diagnosis_role AS tipo_prestacion,
    NULL AS objeto_prestacion,
    cc.subject_persona_id AS id_persona,
    enc.parent_id AS c_parent_id,
    {$parentClassCase} AS c_parent_class,
    enc.created_at AS c_created_at
FROM {$cc} cc
INNER JOIN {$enc} enc
    ON enc.id = cc.encounter_id
    AND enc.deleted_at IS NULL
WHERE cc.deleted_at IS NULL
SQL;

        $this->execute($sql);
    }

    private function rawTable(string $table): string
    {
        return $this->db->schema->getRawTableName($table);
    }

    private function parentClassCaseSql(string $alias): string
    {
        return <<<SQL
CASE {$alias}.parent_type
    WHEN 'TURNO' THEN '\\\\common\\\\models\\\\Turno'
    WHEN 'DERIVACION' THEN '\\\\common\\\\models\\\\ConsultaDerivaciones'
    WHEN 'INTERNACION' THEN '\\\\common\\\\models\\\\SegNivelInternacion'
    WHEN 'GENERICO_AMB' THEN '\\\\common\\\\models\\\\GenericoAMB'
    WHEN 'GENERICO_EMER' THEN '\\\\common\\\\models\\\\GenericoEMER'
    WHEN 'GUARDIA' THEN '\\\\common\\\\models\\\\Guardia'
    WHEN 'PASE_PREVIO' THEN '\\\\common\\\\models\\\\ServiciosEfector'
    WHEN 'ENCUESTA_PARCHES' THEN '\\\\common\\\\models\\\\EncuestaParchesMamarios'
    WHEN 'CIRUGIA' THEN '\\\\common\\\\models\\\\Cirugia'
    ELSE NULL
END
SQL;
    }
}
