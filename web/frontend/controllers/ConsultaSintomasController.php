<?php

namespace frontend\controllers;

use frontend\controllers\DefaultController;

use Yii;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\base\Exception;


use frontend\filters\SisseActionFilter;
use frontend\components\SisseHtmlHelpers;

use common\models\Consulta;
use common\models\Turno;
use common\models\ConsultaSintomas;
use common\models\snomed\SnomedProblemas;

class ConsultaSintomasController extends DefaultController
{
    use \frontend\controllers\traits\ConsultaTrait;

    public function createCore($modelConsulta)
    {
        $idsBDPrevioPost = [];        
        $mapCodigoId = [];

        $modelosConsultaSintomas = $modelConsulta->consultaSintomas;
        if (!$modelosConsultaSintomas) {
            $modelosConsultaSintomas = [new ConsultaSintomas()];            
        } else {
            $idsBDPrevioPost = yii\helpers\ArrayHelper::getColumn($modelosConsultaSintomas, 'id');
            // necesito lo siguiente porque desde la vista (post) no recibo los id solo los codigos
            // y el hard delete lo hago por id
            $mapCodigoId = yii\helpers\ArrayHelper::map($modelosConsultaSintomas, 'codigo', 'id');
        }

        // paso un array de modelos a uno solo
        $modelConsultaSintomas = SisseHtmlHelpers::loadFromModelsAndCreateSelect2(ConsultaSintomas::classname(), $modelosConsultaSintomas);
        // modelConsultaSintomas puede ser un nuevo modelo, entonces select2_codigo es null
        $conceptsIdGuardados = $modelConsultaSintomas->select2_codigo ? $modelConsultaSintomas->select2_codigo : [];

        if (Yii::$app->request->post()) {
            $modelConsultaSintomas->load(Yii::$app->request->post());

            // vuelvo a pasar de un select2 a muchos modelos
            $modelosConsultaSintomas = SisseHtmlHelpers::loadFromSelect2AndCreateModels(ConsultaSintomas::classname());
            $terminos = explode(",", Yii::$app->request->post("terminos_sintomas"));

            $transaction = \Yii::$app->db->beginTransaction();

            try {

                $idsEnPost = [];

                $modelConsulta->save();
                $mensajeRespuesta = 'Se saltó el paso anterior sin agregar registros';

                foreach ($modelosConsultaSintomas as $i => $modelSintoma) {
                    SnomedProblemas::crearSiNoExiste($modelSintoma->codigo, $terminos[$i]);
                    // Puede estar ya creado
                    if (in_array($modelSintoma->codigo, $conceptsIdGuardados)) {
                        // voy registrando los ids para el hardDeleteAll
                        // los que estan en idsBDPrevioPost y no en idsEnPost se eliminan
                        $idsEnPost[] = $mapCodigoId[$modelSintoma->codigo];                        
                        continue;
                    }
                    
                    $modelSintoma->id_consulta = $modelConsulta->id_consulta;
                    
                    if (!$modelSintoma->save()) {
                        throw new Exception();
                    }                   
                }

                // eliminar los que estaban en la BD y no vienen en el post
                $idsAEliminar = array_diff($idsBDPrevioPost, $idsEnPost);
                // hard delete, hardDeleteGrupo verifica que $idsAEliminar no sea vacio
                ConsultaSintomas::hardDeleteGrupo($modelConsulta->id_consulta, $idsAEliminar);

                $mensajeRespuesta = 'El/Los sintomas fueron cargados exitosamente.';
                if (count($idsAEliminar) > 0) {
                    $mensajeRespuesta = 'El/Los sintomas fueron actualizados exitosamente.'; 
                }

                $transaction->commit();

            } catch (\Exception $th) {
                if ($th->getMessage() != "") {
                    Yii::error($th->getMessage());
                }

                $transaction->rollBack();

                $dataSintomas = [];
                $conceptsIdABuscar = $modelConsultaSintomas->select2_codigo ? $modelConsultaSintomas->select2_codigo : [];
                if (count($conceptsIdABuscar) > 0) {
                    $snomedProblemas = SnomedProblemas::find()->where(['in', 'conceptId', $conceptsIdABuscar])->asArray()->all();
                    $dataSintomas = yii\helpers\ArrayHelper::map($snomedProblemas, 'conceptId', 'term');
                }

                return $this->renderAjax('../consultas/v2/_form_sintomas', [
                    'modelConsulta' => $modelConsulta,
                    'modelConsultaSintomas' => $modelConsultaSintomas,
                    'dataSintomas' => $dataSintomas,
                ]);
            }
            return [
                'success' => true,
                'msg' => $mensajeRespuesta,
                'url_siguiente' => $modelConsulta->urlSiguiente
            ];
        }

        $dataSintomas = [];
        $conceptsIdABuscar = $modelConsultaSintomas->select2_codigo ? $modelConsultaSintomas->select2_codigo : [];
        if (count($conceptsIdABuscar) > 0) {
            $snomedProblemas = SnomedProblemas::find()->where(['in', 'conceptId', $conceptsIdABuscar])->asArray()->all();
            $dataSintomas = yii\helpers\ArrayHelper::map($snomedProblemas, 'conceptId', 'term');
        }

        return $this->renderAjax('../consultas/v2/_form_sintomas', [
            'modelConsulta' => $modelConsulta,
            'modelConsultaSintomas' => $modelConsultaSintomas,
            'dataSintomas' => $dataSintomas,
        ]);
    }

}
