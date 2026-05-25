<?php

namespace common\models\Clinical;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $encounter_id
 * @property string $run_at
 * @property string $estado
 * @property int $intentos
 * @property string|null $ultimo_error
 * @property string $created_at
 * @property string $updated_at
 */
class EncounterPatientSummaryPublishQueue extends ActiveRecord
{
    public const ESTADO_PENDIENTE = 'PENDIENTE';
    public const ESTADO_ENVIADA = 'ENVIADA';
    public const ESTADO_CANCELADA = 'CANCELADA';
    public const ESTADO_FALLIDA = 'FALLIDA';

    public static function tableName(): string
    {
        return '{{%encounter_patient_summary_publish_queue}}';
    }

    public function rules(): array
    {
        return [
            [['encounter_id', 'run_at', 'created_at', 'updated_at'], 'required'],
            [['encounter_id', 'intentos'], 'integer'],
            [['run_at', 'created_at', 'updated_at'], 'safe'],
            [['ultimo_error'], 'string'],
            [['estado'], 'string', 'max' => 20],
        ];
    }
}
