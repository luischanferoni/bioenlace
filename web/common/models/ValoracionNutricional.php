<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "valoracion_nutricional".
 *
 * @property string $id_valoracion
 * @property integer $id_persona
 * @property string $fecha
 * @property string $peso
 * @property string $per_peso
 * @property string $talla
 * @property string $per_talla
 * @property string $perim_cefalico
 * @property string $per_perim_cefalico
 * @property string $imc
 * @property string $per_imc
 * @property string $id_consulta
 *
 * @property Consultas $idConsulta
 * @property Personas $idPersona
 */
class ValoracionNutricional extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'valoracion_nutricional';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id_persona', 'peso', 'talla', 'perim_cefalico', 'id_consulta'], 'integer'],
            [['fecha'], 'safe'],
            [['imc'], 'number'],
            [['id_consulta'], 'required'],
            [['per_peso', 'per_talla', 'per_perim_cefalico', 'per_imc'], 'string', 'max' => 10]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_valoracion' => 'Id Valoracion',
            'id_persona' => 'Id Persona',
            'fecha' => 'Fecha',
            'peso' => 'Peso',
            'per_peso' => 'Per Peso',
            'talla' => 'Talla',
            'per_talla' => 'Per Talla',
            'perim_cefalico' => 'Perimetro Cefalico (PC)',
            'per_perim_cefalico' => 'Percentilo (PC)',
            'imc' => 'Imc',
            'per_imc' => 'Per Imc',
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
    
    //Busca la valoración por consulta y persona
    public static function getValoracionPorConsulta($id_cons)
    {
        //Consulta Valoracion nutricional por persona ordenado por fecha-----------------------------------

//        $query = ValoracionNutricional::find()
//                        ->select([
//                                'valoracion_nutricional.id_valoracion AS id_valoracion', 
//                                'valoracion_nutricional.peso AS  peso',
//                                'valoracion_nutricional.talla AS  talla',
//                                'valoracion_nutricional.perim_cefalico AS perim_cefalico',
//                                'valoracion_nutricional.per_perim_cefalico AS per_perim_cefalico',
//                                'valoracion_nutricional.fecha AS fecha',
//                                ]
//                        )->from('valoracion_nutricional')
//                        ->join('INNER JOIN','turnos', '`valoracion_nutricional`.`id_persona` = `turnos`.`id_persona`')
//                        ->join('INNER JOIN','consultas', '`turnos`.`id_turnos` = `consultas`.`id_turnos`')
//                        //->where(['consultas.id_consulta' => $id_cons])
//                        ->orderBy('fecha DESC')
//                        ->asArray()
//                        ->all();
        //----------------------------------------------------------------------------------------
        $query = ValoracionNutricional::findOne(['id_consulta'=>$id_cons]);
        
        return $query;
               
    }
    
}
