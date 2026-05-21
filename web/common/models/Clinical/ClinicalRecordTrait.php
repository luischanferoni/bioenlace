<?php

namespace common\models\Clinical;

use Yii;
use yii\db\ActiveRecord;

/**
 * Soft-delete y campos de auditoría alineados al resto del monorepo.
 */
trait ClinicalRecordTrait
{
    use \common\traits\SoftDeleteDateTimeTrait;

    public function behaviors(): array
    {
        return [
            'blames' => [
                'class' => 'yii\behaviors\AttributeBehavior',
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['created_by'],
                    ActiveRecord::EVENT_BEFORE_UPDATE => ['updated_by'],
                    ActiveRecord::EVENT_BEFORE_DELETE => ['deleted_by'],
                ],
                'value' => static function () {
                    return Yii::$app->user && !Yii::$app->user->isGuest
                        ? (int) Yii::$app->user->id
                        : null;
                },
            ],
        ];
    }
}
