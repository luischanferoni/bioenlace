<?php

namespace common\models\bundles;

use Yii;

class Resource extends Entry
{
    public $resourceType;
    public $id;
    public $meta = ["profile" => ["http://fhir.msal.gob.ar/RDI/StructureDefinition/recetaDigitalRegistroRecetaAR"]];
    
    public $text = ["status" => "additional", "div" => ""];
    public $identifier = [["system" => "http://sisse.msalsgo.gob.ar/", "value" => "7cdea8dc-aaf1-483f-81f3-41619d853c6a"]];

    public $status = "active";

    public $intent = "order";  

    public $insurance = ["reference" => "uri:uuid de coverage"];

    public $dosageInstruction = [["text" => "indicaciones", 
                                    "timing" => ["repeat" => 
                                                    [
                                                        "duration" => "", 
                                                        "durationMax" => "",
                                                        "durationUnit" => "",
                                                        "frequency" => "",
                                                        "period" => "",
                                                        "periodUnit" => ""
                                                    ]
                                                ]
                                ]];
    public $request = ["request" => ["method" => "PUT", "url" => "MedicationRequest?identifier= + uri devuelto por el servidor"]];

}