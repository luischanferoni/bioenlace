<?php

namespace common\models\Clinical;

use yii\db\ActiveRecord;

/**
 * Snapshot corto de extracción IA de captura clínica.
 *
 * @property int $id
 * @property string $token
 * @property int|null $subject_persona_id
 * @property string|null $parent_type
 * @property int|null $parent_id
 * @property int|null $encounter_id
 * @property string $texto_hash
 * @property string $datos_extraidos_json
 * @property string $created_at
 */
class EncounterCaptureAnalysis extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'encounter_capture_analysis';
    }

    public function rules(): array
    {
        return [
            [['token', 'texto_hash', 'datos_extraidos_json', 'created_at'], 'required'],
            [['subject_persona_id', 'parent_id', 'encounter_id'], 'integer'],
            [['token', 'texto_hash'], 'string', 'max' => 64],
            [['parent_type'], 'string', 'max' => 32],
            [['datos_extraidos_json'], 'string'],
            [['created_at'], 'safe'],
            [['token'], 'unique'],
        ];
    }
}
