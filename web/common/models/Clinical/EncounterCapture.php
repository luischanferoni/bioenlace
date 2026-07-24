<?php

namespace common\models\Clinical;

use yii\db\ActiveRecord;

/**
 * Borrador de captura clínica por etapas (audio → STT → análisis → guardado).
 * Procesamiento síncrono: cada request avanza y persiste el checkpoint.
 *
 * @property int $id
 * @property string $client_capture_id
 * @property int $subject_persona_id
 * @property string|null $parent_type
 * @property int|null $parent_id
 * @property int|null $encounter_id
 * @property int $created_by_user_id
 * @property string $stage
 * @property string|null $audio_relative_path
 * @property string|null $audio_mime
 * @property string|null $transcript
 * @property string|null $texto_procesado
 * @property string|null $stt_meta_json
 * @property string|null $datos_extraidos_json
 * @property string|null $analysis_response_json
 * @property string|null $analysis_cache_token
 * @property string|null $staged_item_ids_json
 * @property string|null $last_error
 * @property int $attempts_stt
 * @property int $attempts_analysis
 * @property int $attempts_save
 * @property string $created_at
 * @property string $updated_at
 */
class EncounterCapture extends ActiveRecord
{
    public const STAGE_UPLOADED = 'UPLOADED';
    public const STAGE_STT_FAILED = 'STT_FAILED';
    public const STAGE_TRANSCRIBED = 'TRANSCRIBED';
    public const STAGE_ANALYSIS_FAILED = 'ANALYSIS_FAILED';
    public const STAGE_READY_FOR_REVIEW = 'READY_FOR_REVIEW';
    public const STAGE_SAVE_FAILED = 'SAVE_FAILED';
    public const STAGE_COMPLETED = 'COMPLETED';
    public const STAGE_DISCARDED = 'DISCARDED';

    public static function tableName(): string
    {
        return '{{%encounter_capture}}';
    }

    /**
     * @return list<string>
     */
    public static function stageValues(): array
    {
        return [
            self::STAGE_UPLOADED,
            self::STAGE_STT_FAILED,
            self::STAGE_TRANSCRIBED,
            self::STAGE_ANALYSIS_FAILED,
            self::STAGE_READY_FOR_REVIEW,
            self::STAGE_SAVE_FAILED,
            self::STAGE_COMPLETED,
            self::STAGE_DISCARDED,
        ];
    }

    /**
     * Etapas visibles en listados de trabajo pendiente (cross-device).
     *
     * @return list<string>
     */
    public static function openStageValues(): array
    {
        return [
            self::STAGE_UPLOADED,
            self::STAGE_STT_FAILED,
            self::STAGE_TRANSCRIBED,
            self::STAGE_ANALYSIS_FAILED,
            self::STAGE_READY_FOR_REVIEW,
            self::STAGE_SAVE_FAILED,
        ];
    }

    public function rules(): array
    {
        return [
            [['client_capture_id', 'subject_persona_id', 'created_by_user_id', 'stage', 'created_at', 'updated_at'], 'required'],
            [['subject_persona_id', 'parent_id', 'encounter_id', 'created_by_user_id', 'attempts_stt', 'attempts_analysis', 'attempts_save'], 'integer'],
            [['client_capture_id'], 'string', 'max' => 64],
            [['parent_type'], 'string', 'max' => 32],
            [['stage'], 'in', 'range' => self::stageValues()],
            [['audio_relative_path'], 'string', 'max' => 512],
            [['audio_mime'], 'string', 'max' => 64],
            [['analysis_cache_token'], 'string', 'max' => 64],
            [[
                'transcript',
                'texto_procesado',
                'stt_meta_json',
                'datos_extraidos_json',
                'analysis_response_json',
                'staged_item_ids_json',
                'last_error',
            ], 'string'],
            [['created_at', 'updated_at'], 'safe'],
            [['client_capture_id'], 'unique'],
        ];
    }

    public function isOpen(): bool
    {
        return in_array($this->stage, self::openStageValues(), true);
    }

    public function hasAudio(): bool
    {
        return is_string($this->audio_relative_path) && trim($this->audio_relative_path) !== '';
    }

    public function hasTranscript(): bool
    {
        return is_string($this->transcript) && trim($this->transcript) !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public function getSttMeta(): array
    {
        return $this->decodeJsonMap($this->stt_meta_json);
    }

    /**
     * @param array<string, mixed>|null $meta
     */
    public function setSttMeta(?array $meta): void
    {
        $this->stt_meta_json = $meta === null || $meta === []
            ? null
            : json_encode($meta, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, mixed>
     */
    public function getDatosExtraidos(): array
    {
        return $this->decodeJsonMap($this->datos_extraidos_json);
    }

    /**
     * @param array<string, mixed>|null $data
     */
    public function setDatosExtraidos(?array $data): void
    {
        $this->datos_extraidos_json = $data === null || $data === []
            ? null
            : json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, mixed>
     */
    public function getAnalysisResponse(): array
    {
        return $this->decodeJsonMap($this->analysis_response_json);
    }

    /**
     * @param array<string, mixed>|null $data
     */
    public function setAnalysisResponse(?array $data): void
    {
        $this->analysis_response_json = $data === null || $data === []
            ? null
            : json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return list<string>
     */
    public function getStagedItemIds(): array
    {
        if (!is_string($this->staged_item_ids_json) || $this->staged_item_ids_json === '') {
            return [];
        }
        $decoded = json_decode($this->staged_item_ids_json, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_map('strval', $decoded));
    }

    /**
     * @param list<string>|null $ids
     */
    public function setStagedItemIds(?array $ids): void
    {
        $this->staged_item_ids_json = $ids === null || $ids === []
            ? null
            : json_encode(array_values($ids), JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonMap(?string $raw): array
    {
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
