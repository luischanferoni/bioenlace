<?php

namespace common\models\Clinical;

use yii\db\ActiveRecord;

/**
 * Solicitud de exportación de expediente legal (staff).
 *
 * @property int $id
 * @property int $subject_persona_id
 * @property int|null $id_efector
 * @property int $requested_by_user_id
 * @property int|null $requested_by_persona_id
 * @property string $estado
 * @property string|null $file_path
 * @property int|null $file_size
 * @property string|null $ultimo_error
 * @property int $intentos
 * @property string|null $ready_at
 * @property string|null $downloaded_at
 * @property int|null $downloaded_by_user_id
 * @property string $created_at
 * @property string $updated_at
 */
class LegalRecordExportRequest extends ActiveRecord
{
    public const ESTADO_PENDIENTE = 'PENDIENTE';
    public const ESTADO_PROCESANDO = 'PROCESANDO';
    public const ESTADO_LISTO = 'LISTO';
    public const ESTADO_FALLIDO = 'FALLIDO';

    public static function tableName(): string
    {
        return '{{%legal_record_export_request}}';
    }

    public function rules(): array
    {
        return [
            [['subject_persona_id', 'requested_by_user_id', 'created_at', 'updated_at'], 'required'],
            [
                [
                    'subject_persona_id',
                    'id_efector',
                    'requested_by_user_id',
                    'requested_by_persona_id',
                    'file_size',
                    'intentos',
                    'downloaded_by_user_id',
                ],
                'integer',
            ],
            [['file_path'], 'string', 'max' => 512],
            [['ultimo_error'], 'string'],
            [['estado'], 'string', 'max' => 20],
            [['ready_at', 'downloaded_at', 'created_at', 'updated_at'], 'safe'],
        ];
    }
}
