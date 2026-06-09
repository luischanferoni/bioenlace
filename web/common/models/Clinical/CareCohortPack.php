<?php

namespace common\models\Clinical;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property string $pack_type
 * @property string $cohort_key
 * @property string|null $cohort_profile_json
 * @property string $content_json
 * @property string $ia_context
 * @property string $source
 * @property string $generated_at
 * @property string $expires_at
 * @property string $created_at
 * @property string $updated_at
 */
class CareCohortPack extends ActiveRecord
{
    public const SOURCE_SYNC = 'sync';
    public const SOURCE_VERTEX_BATCH = 'vertex_batch';

    public static function tableName(): string
    {
        return '{{%care_cohort_pack}}';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getContentArray(): ?array
    {
        if ($this->content_json === '' || $this->content_json === null) {
            return null;
        }
        $decoded = json_decode($this->content_json, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getProfileArray(): ?array
    {
        if ($this->cohort_profile_json === '' || $this->cohort_profile_json === null) {
            return null;
        }
        $decoded = json_decode($this->cohort_profile_json, true);

        return is_array($decoded) ? $decoded : null;
    }

    public function isExpired(): bool
    {
        return strtotime($this->expires_at) < time();
    }
}
