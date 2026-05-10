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

}