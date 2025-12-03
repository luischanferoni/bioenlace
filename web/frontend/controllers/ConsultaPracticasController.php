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

class ConsultaPracticasController extends DefaultController
{
    use \frontend\controllers\traits\ConsultaTrait;
    use \frontend\controllers\traits\AdjuntoTrait;


    public function createCore($modelConsulta)
    {
        $idsBDPrevioPost = [];

        $modelosConsultaPracticas = $modelConsulta->consultaPracticas;

        $diagnosticos = [];
        if (!$modelosConsultaPracticas) {
            list($modelosConsultaPracticas, $diagnosticos) = $this->preCargarDerivaciones($modelConsulta);
        } else {
            $idsBDPrevioPost = ArrayHelper::getColumn($modelosConsultaPracticas, 'id');
            $diagnosticos = $modelConsulta->diagnosticoConsultas;
        }

        if (Yii::$app->request->post()) {

            $modelosConsultaPracticas = FormularioDinamico::createAndLoadMultiple(ConsultaPracticas::classname(), 'id', $modelosConsultaPracticas);

            $transaction = \Yii::$app->db->beginTransaction();

            try {

                $idsEnPost = [];

                $modelConsulta->save();

                foreach ($modelosConsultaPracticas as $i => $modelPractica) {

                    SnomedProcedimientos::crearSiNoExiste($modelPractica->codigo, Yii::$app->request->post("CustomAttribute")[$i]["termino_procedimiento"]);

                    $modelPractica->id_consulta = $modelConsulta->id_consulta;
                    $modelPractica->estado = ConsultaPracticas::ESTADO_COMPLETADA;
                    $modelPractica->tipo_practica = "POSTDIAGNOSTICO";
                    if(Yii::$app->request->post($i.'_rechazado')){
                        $consultaDerivaciones = ConsultaDerivaciones::findOne($modelPractica->id_consultas_derivaciones);
                        $consultaDerivaciones->estado = ConsultaDerivaciones::ESTADO_RECHAZADA;
                        $consultaDerivaciones->id_respondido = $modelConsulta->id_consulta;
                        $consultaDerivaciones->save();
                    }else {
                        if (!$modelPractica->save()) {
                            //var_dump($modelPractica->getErrors());
                            throw new Exception();
                        }
                        if($modelPractica->id_consultas_derivaciones){
                            $consultaDerivaciones = ConsultaDerivaciones::findOne($modelPractica->id_consultas_derivaciones);
                            $consultaDerivaciones->estado = ConsultaDerivaciones::ESTADO_RESUELTA;
                            $consultaDerivaciones->id_respondido = $modelPractica->id;
                            $consultaDerivaciones->save();
                        }
                    }
                    $array_archivos = UploadedFile::getInstancesByName("ConsultaPracticas[$i][archivos_adjuntos]");

                    if (!empty($array_archivos)) {

                        if (!empty($this->subirArchivos($array_archivos, 'ConsultaPracticas', $modelPractica->id))) {

                            return $this->renderAjax('../consultas/v2/_form_practicas_realizadas', [
                                'modelConsulta' => $modelConsulta,
                                'modelosConsultaPracticas' => $modelosConsultaPracticas,
                                'diagnosticos' => $diagnosticos
                            ]);
                        }
                    }

                    $idsEnPost[] = $modelPractica->id;
                }

                // eliminar los que estaban en la BD y no vienen en el post
                $idsAEliminar = array_diff($idsBDPrevioPost, $idsEnPost);
                // hard delete, hardDeleteGrupo verifica que $idsAEliminar no sea vacio
                ConsultaPracticas::hardDeleteGrupo($modelConsulta->id_consulta, $idsAEliminar);
                // TODO: borrar archivos

            } catch (\Exception $th) {
                if ($th->getMessage() != "") {
                    var_dump($th->getMessage());
                    die;
                    Yii::error($th->getMessage());
                }

                $transaction->rollBack();

                return $this->renderAjax('../consultas/v2/_form_practicas_realizadas', [
                    'modelConsulta' => $modelConsulta,
                    'modelosConsultaPracticas' => $modelosConsultaPracticas,
                    'diagnosticos' => $diagnosticos
                ]);
            }

            $transaction->commit();

            return [
                'success' => true,
                'msg' => 'Los practicas realizadas fueron cargadas exitosamente.',
                'url_siguiente' => $modelConsulta->urlSiguiente
            ];
        }

        return $this->renderAjax('../consultas/v2/_form_practicas_realizadas', [
            'modelConsulta' => $modelConsulta,
            'modelosConsultaPracticas' => $modelosConsultaPracticas,
            'diagnosticos' => $diagnosticos
        ]);
    }

    public function preCargarDerivaciones($modelConsulta)
    {
        $tieneDerivacion = false;
        if ($modelConsulta->parent_class === Consulta::PARENT_CLASSES[Consulta::PARENT_DERIVACION]) {
            $tieneDerivacion = true;
        }

        // 1. La consulta viene de un turno
        if ($modelConsulta->parent_class === Consulta::PARENT_CLASSES[Consulta::PARENT_TURNO]) {
            $turno = Turno::findOne($modelConsulta->parent_id);
            // 2. El turno origen de la consulta viene de una derivacion
            if ($turno->parent_class === Consulta::PARENT_CLASSES[Consulta::PARENT_DERIVACION]) {
                $tieneDerivacion = true;
            }
        }

        $modelosConsultaPracticas = [];
        $diagnosticos = null;
        $derivaciones = [];

        if ($tieneDerivacion) {
            // 3. Pueden ser multiples derivaciones
            $derivaciones = ConsultaDerivaciones::getDerivacionesPorPersona($modelConsulta->id_persona, $modelConsulta->id_efector, $modelConsulta->id_servicio, ConsultaDerivaciones::ESTADO_CON_TURNO);
            // 4. Busco la consulta que solicito la/s derivaciones para obtener los diagnosticos originales
            if($derivaciones) {
                $consultaSolicitante = $derivaciones[0]->consulta;
                $diagnosticos = $consultaSolicitante->diagnosticoConsultas;

                // 5. Precargo el codigo de las practicas con las derivaciones
                foreach ($derivaciones as $derivacion) {
                    if ($derivacion->tipo_solicitud == ConsultaDerivaciones::PRACTICA) {
                        $consultaPractica = new ConsultaPracticas();
                        $consultaPractica->codigo = $derivacion->codigo;
                        $consultaPractica->estado = ConsultaPracticas::ESTADO_COMPLETADA;
                        $consultaPractica->codigo_deshabilitado = true;
                        $consultaPractica->id_consultas_derivaciones = $derivacion->id;
                        $consultaPractica->setIsNewRecord(false);

                        $modelosConsultaPracticas[] = $consultaPractica;
                    }
                }
            }
        }

        if (count($modelosConsultaPracticas) == 0) {
            $modelosConsultaPracticas = [new ConsultaPracticas()];
        }

        if ($diagnosticos == null) {
            $diagnosticos = $modelConsulta->diagnosticoConsultas;
        }

        return [$modelosConsultaPracticas, $diagnosticos];
    }

    public function actionEliminarAdjunto($id)
    {
        $this->eliminarArchivo($id);
    }
}
