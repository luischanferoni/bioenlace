<?php

namespace common\models\bundles;

use Yii;

class MedicationRequest extends Resource
{
    // lugar donde se almacena la receta
    public $groupIdentifier = ["system" => "https://repositorio5555.com.ar/fhir", "value" => "valor de la receta en donde se guarda (uuid)"];

    // medicamentos
    public $medicationCodeableConcept = ["coding" => 
                            [
                                ["system" => "http://fhir.msal.gob.ar/RDI/CodeSystem/CSMedicamentosGenericosSCT"],
                                ["code" => ""],
                                ["display" => ""],

                            ],
                            [
                                ["system" => "http://fhir.msal.gob.ar/RDI/CodeSystem/CSMedicamentosComercialesSCT"],
                                ["code" => ""],
                                ["display" => ""],

                            ],                            
                        ];
    public $subject = ["reference" => "uri:uuid de paciente", "display" => "apellido, nombre"]; 
    public $supportingInformation = [["reference" => "uri:uuid de location"]];
    public $authoredOn = "";
    // diagnostico
    public $reasonCode = ["coding" => 
    [
        ["system" => "http://fhir.msal.gob.ar/RDI/CodeSystem/CSMedicamentosGenericosSCT"],
        ["code" => ""],
        ["display" => ""],

    ]];

    public $requester = ["reference" => "uri:uuid de medico", "display" => "apellido, nombre de medico"];

    //
    public $dispenseRequest = ["validityPeriod" => ["start" => "datetime"], "quantity" => ["value" => "cantidad del medicamento"]];

}