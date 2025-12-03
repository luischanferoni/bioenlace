<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "tension_arterial".
 *
 * @property string $id_tension
 * @property integer $id_persona
 * @property string $fecha
 * @property integer $sistolica
 * @property integer $diastolica
 * @property string $id_consulta
 *
 * @property Consultas $idConsulta
 * @property Personas $idPersona
 */
class TensionArterial extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'tension_arterial';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id_persona', 'id_consulta'], 'required'],
            [['id_persona', 'sistolica', 'diastolica', 'id_consulta'], 'integer'],
            [['fecha'], 'safe']
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_tension' => 'Id Tension',
            'id_persona' => 'Id Persona',
            'fecha' => 'Fecha',
            'sistolica' => 'Sistólica',
            'diastolica' => 'Diastólica',
            'id_consulta' => 'Id Consulta',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getIdConsulta()
    {
        return $this->hasOne(Consultas::className(), ['id_consulta' => 'id_consulta']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getIdPersona()
    {
        return $this->hasOne(Personas::className(), ['id_persona' => 'id_persona']);
    }
    
    public static function getTensionPorConsulta($id_cons)
    {
    
        $query = TensionArterial::findOne(['id_consulta'=>$id_cons]);
        
        return $query;
               
    }
}
