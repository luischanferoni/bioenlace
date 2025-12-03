<?php

namespace frontend\controllers;

use frontend\controllers\DefaultController;

use Yii;
use yii\web\Controller;

use yii\base\Exception;

use frontend\components\SisseHtmlHelpers;

use common\models\Consulta;
use common\models\Turno;
use common\models\ConsultaMotivos;
use common\models\ConsultaDerivaciones;

use common\models\snomed\SnomedProblemas;

class ConsultaMotivosConsultaController extends DefaultController
{
    //use \frontend\controllers\traits\ConsultaTrait;


    public function createCore($modelConsulta)
    {
        $modelosMotivosConsulta = $modelConsulta->motivoConsulta;
        $idsBDPrevioPost = [];
        $mapCodigoId = [];
        $motivos = [];
        if (count($modelConsulta->getMostUseRrhh($modelConsulta->id_rr_hh)) > 3) {
            $motivos = $modelConsulta->getMostUseRrhh($modelConsulta->id_rr_hh);
        } else {
            $motivos = $modelConsulta->getMostUseServicio($modelConsulta->id_servicio);
        }

        if (!$modelosMotivosConsulta) {
            $modelosMotivosConsulta = [new ConsultaMotivos()];
        } else {
            $idsBDPrevioPost = yii\helpers\ArrayHelper::getColumn($modelosMotivosConsulta, 'id');
            // necesito lo siguiente porque desde la vista (post) no recibo los id solo los codigos
            // y el hard delete lo hago por id
            $mapCodigoId = yii\helpers\ArrayHelper::map($modelosMotivosConsulta, 'codigo', 'id');
        }

        // paso un array de modelos a uno solo       
        $modelConsultaMotivos = SisseHtmlHelpers::loadFromModelsAndCreateSelect2(ConsultaMotivos::classname(), $modelosMotivosConsulta);

        $conceptsIdGuardados = $modelConsultaMotivos->select2_codigo ? $modelConsultaMotivos->select2_codigo : [];

        /*-----------------------------------*/
        if (Yii::$app->request->post()) {
            $modelConsultaMotivos->load(Yii::$app->request->post());

            // vuelvo a pasar de un select2 a muchos modelos
            $modelosMotivosConsulta = SisseHtmlHelpers::loadFromSelect2AndCreateModels(ConsultaMotivos::classname());
            $terminos = explode(",", Yii::$app->request->post("terminos_motivos"));

            $transaction = \Yii::$app->db->beginTransaction();

            try {

                $idsEnPost = [];

                $modelConsulta->save();
                $mensajeRespuesta = 'Se saltó el paso anterior sin agregar registros';
                foreach ($modelosMotivosConsulta as $i => $modelMotivo) {

                    SnomedProblemas::crearSiNoExiste($modelMotivo->codigo, $terminos[$i]);

                    if (in_array($modelMotivo->codigo, $conceptsIdGuardados)) {
                        // voy registrando los ids para el hardDeleteAll
                        // los que estan en idsBDPrevioPost y no en idsEnPost se eliminan
                        $idsEnPost[] = $mapCodigoId[$modelMotivo->codigo];
                        continue;
                    }

                    $modelMotivo->id_consulta = $modelConsulta->id_consulta;

                    if (!$modelMotivo->save()) {
                        var_dump($modelMotivo->getErrors());
                        throw new Exception();
                    }
                }

                // eliminar los que estaban en la BD y no vienen en el post
                $idsAEliminar = array_diff($idsBDPrevioPost, $idsEnPost);
                //var_dump($idsEnPost);die;
                // hard delete, hardDeleteGrupo verifica que $idsAEliminar no sea vacio
                ConsultaMotivos::hardDeleteGrupo($modelConsulta->id_consulta, $idsAEliminar);

                $mensajeRespuesta = 'El/Los Motivos fueron cargados exitosamente.';
                if (count($idsAEliminar) > 0) {
                    $mensajeRespuesta = 'El/Los Motivos fueron actualizados exitosamente.';
                }

                $transaction->commit();
            } catch (\Exception $th) {
                if ($th->getMessage() != "") {
                    echo  Yii::error($th->getMessage());
                }
                $transaction->rollBack();

                $dataProblemas = [];
                $conceptsIdABuscar = $modelConsultaMotivos->select2_codigo ? $modelConsultaMotivos->select2_codigo : [];
                if (count($conceptsIdABuscar) > 0) {
                    $snomedProblemas = SnomedProblemas::find()->where(['in', 'conceptId', $conceptsIdABuscar])->asArray()->all();
                    $dataProblemas = yii\helpers\ArrayHelper::map($snomedProblemas, 'conceptId', 'term');
                }

                return $this->renderAjax('../consultas/v2/_form_motivos_consulta', [
                    'modelConsulta' => $modelConsulta,
                    'motivos' => $motivos,
                    'modelConsultaMotivos' => (empty($modelConsultaMotivos)) ? [new ConsultaMotivos] : $modelConsultaMotivos,
                    'dataProblemas' => $dataProblemas,
                ]);
            }

            return [
                'success' => true,
                'msg' => $mensajeRespuesta,
                'url_siguiente' => $modelConsulta->urlSiguiente
            ];
        }
        /*------------------------------------*/

        $dataProblemas = [];
        $conceptsIdABuscar = $modelConsultaMotivos->select2_codigo ? $modelConsultaMotivos->select2_codigo : [];
        if (count($conceptsIdABuscar) > 0) {
            $snomedProblemas = SnomedProblemas::find()->where(['in', 'conceptId', $conceptsIdABuscar])->asArray()->all();
            $dataProblemas = yii\helpers\ArrayHelper::map($snomedProblemas, 'conceptId', 'term');
        }

        return $this->renderAjax('../consultas/v2/_form_motivos_consulta', [
            'modelConsulta' => $modelConsulta,
            'motivos' => $motivos,
            'modelConsultaMotivos' => (empty($modelConsultaMotivos)) ? [new ConsultaMotivos] : $modelConsultaMotivos,
            'dataProblemas' => $dataProblemas,
        ]);
    }
}
