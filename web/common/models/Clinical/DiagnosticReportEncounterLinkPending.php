<?php

namespace common\models\Clinical;

use yii\db\ActiveRecord;

/**
 * Informe de lab pendiente de vincular a encounter (ambigüedad agente E01).
 *
 * @property int $diagnostic_report_id
 * @property string $candidates_json
 * @property string $created_at
 */
class DiagnosticReportEncounterLinkPending extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%diagnostic_report_encounter_link_pending}}';
    }

    public function rules(): array
    {
        return [
            [['diagnostic_report_id', 'candidates_json', 'created_at'], 'required'],
            [['diagnostic_report_id'], 'integer'],
            [['candidates_json'], 'string'],
        ];
    }

    public function getDiagnosticReport(): \yii\db\ActiveQuery
    {
        return $this->hasOne(DiagnosticReport::class, ['id' => 'diagnostic_report_id']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function candidatesList(): array
    {
        $raw = json_decode((string) $this->candidates_json, true);

        return is_array($raw) ? $raw : [];
    }
}
