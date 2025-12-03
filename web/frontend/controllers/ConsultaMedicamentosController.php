<?php

namespace frontend\controllers;

use frontend\controllers\DefaultController;

use Yii;
use yii\web\Controller;
use yii\helpers\ArrayHelper;
use yii\base\Exception;

use frontend\components\SisseHtmlHelpers;

use common\models\Consulta;
use common\models\Turno;
use common\models\ConsultaMedicamentos;
use common\models\ConsultaDerivaciones;
use common\models\FormularioDinamico;
use common\models\snomed\SnomedMedicamentos;

class ConsultaMedicamentosController extends DefaultController
{
    use \frontend\controllers\traits\ConsultaTrait;

    public function createCore($modelConsulta)
    {
        $idGuardados = [];

        $modelosConsultaMedicamentos = $modelConsulta->consultaMedicamentos;
        if (!$modelosConsultaMedicamentos) {
            $modelosConsultaMedicamentos = [new ConsultaMedicamentos()];
        } else {
            $idGuardados = ArrayHelper::getColumn($modelosConsultaMedicamentos, 'id');
        }

        if (Yii::$app->request->post()) {
            
            // Tengo que modificar el post para que FormularioDinamico lo pueda procesar
            // lo siguiente es lo que se recibe del post, array de de array con clave indicando el diagnostico (107)
            /* array(1) { 
                  [0]=> array(1) { 
                                [107]=> array(6) { 
                                    ["id_consultas_diagnosticos"]=> string(3) "107" 
                                    ["id_snomed_medicamento"]=> string(9) "330274004" 
                                    ["cantidad"]=> string(1) "1" 
                                    ["frecuencia"]=> string(1) "1" 
                                    ["durante"]=> string(1) "1" 
                                    ["indicaciones"]=> string(3) "asd" 
                                } 
                            } 
                        }
            */
            // Pero FormularioDinamico::createAndLoadMultiple necesita un array bidimensional
            if(isset(Yii::$app->request->post()["ConsultaMedicamentos"])){

            
            foreach (Yii::$app->request->post()["ConsultaMedicamentos"] as $medicamentosPorDiagnosticoPost) {                
                foreach ($medicamentosPorDiagnosticoPost as $idDiagnostico => $medicamentoPorDiagnosticoPost) {
                    $medicamentoPorDiagnosticoPost["id_consultas_diagnosticos"] = $idDiagnostico;
                    $consultaMedicamentosPost[] = $medicamentoPorDiagnosticoPost;
                }
            }

            $post = Yii::$app->request->post();
            $post["ConsultaMedicamentos"] = $consultaMedicamentosPost;
            Yii::$app->request->setBodyParams($post);
            
            $modelosConsultaMedicamentos = FormularioDinamico::createAndLoadMultiple(ConsultaMedicamentos::classname(), 'id', $modelosConsultaMedicamentos);
            //var_dump($modelosConsultaMedicamentos);
            $nuevosIds = [];
            
            $transaction = \Yii::$app->db->beginTransaction();

            try {

                $modelConsulta->save();
                
                $indiceSintomaAnterior = null;
                foreach ($modelosConsultaMedicamentos as $i => $modelMedicamento) {
                    if ($indiceSintomaAnterior != $modelMedicamento->id_consultas_diagnosticos) {
                        $indiceSintomaAnterior = 0;
                    }
                    $modelMedicamento->estado = ConsultaMedicamentos::ESTADO_ACTIVO;
                    
                    $nuevosIds[] = $modelMedicamento->id;

                    SnomedMedicamentos::crearSiNoExiste(
                                $modelMedicamento->id_snomed_medicamento, 
                                Yii::$app->request->post("CustomAttribute")[$indiceSintomaAnterior][$modelMedicamento->id_consultas_diagnosticos]["termino_medicamento"]);

                    $modelMedicamento->id_consulta = $modelConsulta->id_consulta;

                    if (!$modelMedicamento->save()) {
                        //var_dump($modelMedicamento->getErrors());
                        throw new Exception();
                    }                    
                }

                // eliminar los que estaban en la BD y no vienen en el post            
                $codigosAEliminar = array_diff($idGuardados, $nuevosIds);
                // hard delete, hardDeleteGrupo verifica que $idsAEliminar no sea vacio
                ConsultaMedicamentos::hardDeleteGrupo($modelConsulta->id_consulta, $codigosAEliminar);

            } catch (\Exception $th) {
                //Yii::error($th);
                if ($th->getMessage() != "") {                    
                    Yii::error($th->getMessage());
                }

                $transaction->rollBack();

                $diagModelosConsultaMedicamentos = [];
                // El array que la vista necesita es en el formato [id_diagnostico] => [array medicamentos]
                foreach ($modelosConsultaMedicamentos as $modeloConsultaMedicamento) {
                    $diagModelosConsultaMedicamentos[$modeloConsultaMedicamento->id_consultas_diagnosticos][] = $modeloConsultaMedicamento;
                }

                return $this->renderAjax('../consultas/v2/_form_medicamentos_diagnosticos.php', [
                    'modelConsulta' => $modelConsulta,
                    'modelosConsultaMedicamentos' => $diagModelosConsultaMedicamentos
                ]);
            }

            $transaction->commit();
        }
            return [
                'success' => true,
                'msg' => 'Los medicamentos fueron cargados exitosamente.',                
                'url_siguiente' => $modelConsulta->urlSiguiente
            ];  
                 
        }


        $diagModelosConsultaMedicamentos = [];
        // El array que la vista necesita es en el formato [id_diagnostico] => [array medicamentos]
        foreach ($modelosConsultaMedicamentos as $modeloConsultaMedicamento) {
            $diagModelosConsultaMedicamentos[$modeloConsultaMedicamento->id_consultas_diagnosticos][] = $modeloConsultaMedicamento;
        }

        return $this->renderAjax('../consultas/v2/_form_medicamentos_diagnosticos.php', [
            'modelConsulta' => $modelConsulta,
            'modelosConsultaMedicamentos' => $diagModelosConsultaMedicamentos
        ]);
    }
}
