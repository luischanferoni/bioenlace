<?php

use yii\db\Migration;

/**
 * Reemplaza `view_consulta_diagnostico` por `view_encounter_diagnostico`.
 *
 * - Entornos con legacy: join `diagnostico_consultas` + `encounter`.
 * - Greenfield (post-drop legacy): join `clinical_condition` + `encounter` con columnas compatibles.
 */
class m260526_130001_view_encounter_diagnostico extends Migration
{
    public function safeUp()
    {
        $this->execute('DROP VIEW IF EXISTS view_consulta_diagnostico');
        $this->execute('DROP VIEW IF EXISTS view_encounter_diagnostico');

        if ($this->tableExists('{{%diagnostico_consultas}}')) {
            $this->createViewFromLegacyDiagnosticoConsultas();
            echo "    > view_encounter_diagnostico creada desde diagnostico_consultas (legacy).\n";

            return;
        }

        if ($this->tableExists('{{%clinical_condition}}') && $this->tableExists('{{%encounter}}')) {
            $this->createViewFromClinicalCondition();
            echo "    > view_encounter_diagnostico creada desde clinical_condition (FHIR).\n";

            return;
        }

        echo "    > m260526_130001: omitida — sin diagnostico_consultas ni clinical_condition.\n";
    }

    public function safeDown()
    {
        $this->execute('DROP VIEW IF EXISTS view_encounter_diagnostico');
        echo "    > m260526_130001: safeDown no recrea view_consulta_diagnostico (legacy).\n";

        return true;
    }

    private function tableExists(string $table): bool
    {
        return $this->db->schema->getTableSchema($table, true) !== null;
    }

    private function createViewFromLegacyDiagnosticoConsultas(): void
    {
        $dc = $this->rawTable('{{%diagnostico_consultas}}');
        $enc = $this->rawTable('{{%encounter}}');
        $parentClassCase = $this->parentClassCaseSql('enc');

        $sql = <<<SQL
CREATE VIEW view_encounter_diagnostico AS
SELECT
    dc.*,
    enc.subject_persona_id AS id_persona,
    enc.parent_id AS c_parent_id,
    {$parentClassCase} AS c_parent_class,
    enc.created_at AS c_created_at
FROM {$dc} dc
INNER JOIN {$enc} enc
    ON enc.id = dc.id_consulta
    AND enc.deleted_at IS NULL
SQL;

        $this->execute($sql);
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
