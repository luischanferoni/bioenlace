<?php

namespace common\models\Platform;

use yii\db\ActiveRecord;

/**
 * Agregado diario de uso de IA por contexto y efector.
 *
 * @property int $id
 * @property string $usage_date
 * @property string $context
 * @property int $id_efector
 * @property int $calls
 * @property int $errors
 * @property int $prompt_tokens
 * @property int $cached_tokens
 * @property int $output_tokens
 * @property int $thoughts_tokens
 * @property int $latency_ms_sum
 * @property string $cost_usd
 * @property string $created_at
 * @property string|null $updated_at
 */
class AIUsageDaily extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%ai_usage_daily}}';
    }
}
