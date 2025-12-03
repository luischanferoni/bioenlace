<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "tipo_telefono".
 *
 * @property string $id_tipo_telefono
 * @property string $nombre
 * @property string $categoria
 *
 * @property PersonaTelefono[] $personaTelefonos
 */
class Tipo_telefono extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'tipo_telefono';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            // [['id_tipo_telefono'], 'required'],
            [['id_tipo_telefono'], 'integer'],
            [['nombre', 'categoria'], 'string', 'max' => 20]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_tipo_telefono' => 'Tipo de Telefono',
            'nombre' => 'Nombre',
            'categoria' => 'Categoria',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getPersonaTelefonos()
    {
        return $this->hasMany(PersonaTelefono::className(), ['id_tipo_telefono' => 'id_tipo_telefono']);
    }
    
     public static function getListaTiposTelefono()
    {
       $tipos_telefono = static::find()->indexBy('id_tipo_telefono')->asArray()->all(); 
       return \yii\helpers\ArrayHelper::map($tipos_telefono, 'id_tipo_telefono', 'nombre');
    }


     public static function getTiposTelefonoxCategoria($categoria)
    {
       $tipos_telefono = static::find()->where(['categoria' => $categoria])->indexBy('id_tipo_telefono')->asArray()->all(); 
       return \yii\helpers\ArrayHelper::map($tipos_telefono, 'id_tipo_telefono', 'nombre');
    }

     public static function getTipoTelefono($id_tipo_telefono)
    {
       return $tipo_telefono = static::findOne($id_tipo_telefono); 
       
    }
}
