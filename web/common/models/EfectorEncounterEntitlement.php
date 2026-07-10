<?php

namespace common\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * Clases de encounter contratadas por efector.
 *
 * @property int $id
 * @property int $id_efector
 * @property string $encounter_class
 * @property int|null $max_pes
 * @property int $activo
 * @property string $created_at
 * @property string|null $updated_at
 * @property string|null $deleted_at
 */
class EfectorEncounterEntitlement extends ActiveRecord
{
    use \common\traits\SoftDeleteDateTimeTrait;

    public static function tableName()
    {
        return 'efector_encounter_entitlement';
    }

    public function behaviors()
    {
        return [
            'blames' => [
                'class' => 'yii\behaviors\AttributeBehavior',
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['created_by'],
                    ActiveRecord::EVENT_BEFORE_UPDATE => ['updated_by'],
                ],
                'value' => static function () {
                    return Yii::$app->has('user', true) && Yii::$app->user->id
                        ? (int) Yii::$app->user->id
                        : null;
                },
            ],
            'timestamp' => [
                'class' => 'yii\behaviors\TimestampBehavior',
                'value' => static function () {
                    return date('Y-m-d H:i:s');
                },
                'createdAtAttribute' => 'created_at',
                'updatedAtAttribute' => 'updated_at',
            ],
        ];
    }

    public function rules()
    {
        return [
            [['id_efector', 'encounter_class'], 'required'],
            [['id_efector', 'max_pes', 'activo'], 'integer'],
            [['encounter_class'], 'string', 'max' => 10],
            [['created_at', 'updated_at', 'deleted_at'], 'safe'],
            [['activo'], 'default', 'value' => 1],
        ];
    }

    /**
     * @return list<self>
     */
    public static function findActivasPorEfector(int $idEfector): array
    {
        if ($idEfector <= 0) {
            return [];
        }

        /** @var list<self> $rows */
        $rows = static::find()
            ->where([
                'id_efector' => $idEfector,
                'activo' => 1,
                'deleted_at' => null,
            ])
            ->orderBy(['encounter_class' => SORT_ASC])
            ->all();

        return $rows;
    }
}
