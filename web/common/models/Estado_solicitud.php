<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "estado_solicitud".
 *
 * @property string $id_estado
 * @property string $nombre
 */
class Estado_solicitud extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'estado_solicitud';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id_estado'], 'required'],
            [['id_estado'], 'integer'],
            [['nombre'], 'string', 'max' => 10]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_estado' => 'Id Estado',
            'nombre' => 'Nombre',
        ];
    }
}
