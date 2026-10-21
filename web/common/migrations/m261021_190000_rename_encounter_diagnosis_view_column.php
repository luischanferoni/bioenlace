<?php

use yii\db\Migration;

/**
 * Exposes encounter_id as the canonical identifier in the clinical diagnosis view.
 */
class m261021_190000_rename_encounter_diagnosis_view_column extends Migration
{
    public function safeUp(): void
    {
        $this->refreshView('encounter_id');
    }

    public function safeDown(): void
    {
        $this->refreshView('id_consulta');
    }

    private function refreshView(string $identifierColumn): void
    {
        if ($this->db->schema->getTableSchema('{{%clinical_condition}}', true) === null) {
            return;
        }

        $this->execute('DROP VIEW IF EXISTS view_encounter_diagnostico');
        $this->execute(<<<SQL
CREATE VIEW view_encounter_diagnostico AS
SELECT
    cc.id AS id,
    cc.encounter_id AS {$identifierColumn},
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
    CASE enc.parent_type
        WHEN 'TURNO' THEN '\\common\\models\\Scheduling\\Turno'
        WHEN 'DERIVACION' THEN '\\common\\models\\Clinical\\EncounterReferralRequest'
        WHEN 'INTERNACION' THEN '\\common\\models\\Clinical\\InpatientStay'
        WHEN 'GENERICO_AMB' THEN '\\common\\models\\GenericoAMB'
        WHEN 'GENERICO_EMER' THEN '\\common\\models\\GenericoEMER'
        WHEN 'GUARDIA' THEN '\\common\\models\\Clinical\\Emergency\\EmergencyEpisode'
        WHEN 'PASE_PREVIO' THEN '\\common\\models\\Organization\\ServiciosEfector'
        WHEN 'ENCUESTA_PARCHES' THEN '\\common\\models\\Clinical\\EncuestaParchesMamarios'
        WHEN 'CIRUGIA' THEN '\\common\\models\\Scheduling\\Cirugia'
        ELSE NULL
    END AS c_parent_class,
    enc.created_at AS c_created_at
FROM clinical_condition cc
INNER JOIN encounter enc
    ON enc.id = cc.encounter_id AND enc.deleted_at IS NULL
WHERE cc.deleted_at IS NULL
  AND (cc.diagnosis_role IS NULL OR cc.diagnosis_role = '' OR cc.diagnosis_role IN ('principal', 'secondary'))
SQL);
    }
}
