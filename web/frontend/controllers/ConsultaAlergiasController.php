<?php

namespace frontend\controllers;

use frontend\controllers\DefaultController;

use Yii;
use yii\web\Controller;
use yii\helpers\ArrayHelper;
use yii\base\Exception;

use frontend\components\SisseHtmlHelpers;

use common\models\Consulta;
use common\models\Alergias;
use common\models\DiagnosticoConsulta;
use common\models\FormularioDinamico;
use common\models\snomed\SnomedHallazgos;
use common\models\Turno;
use common\models\ConsultaDerivaciones;

/**
 * ConsultasController implements the CRUD actions for Consulta model.
 */
class ConsultaAlergiasController extends DefaultController
{
    use \frontend\controllers\traits\ConsultaTrait;

    public function createCore($modelConsulta)
    {
        $idsBDPrevioPost = [];

        $modelosConsultaAlergias = $modelConsulta->alergias;
        if (!$modelosConsultaAlergias) {
            $modelosConsultaAlergias = [new Alergias()];
        } else {
            $idsBDPrevioPost = ArrayHelper::getColumn($modelosConsultaAlergias, 'id');
        }      

        if (Yii::$app->request->post()) {

            $modelosConsultaAlergias = FormularioDinamico::createAndLoadMultiple(Alergias::classname(), 'id', $modelosConsultaAlergias);

            $idsEnPost = [];

            $transaction = \Yii::$app->db->beginTransaction();

            try {

                $modelConsulta->save();

                foreach ($modelosConsultaAlergias as $i => $modelAlergia) {                    

                    SnomedHallazgos::crearSiNoExiste($modelAlergia->id_snomed_hallazgo, Yii::$app->request->post("CustomAttribute")[$i]["termino_hallazgo"]);

                    $modelAlergia->id_consulta = $modelConsulta->id_consulta;
                    $modelAlergia->id_persona = $modelConsulta->id_persona;

                    if (!$modelAlergia->save()) {
                        throw new Exception();
                    }

                    $idsEnPost[] = $modelAlergia->id;
                    $modelosConsultaAlergias[$i]->setIsNewRecord(false);
                }

                // eliminar los que estaban en la BD y no vienen en el post
                $idsAEliminar = array_diff($idsBDPrevioPost, $idsEnPost);
                // hard delete, hardDeleteGrupo verifica que $idsAEliminar no sea vacio
                Alergias::hardDeleteGrupo($modelConsulta->id_consulta, $idsAEliminar);

            } catch (\Exception $th) {
                //var_dump($th->getMessage());
                if ($th->getMessage() != "") {
                    Yii::error($th->getMessage());
                }

                $transaction->rollBack();

                return $this->renderAjax('../consultas/v2/_form_alergias', [
                    'modelConsulta' => $modelConsulta,
                    'model_alergias' => (empty($modelosConsultaAlergias)) ? [new Alergias] : $modelosConsultaAlergias
                ]);
              
            }
            
            $transaction->commit();

            return [
                'success' => true,
                'msg' => 'Las alergias fueron cargadas exitosamente.',
                'url_siguiente' => $modelConsulta->urlSiguiente
            ];
        }

        return $this->renderAjax('../consultas/v2/_form_alergias', [
            'modelConsulta' => $modelConsulta,
            'model_alergias' => (empty($modelosConsultaAlergias)) ? [new Alergias] : $modelosConsultaAlergias
        ]);
    }
}
