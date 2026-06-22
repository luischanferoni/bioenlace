<?php

namespace common\models\Clinical;

use yii\db\ActiveRecord;

/**
 * Auditoría de jobs de export FHIR.
 *
 * @property int $id
 * @property int $job_id
 * @property string $event_type
 * @property string|null $meta_json
 * @property string $created_at
 */
class ClinicalHistoryOutboundAudit extends ActiveRecord
{
    public const EVENT_ENCUEUED = 'encolado';
    public const EVENT_PROCESANDO = 'procesando';
    public const EVENT_ENVIADO = 'enviado';
    public const EVENT_OMITIDO = 'omitido';
    public const EVENT_FALLIDO = 'fallido';
    public const EVENT_MUERTO = 'muerto';

    public static function tableName(): string
    {
        return '{{%clinical_history_outbound_audit}}';
    }

    /**
     * @param array<string, mixed>|null $meta
     */
    public static function registrar(int $jobId, string $eventType, ?array $meta = null): void
    {
        $row = new self();
        $row->job_id = $jobId;
        $row->event_type = $eventType;
        $row->meta_json = $meta !== null ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null;
        $row->created_at = date('Y-m-d H:i:s');
        $row->save(false);
    }
}
