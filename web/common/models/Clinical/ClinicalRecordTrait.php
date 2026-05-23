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
                    if (!Yii::$app->has('user')) {
                        return null;
                    }
                    $user = Yii::$app->get('user');

                    return $user && !$user->isGuest ? (int) $user->id : null;
                },
            ],
        ];
    }
}
