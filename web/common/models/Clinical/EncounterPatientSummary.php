<?php

namespace common\models\Clinical;

use yii\db\ActiveRecord;

/**
 * Snapshot publicado del resumen de atención para el paciente.
 *
 * @property int $id
 * @property int $encounter_id
 * @property int $subject_persona_id
 * @property string|null $narrative_text
 * @property string|null $summary_json
 * @property string $published_at
 * @property int $version
 * @property string $created_at
 * @property string $updated_at
 */
class EncounterPatientSummary extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%encounter_patient_summary}}';
    }

    public function rules(): array
    {
        return [
            [['encounter_id', 'subject_persona_id', 'published_at', 'created_at', 'updated_at'], 'required'],
            [['encounter_id', 'subject_persona_id', 'version'], 'integer'],
            [['narrative_text', 'summary_json'], 'string'],
            [['published_at', 'created_at', 'updated_at'], 'safe'],
        ];
    }

    public function getEncounter(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Encounter::class, ['id' => 'encounter_id']);
    }
}
