<?php

namespace common\models\Clinical;

use yii\db\ActiveRecord;

/**
 * Event trail del pipeline de captura clínica (admin / superadmin).
 *
 * @property int $id
 * @property int $capture_id
 * @property int|null $encounter_id
 * @property int|null $actor_user_id
 * @property string $event_type
 * @property string|null $meta_json
 * @property string $created_at
 *
 * @see web/docs/plans/auditoria-captura-clinica/design.md
 */
class EncounterCaptureAudit extends ActiveRecord
{
    public const EVENT_UPLOADED = 'UPLOADED';
    public const EVENT_STT_OK = 'STT_OK';
    public const EVENT_STT_FAILED = 'STT_FAILED';
    public const EVENT_ANALYZED = 'ANALYZED';
    public const EVENT_ANALYSIS_FAILED = 'ANALYSIS_FAILED';
    public const EVENT_RESOLUTIONS_APPLIED = 'RESOLUTIONS_APPLIED';
    public const EVENT_SAVED = 'SAVED';
    public const EVENT_SAVE_FAILED = 'SAVE_FAILED';
    public const EVENT_DISCARDED = 'DISCARDED';

    public static function tableName(): string
    {
        return '{{%encounter_capture_audit}}';
    }

    /**
     * @return list<string>
     */
    public static function eventTypeValues(): array
    {
        return [
            self::EVENT_UPLOADED,
            self::EVENT_STT_OK,
            self::EVENT_STT_FAILED,
            self::EVENT_ANALYZED,
            self::EVENT_ANALYSIS_FAILED,
            self::EVENT_RESOLUTIONS_APPLIED,
            self::EVENT_SAVED,
            self::EVENT_SAVE_FAILED,
            self::EVENT_DISCARDED,
        ];
    }

    public function rules(): array
    {
        return [
            [['capture_id', 'event_type', 'created_at'], 'required'],
            [['capture_id', 'encounter_id', 'actor_user_id'], 'integer'],
            [['event_type'], 'in', 'range' => self::eventTypeValues()],
            [['meta_json'], 'string'],
            [['created_at'], 'safe'],
        ];
    }

    /**
     * @param array<string, mixed>|null $meta
     */
    public static function registrar(
        int $captureId,
        string $eventType,
        ?array $meta = null,
        ?int $actorUserId = null,
        ?int $encounterId = null
    ): void {
        $row = new self();
        $row->capture_id = $captureId;
        $row->event_type = $eventType;
        $row->actor_user_id = $actorUserId;
        $row->encounter_id = $encounterId;
        $row->meta_json = $meta !== null ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null;
        $row->created_at = date('Y-m-d H:i:s');
        $row->save(false);
    }

    /**
     * @return array<string, mixed>
     */
    public function getMeta(): array
    {
        if (!is_string($this->meta_json) || trim($this->meta_json) === '') {
            return [];
        }
        $decoded = json_decode($this->meta_json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
