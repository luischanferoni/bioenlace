<?php

namespace frontend\controllers;

use frontend\controllers\DefaultController;

use Yii;
use yii\web\Controller;
use yii\helpers\ArrayHelper;
use yii\web\UploadedFile;
use yii\base\Exception;

use frontend\components\SisseHtmlHelpers;

use common\models\Consulta;
use common\models\Turno;
use common\models\ConsultaPracticas;
use common\models\ConsultaDerivaciones;
use common\models\FormularioDinamico;
use common\models\Servicio;
use common\models\Adjunto;
use common\models\snomed\SnomedProcedimientos;

class ConsultaDerivacionesController extends DefaultController
{
    use \frontend\controllers\traits\ConsultaTrait;
    use \frontend\controllers\traits\AdjuntoTrait;


    public function createCore($modelConsulta)
    {
        $idsBDPrevioPost = [];

        $modelosConsultaDerivacionesSolicitadas = $modelConsulta->derivacionesSolicitadas;
        if (!$modelosConsultaDerivacionesSolicitadas) {
            $derivaciones = ConsultaDerivaciones::getDerivacionesRechazadaPorPersona($modelConsulta->id_consulta,$modelConsulta->id_persona, $modelConsulta->id_efector, $modelConsulta->id_servicio, ConsultaDerivaciones::ESTADO_RECHAZADA);
            if($derivaciones) {
                // Precargo derivaciones rechazadas
                foreach ($derivaciones as $derivacion) {
                    $modelosConsultaDerivacionesSolicitadas[] = $derivacion;
                }
            }else{
                $modelosConsultaDerivacionesSolicitadas = [new ConsultaDerivaciones()];
            }

        } else {
            $idsBDPrevioPost = ArrayHelper::getColumn($modelosConsultaDerivacionesSolicitadas, 'id');
        }

        $serviciosAceptaPracticas = ArrayHelper::map(Servicio::find()->where(['acepta_practicas' => 'SI'])->asArray()->all(), 'id_servicio', 'nombre');

        if (Yii::$app->request->post()) {
            
            $modelosConsultaDerivacionesSolicitadas = FormularioDinamico::createAndLoadMultiple(ConsultaDerivaciones::classname(), 'id', $modelosConsultaDerivacionesSolicitadas);
            
            $idsEnPost = [];

            $transaction = \Yii::$app->db->beginTransaction();

            try {

                $modelConsulta->save();

                foreach ($modelosConsultaDerivacionesSolicitadas as $i => $modelDerivacionSolicitada) {

                    SnomedProcedimientos::crearSiNoExiste($modelDerivacionSolicitada->codigo, Yii::$app->request->post("CustomAttribute")[$i]["termino_procedimiento"]);

                    $modelDerivacionSolicitada->id_consulta_solicitante = $modelConsulta->id_consulta;
                    
                    if (!$modelDerivacionSolicitada->save()) {
                        var_dump($modelDerivacionSolicitada->getErrors());
                        throw new Exception();
                    }

                    $idsEnPost[] = $modelDerivacionSolicitada->id;
                }

                // eliminar los que estaban en la BD y no vienen en el post
                $idsAEliminar = array_diff($idsBDPrevioPost, $idsEnPost);
                // hard delete, hardDeleteGrupo verifica que $codigosAEliminar no sea vacio
                ConsultaDerivaciones::hardDeleteGrupo($modelConsulta->id_consulta, $idsAEliminar);
                
            } catch (\Exception $th) {
                Yii::error($th);    
                if ($th->getMessage() != "") {
                    Yii::error($th->getMessage());
                }

                $transaction->rollBack();

                return $this->renderAjax('../consultas/v2/_form_derivaciones_solicitadas', [
                    'modelConsulta' => $modelConsulta,
                    'modelosConsultaDerivacionesSolicitadas' => $modelosConsultaDerivacionesSolicitadas,
                    'serviciosAceptaPracticas' => $serviciosAceptaPracticas,
                ]);
            }

            $transaction->commit();

            return [
                'success' => true,
                'msg' => 'Los solicitudes de practicas fueron cargadas exitosamente.',
                'url_siguiente' => $modelConsulta->urlSiguiente
            ];
        }

        return $this->renderAjax('../consultas/v2/_form_derivaciones_solicitadas', [
            'modelConsulta' => $modelConsulta,
            'modelosConsultaDerivacionesSolicitadas' => (empty($modelosConsultaDerivacionesSolicitadas)) ? [new ConsultaDerivaciones] : $modelosConsultaDerivacionesSolicitadas,
            'serviciosAceptaPracticas' => $serviciosAceptaPracticas,
        ]);
    }

    public function actionEliminarAdjunto($id)
    {
        $this->eliminarArchivo($id);
    }
}
