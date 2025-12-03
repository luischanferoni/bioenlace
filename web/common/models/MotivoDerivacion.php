<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "motivos_derivacion".
 *
 * @property string $id_motivo_derivacion
 * @property string $nombre
 *
 * @property Referencia[] $referencias
 */
class MotivoDerivacion extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'motivos_derivacion';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['nombre'], 'string', 'max' => 60]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_motivo_derivacion' => 'Id Motivo Derivacion',
            'nombre' => 'Nombre',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getReferencias()
    {
        return $this->hasMany(Referencia::className(), ['id_motivo_derivacion' => 'id_motivo_derivacion']);
    }
}
