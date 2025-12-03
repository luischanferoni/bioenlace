<?php

namespace common\models\bundles;

use Yii;

class Prescripcion extends \yii\db\ActiveRecord
{
    public $resourceType = "Bundle";
    public $id = "SissePrescripcion";
    public $meta = ["profile" => "http://fhir.msal.gob.ar/RDI/StructureDefinition/recetaDigitalRegistroRecetaAR"];
    public $type = "transaction";
    public $timestamp;
    public $entry = [];

    /*
    public function rules()
    {
        return [
            [['id_rrhh_servicio_asignado'], 'required'],            
            [['id_rrhh_servicio_asignado', 'id_tipo_dia', 'id_efector', 'cupo_pacientes'], 'integer'],
            [['hora_inicio', 'hora_fin', 'fecha_inicio', 'fecha_fin'], 'safe'],
            [['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo', 
            'lunes_2', 'martes_2', 'miercoles_2', 'jueves_2', 'viernes_2', 'sabado_2', 'domingo_2'], 'string'],
            [['fecha_inicio', 'fecha_fin'], 'date', 'format' => 'php:Y-m-d'],
            [['lunes_2', 'martes_2', 'miercoles_2', 'jueves_2', 'viernes_2', 'sabado_2', 'domingo_2'], 'validarAlmenosUno', 'skipOnEmpty' => false],
        ];
    }
    */
}