<?php

namespace common\models\Clinical;

use yii\db\ActiveRecord;

/**
 * Job de export FHIR de un encounter hacia red / gobierno.
 *
 * @property int $id
 * @property int $encounter_id
 * @property int $subject_persona_id
 * @property int|null $efector_id
 * @property string $exchange_profile
 * @property string $connector_key
 * @property string $estado
 * @property string $run_at
 * @property string|null $bundle_hash
 * @property string|null $bundle_json
 * @property string|null $external_id
 * @property string|null $ultimo_error
 * @property int $intentos
 * @property string|null $sent_at
 * @property string $created_at
 * @property string $updated_at
 */
class ClinicalHistoryOutboundJob extends ActiveRecord
{
    public const ESTADO_PENDIENTE = 'PENDIENTE';
    public const ESTADO_PROCESANDO = 'PROCESANDO';
    public const ESTADO_ENVIADO = 'ENVIADO';
    public const ESTADO_OMITIDO = 'OMITIDO';
    public const ESTADO_FALLIDO = 'FALLIDO';
    public const ESTADO_MUERTO = 'MUERTO';

    public const PROFILE_ENCOUNTER_DOCUMENT_V1 = 'encounter-document-v1';

    public static function tableName(): string
    {
        return '{{%clinical_history_outbound_job}}';
    }

    public function rules(): array
    {
        return [
            [['encounter_id', 'subject_persona_id', 'exchange_profile', 'connector_key', 'estado', 'run_at'], 'required'],
            [['encounter_id', 'subject_persona_id', 'efector_id', 'intentos'], 'integer'],
            [['bundle_json', 'ultimo_error'], 'string'],
            [['run_at', 'sent_at', 'created_at', 'updated_at'], 'safe'],
            [['exchange_profile', 'connector_key'], 'string', 'max' => 64],
            [['estado'], 'string', 'max' => 20],
            [['bundle_hash'], 'string', 'max' => 64],
            [['external_id'], 'string', 'max' => 128],
        ];
    }
}
