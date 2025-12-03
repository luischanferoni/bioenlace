<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "antecedentes".
 *
 * @property integer $id_antecedente
 * @property string $nombre
 * @property string $tipo
 * @property string $masculino
 * @property string $femenino
 * @property integer $edad_desde
 * @property integer $edad_hasta
 * @property string $activo
 *
 * @property PersonasAntecedentes[] $personasAntecedentes
 * @property Personas[] $idPersonas
 */
class Antecedente extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'antecedentes';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['masculino', 'femenino', 'activo'], 'string'],
            [['edad_desde', 'edad_hasta'], 'integer'],
            [['nombre'], 'string', 'max' => 200],
            [['tipo'], 'string', 'max' => 8]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_antecedente' => '',
            'nombre' => 'Nombre',
            'tipo' => 'Tipo',
            'masculino' => 'Masculino',
            'femenino' => 'Femenino',
            'edad_desde' => 'Edad Desde',
            'edad_hasta' => 'Edad Hasta',
            'activo' => 'Activo',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getPersonasAntecedentes()
    {
        return $this->hasMany(PersonasAntecedentes::className(), ['id_antecedente' => 'id_antecedente']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getIdPersonas()
    {
        return $this->hasMany(Personas::className(), ['id_persona' => 'id_persona'])->viaTable('personas_antecedentes', ['id_antecedente' => 'id_antecedente']);
    }
    
    public function getAntecedente_personal($edad,$sexo) {
        
        if($sexo=='F'){
         
         $consulta_ant_per= Antecedente::find()->where("femenino='SI' AND tipo='Personal' AND '$edad' >=edad_desde AND '$edad' <=edad_hasta")
                            ->all();
        
        }
        else{
            $consulta_ant_per= Antecedente::find()->where("masculino='SI' AND tipo='Personal' AND '$edad' >=edad_desde AND '$edad' <=edad_hasta")
                            ->all();
        }
        return $consulta_ant_per;
    }
    
    public function getAntecedente_familiar($edad,$sexo) {
        
        if($sexo=='F'){
         
         $consulta_ant_per= Antecedente::find()->where("femenino='SI' AND tipo='Familiar' AND '$edad' >=edad_desde AND '$edad' <=edad_hasta")
                            ->all();
        
        }
        else{
            $consulta_ant_per= Antecedente::find()->where("masculino='SI' AND tipo='Familiar' AND '$edad' >=edad_desde AND '$edad' <=edad_hasta")
                            ->all();
        }
        return $consulta_ant_per;
    }
}
