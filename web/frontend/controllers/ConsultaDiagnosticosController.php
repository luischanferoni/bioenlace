<?php

namespace frontend\controllers;

use common\models\ConsultaDerivaciones;

use frontend\controllers\DefaultController;

use Yii;
use yii\web\Controller;
use yii\helpers\ArrayHelper;
use yii\base\Exception;

use frontend\components\SisseHtmlHelpers;

use common\models\Consulta;
use common\models\Turno;
use common\models\DiagnosticoConsulta;
use common\models\FormularioDinamico;
use common\models\snomed\SnomedHallazgos;
use common\models\DiagnosticoConsultaRepository as DCRepo;
use common\models\DiagnosticoPrevio;
use common\models\form\AMBDiagnosticoForm;
use common\models\form\IMPDiagnosticoForm;

/**
 * ConsultasController implements the CRUD actions for Consulta model.
 */
class ConsultaDiagnosticosController extends DefaultController
{
    use \frontend\controllers\traits\ConsultaTrait;

    public function createCore($consulta)
    {
        $form_class = AMBDiagnosticoForm::class;
        if($consulta->esInternacion()) {
            $form_class = IMPDiagnosticoForm::class;
        }
        $form_model = new $form_class();
        $form_model->prepareForm($consulta);
        $min_diag = $form_model->minimosDiagnosticosRequeridos();
        Yii::debug("ALEX: Min DIAG. value:".$min_diag);

        if (Yii::$app->request->post()) {
            $form_model->loadFomPost();
            $valid = $form_model->validatePost();
            
            if($valid) {
              $transaction = \Yii::$app->db->beginTransaction();
              try {
                $form_model->processPost();
                $transaction->commit();
                
                $response = [
                  'success' => true,
                  'msg' => 'Los diagnosticos fueron cargados exitosamente.',
                  'url_siguiente' => $consulta->urlSiguiente
                  ];
                return $response;

              } catch (Exception $e) {
                Yii::error($e->getMessage());
                $transaction->rollBack();
              }
            }
            else {
                # El form tiene errores
                if($form_model->hasDiagnosticosNuevosSinGuardar()) {
                    # Fijo el minimo de childs, para que el dynamic form
                    # no borre los nuevos!
                    $min_diag = 1;
                }
            }
        }
        
        // Dynamic form requiere si o si una fila para renderizar
        // su plantilla (WTF!)
        if (!$form_model->diagnosticos) {
            $form_model->diagnosticos = [ $form_model->getDiagnosticoTemplate() ];            
        }

        $context = [
            'modelConsulta' => $consulta,
            'modelosConsultaDiagnosticos' => $form_model->diagnosticos,
            'clinical_statuses_for_new' => 
                DCRepo::getClinicalStatusForNew(),
            'verification_statuses_for_new' =>
                DCRepo::getVerificationStatusForNew(),
            'clinical_statuses_for_prev' => 
                DCRepo::getClinicalStatusForPrev(),
            'verification_statuses_for_prev' =>
                DCRepo::getVerificationStatusForPrev(),
            'diagnosticos_previos' => $form_model->diag_previos,
            'form_model' => $form_model,
            'min_diag' => $min_diag,
          ];
        return $this->renderAjax('../consultas/v2/_form_diagnosticos', $context);
    }


}
