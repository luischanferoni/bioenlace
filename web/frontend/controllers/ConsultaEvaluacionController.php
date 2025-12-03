<?php

namespace frontend\controllers;

use frontend\controllers\DefaultController;

use Yii;
use yii\base\Exception;

use common\models\ConsultaPracticas;
use common\models\FormularioDinamico;
use common\models\snomed\SnomedProcedimientos;

class ConsultaEvaluacionController extends DefaultController
{
    use \frontend\controllers\traits\ConsultaTrait;

   
    public function createCore($modelConsulta)
    {
        $idsBDPrevioPost = [];

        $modelosConsultaEvaluaciones = $modelConsulta->consultaEvaluaciones; 

        if(!$modelosConsultaEvaluaciones){
            $modelosConsultaEvaluaciones = [new ConsultaPracticas()];
        }

        if (Yii::$app->request->post()) {

            $modelosConsultaEvaluaciones = FormularioDinamico::createAndLoadMultiple(ConsultaPracticas::classname(), 'id', $modelosConsultaEvaluaciones);
            
            $transaction = \Yii::$app->db->beginTransaction();

            try {

                $idsEnPost = [];

                $modelConsulta->save();
                
                foreach ($modelosConsultaEvaluaciones as $i => $modelEvaluacion) {
                    
                    SnomedProcedimientos::crearSiNoExiste($modelEvaluacion->codigo, Yii::$app->request->post("CustomAttribute")[$i]["termino_procedimiento"]);

                    $modelEvaluacion->id_consulta = $modelConsulta->id_consulta;
                    $modelEvaluacion->estado = ConsultaPracticas::ESTADO_COMPLETADA;
                    $modelEvaluacion->tipo_practica = "PREDIAGNOSTICO";

                    if (!$modelEvaluacion->save()) {
                        //var_dump($modelEvaluacion->getErrors());
                        throw new Exception();
                    }

                    
                    $idsEnPost[] = $modelEvaluacion->id;
                }

                // eliminar los que estaban en la BD y no vienen en el post
                $idsAEliminar = array_diff($idsBDPrevioPost, $idsEnPost);
                // hard delete, hardDeleteGrupo verifica que $idsAEliminar no sea vacio
                ConsultaPracticas::hardDeleteGrupo($modelConsulta->id_consulta, $idsAEliminar);
                // TODO: borrar archivos

            } catch (\Exception $th) {
                if ($th->getMessage() != "") {
                    var_dump($th->getMessage());die;
                    Yii::error($th->getMessage());
                }

                $transaction->rollBack();

                return $this->renderAjax('../consultas/v2/_form_evaluaciones', [
                    'modelConsulta' => $modelConsulta,
                    'modelosConsultaEvaluaciones' => $modelosConsultaEvaluaciones,
                ]);
            }

            $transaction->commit();

            return [
                'success' => true,
                'msg' => 'Las evaluaciones fueron cargadas exitosamente.',
                'url_siguiente' => $modelConsulta->urlSiguiente
            ];
        }

        return $this->renderAjax('../consultas/v2/_form_evaluaciones', [
            'modelConsulta' => $modelConsulta,
            'modelosConsultaEvaluaciones' => $modelosConsultaEvaluaciones,
        ]);
    }
}
