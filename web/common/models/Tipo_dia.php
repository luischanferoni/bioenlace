<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "tipo_dia".
 *
 * @property string $id_tipo_dia
 * @property string $nombre
 */
class Tipo_dia extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'tipo_dia';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['nombre'], 'string', 'max' => 10]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_tipo_dia' => 'Id Tipo Dia',
            'nombre' => 'Nombre',
        ];
    }
}
