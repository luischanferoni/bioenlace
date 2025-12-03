<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "embarazos".
 *
 * @property string $id_embarazo
 * @property integer $id_persona
 * @property string $fum
 * @property string $fpp
 * @property string $fecha_diagnostico
 * @property string $fecha_parto
 * @property string $metodo_anticonceptivo
 * @property string $finalizado
 * @property string $id_consulta
 *
 * @property Consultas $idConsulta
 * @property Personas $idPersona
 */
class ConsultaObstetricia extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'consultas_obstetricia';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id_persona', 'metodo_anticonceptivo', 'id_consulta', 'edad_gestacional'], 'integer'],
            [['fum', 'fpp', 'fecha_diagnostico', 'fecha_parto'], 'safe'],
            [['finalizado', 'metodo_calculo_eg'], 'string'],
            [['id_consulta'], 'required']
        ];
    }

    /**
     * Retorna los campos requeridos en lenguaje natural para prompts de IA
     * @return array
     */
    public function requeridosPrompt()
    {
        return [];
    }


    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_embarazo' => 'Id Embarazo',
            'id_persona' => 'Persona',
            'fum' => 'Fum',
            'fpp' => 'Fpp',
            'fecha_diagnostico' => 'Fecha Diagnostico',
            'fecha_parto' => 'Fecha Parto',
            'metodo_anticonceptivo' => 'Método Anticonceptivo',
            'finalizado' => 'Finalizado',
            'id_consulta' => 'Id Consulta',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getConsulta()
    {
        return $this->hasOne(Consultas::className(), ['id_consulta' => 'id_consulta']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getPersona()
    {
        return $this->hasOne(Personas::className(), ['id_persona' => 'id_persona']);
    }
    
    public function pasarFechaFormatoISO($date)
    {
        list($d,$m,$y) = explode("/", $date);
        return "$y-$m-$d";
    }
    

    

}
