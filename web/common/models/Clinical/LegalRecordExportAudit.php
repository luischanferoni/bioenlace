<?php

namespace common\models\Clinical;

use Yii;
use yii\db\ActiveRecord;

/**
 * Auditoría de solicitudes de expediente legal.
 *
 * @property int $id
 * @property int $request_id
 * @property string $event_type
 * @property int|null $id_user
 * @property string|null $meta_json
 * @property string $created_at
 */
class LegalRecordExportAudit extends ActiveRecord
{
    public const EVENT_SOLICITADO = 'SOLICITADO';
    public const EVENT_GENERADO = 'GENERADO';
    public const EVENT_DESCARGADO = 'DESCARGADO';
    public const EVENT_FALLIDO = 'FALLIDO';

    public static function tableName(): string
    {
        return '{{%legal_record_export_audit}}';
    }

    /**
     * @param array<string, mixed> $meta
     */
    public static function registrar(int $requestId, string $eventType, array $meta = []): void
    {
        $row = new static();
        $row->request_id = $requestId;
        $row->event_type = $eventType;
        $row->id_user = Yii::$app->user->id ?? null;
        $row->meta_json = $meta !== [] ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null;
        $row->created_at = date('Y-m-d H:i:s');
        $row->save(false);
    }
}
