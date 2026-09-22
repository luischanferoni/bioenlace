<?php

use yii\db\Migration;

/**
 * Motivos de consulta → Condition rol CC; elimina encounter.reason_text.
 * code nullable en clinical_condition (motivos solo texto).
 * Vista diagnósticos excluye diagnosis_role = 'CC'.
 */
class m260917_140000_encounter_reasons_as_condition_cc extends Migration
{
    public function safeUp()
    {
        if ($this->db->schema->getTableSchema('{{%clinical_condition}}', true) !== null) {
            $this->alterColumn('{{%clinical_condition}}', 'code', $this->string(32)->null());
        }

        if ($this->db->schema->getTableSchema('{{%encounter}}', true) === null) {
            return;
        }

        $schema = $this->db->schema->getTableSchema('{{%encounter}}', true);
        if ($schema !== null && isset($schema->columns['reason_text'])) {
            $this->execute(<<<'SQL'
INSERT INTO clinical_condition (
    encounter_id,
    subject_persona_id,
    code,
    code_system,
    display,
    clinical_status,
    verification_status,
    diagnosis_role,
    recorded_date,
    note,
    created_at,
    created_by
)
SELECT
    e.id,
    e.subject_persona_id,
    NULL,
    'https://bioenlace.local/CodeSystem/encounter-reason-text',
    TRIM(e.reason_text),
    'ACTIVE',
    'UNCONFIRMED',
    'CC',
    COALESCE(e.period_start, e.created_at, NOW()),
    NULL,
    NOW(),
    e.created_by
FROM encounter e
WHERE e.deleted_at IS NULL
  AND e.reason_text IS NOT NULL
  AND TRIM(e.reason_text) <> ''
  AND NOT EXISTS (
    SELECT 1 FROM clinical_condition c
    WHERE c.encounter_id = e.id
      AND c.diagnosis_role = 'CC'
      AND c.deleted_at IS NULL
  )
SQL);

            $this->dropColumn('{{%encounter}}', 'reason_text');
        }

        $this->refreshDiagnosticoViewExcludingCc();
    }

    public function safeDown()
    {
        echo "m260917_140000: irreversible (reason_text dropped).\n";

        return false;
    }

    private function refreshDiagnosticoViewExcludingCc(): void
    {
        if ($this->db->schema->getTableSchema('{{%clinical_condition}}', true) === null) {
            return;
        }
        $this->execute('DROP VIEW IF EXISTS view_encounter_diagnostico');
        $this->execute(<<<'SQL'
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
    CASE enc.parent_type
        WHEN 'TURNO' THEN '\\common\\models\\Scheduling\\Turno'
        WHEN 'DERIVACION' THEN '\\common\\models\\Clinical\\ConsultaDerivaciones'
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
