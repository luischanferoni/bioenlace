<?php

namespace common\models;

use Yii;
use yii\behaviors\AttributeBehavior;
use yii\db\ActiveRecord;
use common\traits\SoftDeleteDateTimeTrait;

/**
 * @property int $id
 * @property int $id_efector
 * @property string $nombre
 * @property string|null $codigo
 * @property bool $activo
 * @property string $created_at
 * @property string $updated_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property string|null $deleted_at
 * @property int|null $deleted_by
 *
 * @property Efector $efector
 * @property Cirugia[] $cirugias
 */
class QuirofanoSala extends ActiveRecord
{
    use SoftDeleteDateTimeTrait;

    public static function tableName()
    {
        return 'quirofano_sala';
    }

    public function behaviors()
    {
        return [
            'blames' => [
                'class' => AttributeBehavior::class,
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['created_by'],
                    ActiveRecord::EVENT_BEFORE_UPDATE => ['updated_by'],
                ],
                'value' => Yii::$app->has('user') && !Yii::$app->user->isGuest ? Yii::$app->user->id : null,
            ],
        ];
    }

    public function rules()
    {
        return [
            [['id_efector', 'nombre'], 'required'],
            [['id_efector', 'created_by', 'updated_by', 'deleted_by'], 'integer'],
            [['activo'], 'boolean'],
            [['nombre'], 'string', 'max' => 120],
            [['codigo'], 'string', 'max' => 32],
            [['id_efector'], 'exist', 'skipOnError' => true, 'targetClass' => Efector::class, 'targetAttribute' => ['id_efector' => 'id_efector']],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'id_efector' => 'Efector',
            'nombre' => 'Nombre',
            'codigo' => 'Código',
            'activo' => 'Activo',
        ];
    }

    public function getEfector()
    {
        return $this->hasOne(Efector::class, ['id_efector' => 'id_efector']);
    }

    public function getCirugias()
    {
        return $this->hasMany(Cirugia::class, ['id_quirofano_sala' => 'id']);
    }
}
