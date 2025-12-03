<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "programas".
 *
 * @property integer $id_programa
 * @property string $nombre
 * @property string $referente
 */
class Programa extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'programas';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['nombre', 'referente'], 'required'],
            [['nombre', 'referente'], 'string', 'max' => 60]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_programa' => 'Id Programa',
            'nombre' => 'Nombre',
            'referente' => 'Referente',
        ];
    }

    public static function obtenerIdPrograma($nombrePrograma){

        $programa = self::find()
        ->where(['LIKE', 'nombre', $nombrePrograma])
        ->one();

        return $programa->id_programa;
    }
}
