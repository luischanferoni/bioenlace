<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "seg_nivel_internacion_tipo_alta".
 *
 * @property int $id
 * @property string $tipo_alta
 *
 * @property SegNivelInternacion[] $segNivelInternacions
 */
class SegNivelInternacionTipoAlta extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'seg_nivel_internacion_tipo_alta';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['tipo_alta'], 'required'],
            [['tipo_alta'], 'string', 'max' => 80],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'tipo_alta' => 'Tipo Alta',
        ];
    }

    /**
     * Gets query for [[SegNivelInternacions]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getSegNivelInternacions()
    {
        return $this->hasMany(SegNivelInternacion::className(), ['id_tipo_alta' => 'id']);
    }
}
