<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "categorias_practicas".
 *
 * @property integer $id_categoria
 * @property string $nombre
 *
 * @property Practicas[] $practicas
 */
class CategoriasPractica extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'categorias_practicas';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id_categoria'], 'required'],
            [['id_categoria'], 'integer'],
            [['nombre'], 'string', 'max' => 50]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_categoria' => 'Id Categoria',
            'nombre' => 'Nombre',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getPracticas()
    {
        return $this->hasMany(Practicas::className(), ['id_categoria' => 'id_categoria']);
    }
}
