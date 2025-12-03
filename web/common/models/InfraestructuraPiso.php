<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "infraestructura_piso".
 *
 * @property int $id
 * @property int $nro_piso
 * @property string|null $descripcion
 * @property int $id_efector
 *
 * @property InfraestructuraSala[] $infraestructuraSalas
 */
class InfraestructuraPiso extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'infraestructura_piso';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [[ 'nro_piso', 'id_efector'], 'required'],
            [['id', 'nro_piso', 'id_efector'], 'integer'],
            [['descripcion'], 'string', 'max' => 255],
            [['id'], 'unique'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'nro_piso' => 'Nro Piso',
            'descripcion' => 'Descripcion',
            'id_efector' => 'Id Efector',
        ];
    }

    /**
     * Gets query for [[InfraestructuraSalas]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getInfraestructuraSalas()
    {
        return $this->hasMany(InfraestructuraSala::className(), ['id_piso' => 'id']);
    }

    public function pisosPorEfector($id_efector) 
    {
        return InfraestructuraPiso::find()->where('id_efector = '. $id_efector)->all();
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getEfector()
    {
        return $this->hasOne(Efector::className(), ['id_efector' => 'id_efector']);
    }
}
