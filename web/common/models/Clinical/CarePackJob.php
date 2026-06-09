<?php

namespace common\models\Clinical;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property string $pack_type
 * @property string $cohort_key
 * @property string|null $cohort_profile_json
 * @property int|null $encounter_id
 * @property int|null $subject_persona_id
 * @property string $status
 * @property string $mode
 * @property string $run_at
 * @property int $attempts
 * @property int|null $pack_id
 * @property string|null $vertex_batch_job_name
 * @property string|null $vertex_batch_custom_id
 * @property string|null $prompt_snapshot
 * @property string|null $last_error
 * @property string $created_at
 * @property string $updated_at
 */
class CarePackJob extends ActiveRecord
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_VERTEX_SUBMITTED = 'vertex_submitted';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public const MODE_SYNC = 'sync';
    public const MODE_VERTEX_BATCH = 'vertex_batch';

    public static function tableName(): string
    {
        return '{{%care_pack_job}}';
    }
}
