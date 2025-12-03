<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "odonto_consulta_persona".
 *
 * @property int $id_odonto_consulta
 * @property int $id_consulta
 * @property int $id_turno
 * @property int $id_persona
 * @property int $id_nomenclador_odonto
 * @property string $id_odonto_pieza
 * @property string $estado_pieza
 * @property string $observaciones
 */
class OdontoConsultaPersona extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'odonto_consulta_persona';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_consulta', 'id_turno', 'id_persona', 'id_odonto_pieza', 'estado_pieza'], 'required'],
            [['id_consulta', 'id_turno', 'id_persona', 'id_nomenclador_odonto'], 'integer'],
            [['id_odonto_pieza', 'estado_pieza'], 'string', 'max' => 50],
            [['observaciones'], 'string', 'max' => 100],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id_odonto_consulta' => 'Id Odonto Consulta',
            'id_consulta' => 'Id Consulta',
            'id_turno' => 'Id Turno',
            'id_persona' => 'Id Persona',
            'id_nomenclador_odonto' => 'Id Nomenclador Odonto',
            'id_odonto_pieza' => 'Id Odonto Pieza',
            'estado_pieza' => 'Estado Pieza',
            'fecha_alta' => 'Fecha alta en la tabla',
            'fecha_completado' => 'Fecha tratamiento completado',
            'observaciones' => 'Observaciones',
        ];
    }

//Busca consultas anteriores (id_persona)
    public function getOdontoConsultaPorPersona($id_persona)
    {
        $odontoConsulta = OdontoConsultaPersona::findAll(['id_persona'=>$id_persona]);
        ////$odontoConsulta = OdontoConsultaPersona::find()->where([['id_persona'=>$id_persona]])->orderBy(['id_odonto_consulta'=>SORT_DESC])->all(); actualizar con esta consulta
        return $odontoConsulta;
    }

}
