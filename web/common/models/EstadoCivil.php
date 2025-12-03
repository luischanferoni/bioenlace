<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "estado_civil".
 *
 * @property string $id_estado_civil
 * @property string $nombre
 *
 * @property Personas[] $personas
 */
class EstadoCivil extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'estado_civil';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id_estado_civil'], 'required'],
            [['id_estado_civil'], 'integer'],
            [['nombre'], 'string', 'max' => 15]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_estado_civil' => 'Id Estado Civil',
            'nombre' => 'Nombre',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getPersonas()
    {
        return $this->hasMany(Personas::className(), ['id_estado_civil' => 'id_estado_civil']);
    }
    
    public static function getListaEstadosCiviles()
    {
       $estados_civiles = static::find()->indexBy('id_estado_civil')->asArray()->all(); 
       return \yii\helpers\ArrayHelper::map($estados_civiles, 'id_estado_civil', 'nombre');
    }
}
