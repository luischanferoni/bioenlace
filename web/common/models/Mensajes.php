<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "mensajes".
 *
 * @property integer $id
 * @property integer $id_emisor
 * @property integer $id_receptor
 * @property string $texto
 * @property string $estado
 * @property string $fecha
 */
class Mensajes extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'mensajes';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id_emisor', 'id_receptor', 'fecha'], 'required'],
            [['id_emisor', 'id_receptor'], 'integer'],
            [['texto'], 'string'],
            [['fecha'], 'safe'],
            [['estado'], 'string', 'max' => 255],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'id_emisor' => 'Emisor',
            'id_receptor' => 'Receptor',
            'texto' => 'Texto',
            'estado' => 'Estado',
            'fecha' => 'Fecha',
        ];
    }
}

