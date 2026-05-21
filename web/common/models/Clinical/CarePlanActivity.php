<?php

namespace common\models\Clinical;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $care_plan_id
 * @property string $kind
 * @property string $resource_type
 * @property int $resource_id
 * @property int $sort_order
 * @property string $status
 */
class CarePlanActivity extends ActiveRecord
{
    use ClinicalRecordTrait;

    public static function tableName(): string
    {
        return 'care_plan_activity';
    }

    public function rules(): array
    {
        return [
            [['care_plan_id', 'kind', 'resource_type', 'resource_id', 'status'], 'required'],
            [['care_plan_id', 'resource_id', 'sort_order'], 'integer'],
            [['kind', 'resource_type'], 'string', 'max' => 64],
            [['status'], 'string', 'max' => 32],
        ];
    }

    public function getCarePlan(): \yii\db\ActiveQuery
    {
        return $this->hasOne(CarePlan::class, ['id' => 'care_plan_id']);
    }
}
