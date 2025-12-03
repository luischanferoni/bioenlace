<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "practicas".
 *
 * @property integer $id_practica
 * @property string $codigo_practica
 * @property integer $id_categoria
 * @property string $nombre
 * @property string $observacion
 * @property string $arancel
 *
 * @property DetallePracticas[] $detallePracticas
 * @property CategoriasPracticas $idCategoria
 */
class Practica extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'practicas';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id_practica'], 'required'],
            [['id_practica', 'id_categoria'], 'integer'],
            [['observacion'], 'string'],
            [['arancel'], 'number'],
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
            'id_practica' => 'Id Practica',
            'codigo_practica' => 'Codigo Practica',
            'id_categoria' => 'Id Categoria',
            'nombre' => 'Nombre',
            'observacion' => 'Observacion',
            'arancel' => 'Arancel',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getDetallePracticas()
    {
        return $this->hasMany(DetallePracticas::className(), ['codigo_practica' => 'codigo_practica']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getIdCategoria()
    {
        return $this->hasOne(CategoriasPracticas::className(), ['id_categoria' => 'id_categoria']);
    }
}
