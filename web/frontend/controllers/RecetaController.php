<?php

namespace frontend\controllers;

use Yii;
use common\models\Programa;
use common\models\busquedas\ProgramasBusquedas;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;

/**
 * ProgramasController implements the CRUD actions for Programa model.
 */
class RecetaController extends Controller
{
    /**
     * Prueba de recetas.
     * @return mixed
     */
    public function actionCreate()
    {
        $arrayDiagnostico = [
            25064002 => 'cefalea', 
            195658003 => 'aringitis bacteriana aguda', 
            38341003 => 'hipertensión arterial'
        ];

        $arrayPaciente = [0 => 'Mauro Sezella', 1 => 'Luis Chanferoni'];
        $arrayMedicaentosGenericos =                             [
            329653008 => 'ibuprofeno 400 mg por cada comprimido para administración oral',
            374646004 => 'amoxicilina 500 mg por cada comprimido para administración oral',
            318956006 => 'losartán potásico 50 mg por cada comprimido para administración oral',
        ];

        $arrayMedicamentosComerciales = [
            134571000221104 => 'COPIRON 400 MG [IBUPROFENO 400 MG] COMPRIMIDO RECUBIERTO',
            138041000221108 => 'DOLORSYN [IBUPROFENO 400 MG] COMPRIMIDO',
            158001000221105 => 'ALMORSAN [AMOXICILINA TRIHIDRATO 500 MG] COMPRIMIDO RECUBIERTO',
            131931000221108 => 'AMIXEN [AMOXICILINA 500 MG] COMPRIMIDO RECUBIERTO',
            105961000221108 => 'COZAAREX [LOSARTAN POTASICO 50 MG] COMPRIMIDO',
            130911000221107 => 'NITEN [LOSARTAN POTASICO 50 MG] COMPRIMIDO RECUBIERTO',
        ];
        $arrayMedicos = [
            0 => 'Medico 1', 
            1 => 'Medico 2',
        ];

        if(Yii::$app->request->post()) {
            $paciente = Yii::$app->request->post('paciente');
            $diagnostico = Yii::$app->request->post('diagnostico');
            $medicamento_generico = Yii::$app->request->post('medicamento_generico');
            $medicamento_comercial = Yii::$app->request->post('medicamento_comercial');
            $medico = Yii::$app->request->post('medico');


            $prescripcion = new \common\models\bundles\Prescripcion();
            $prescripcion->timestamp = date("c");

            // $medicationRequestUri = uniqid('urn:uuid:');
            $entry = new \common\models\bundles\Entry();
            $entry->fullUrl = "urn:uuid:065c6f50-28de-4224-b950-a764396d4a25";

            $medicationRequest = new \common\models\bundles\MedicationRequest();
            $medicationRequest->id = "receta-sde-01";
            $medicationRequest->text["div"] = "<div xmlns=\"http://www.w3.org/1999/xhtml\">Medicamento: amoxicilina 500 mg por cada comprimido para administración oral</div>";
            // Sistema que genera la prescripcion
            $medicationRequest->identifier["system"] = "http://sisse.msalsgo.gob.ar";
            $medicationRequest->identifier["value"] = "7cdea8dc-aaf1-483f-81f3-41619d853c6a";
            // Medicamentos
            $medicationRequest->medicationCodeableConcept["coding"][0]["code"] = $medicamento_generico;
            $medicationRequest->medicationCodeableConcept["coding"][0]["display"] = $arrayMedicaentosGenericos[$medicamento_generico];
            $medicationRequest->medicationCodeableConcept["coding"][1]["code"] = $medicamento_comercial;
            $medicationRequest->medicationCodeableConcept["coding"][1]["display"] = $arrayMedicamentosComerciales[$medicamento_comercial];
            // Paciente
            $medicationRequest->subject = ["reference" => "urn:uuid:015c6f50-28de-4224-b950-a764396d4a25", "display" => $arrayPaciente[$paciente]];
            $medicationRequest->supportingInformation = [["reference" => "urn:uuid:015c6f50-28de-4224-b950-a764396d4a25"]];
            $medicationRequest->authoredOn = date("c");
            // Prescriptor
            $medicationRequest->requester = ["reference" => "urn:uuid:015c6f50-28de-4224-b950-a764396d4b25", "display" => $arrayMedicos[$medico]];
            // Diagnostico
            $medicationRequest->reasonCode["coding"][0]["system"] = "http://fhir.msal.gob.ar/RDI/CodeSystem/csproblemas-salud";
            $medicationRequest->reasonCode["coding"][0]["code"] = $diagnostico;
            $medicationRequest->reasonCode["coding"][0]["display"] = $arrayDiagnostico[$diagnostico];

            $medicationRequest->groupIdentifier["value"] = "7cdea8dc-81f3-483f-aaf1-41619d853c6e";

            // referencia a la cobertura
            $medicationRequest->insurance["reference"] = "urn:uuid:015c6f50-28de-4224-b950-a764396d4c25";

            //referencia a la dispensa de los medicamentos recetados.
            $medicationRequest->dispenseRequest["validityPeriod"]["start"]= date("c");


            $entry->resource = $medicationRequest;
            $prescripcion->entry[] = $entry;

            $entry = new \common\models\bundles\Entry();
            $medicationRequest = new \common\models\bundles\MedicationRequest();
            $entry->resource = $medicationRequest;
            $prescripcion->entry[] = $entry;
        }
        return $this->render('create');
    }
}