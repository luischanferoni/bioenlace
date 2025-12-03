<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "detalle_practicas".
 *
 * @property integer $id_detalle
 * @property string $codigo_practica
 * @property string $nombre
 *
 * @property Practicas $codigoPractica
 */
class DetallePractica extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'detalle_practicas';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id_detalle'], 'required'],
            [['id_detalle'], 'integer'],
            [['codigo_practica'], 'string', 'max' => 10],
            [['nombre'], 'string', 'max' => 200]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_detalle' => 'Id Detalle',
            'codigo_practica' => 'Codigo Practica',
            'nombre' => 'Nombre',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCodigoPractica()
    {
        return $this->hasOne(Practicas::className(), ['codigo_practica' => 'codigo_practica']);
    }
    
    public function getDetallePracticaConcat() {
        return $this->codigo_practica.' - '.$this->nombre;
    }
}
