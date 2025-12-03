<?php

namespace frontend\controllers;

use common\models\ConsultaBalanceHidrico;
use common\models\FormularioDinamico;
use frontend\controllers\DefaultController;
use Yii;
use common\models\ConsultasRecetaLentes;
use common\models\busquedas\ConsultasRecetaLentesBusqueda;
use common\models\ConsultaPracticasOftalmologia;
use yii\base\Exception;
use yii\helpers\ArrayHelper;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;

/**
 * ConsultasRecetaLentesController implements the CRUD actions for ConsultasRecetaLentes model.
 */
class ConsultasRecetaLentesController extends DefaultController
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Lists all ConsultasRecetaLentes models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new ConsultasRecetaLentesBusqueda();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single ConsultasRecetaLentes model.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Creates a new ConsultasRecetaLentes model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function createCore($modelConsulta, $form_steps = true)
    {
        $receta = $modelConsulta->recetasLentes;
        $disabled = false;
        $oftalmologias = $modelConsulta->oftalmologias;
        if ($oftalmologias) {
            foreach ($oftalmologias as $o):
                if ($o->codigo == 252886007):
                    $disabled = true;
                endif;
            endforeach;
        }
        if (!$receta) {
            $receta = new ConsultasRecetaLentes();
        }

        if (Yii::$app->request->post()) {

            if (Yii::$app->request->post("ConsultasRecetaLentes")) {
                if (
                    Yii::$app->request->post("ConsultasRecetaLentes")["oi_esfera"] == "" &&
                    Yii::$app->request->post("ConsultasRecetaLentes")["oi_cilindro"] == "" &&
                    Yii::$app->request->post("ConsultasRecetaLentes")["oi_eje"] == "" &&
                    Yii::$app->request->post("ConsultasRecetaLentes")["od_esfera"] == "" &&
                    Yii::$app->request->post("ConsultasRecetaLentes")["od_cilindro"] == "" &&
                    Yii::$app->request->post("ConsultasRecetaLentes")["od_eje"] == ""
                ) {

                    $response = [
                        'success' => true,
                        'msg' => 'Los resultados fueron cargados exitosamente.',
                        'url_siguiente' => $modelConsulta->urlSiguiente
                    ];

                    return $response;
                }
            }

            $receta->load(Yii::$app->request->post());
            $valid = $modelConsulta->isNewRecord ?
                $modelConsulta->save() :
                $modelConsulta->validate();
            $valid = $receta->validate() && $valid;
            if ($valid) {
                $modelConsulta->save();
                $transaction = \Yii::$app->db->beginTransaction();
                try {
                    $receta->id_consulta = $modelConsulta->id_consulta;
                    if (!$receta->save()) {
                        $msg = 'Error al guardar entidad ConsultaRecetasLEntes';
                        throw new Exception($msg);
                    }
                    
                    $transaction->commit();

                    $response = [
                        'success' => true,
                        'msg' => 'Los resultados fueron cargados exitosamente.',
                        'url_siguiente' => $modelConsulta->urlSiguiente
                    ];

                    return $response;
                } catch (Exception $e) {
                    if ($e->getMessage() != "") {
                        Yii::error($e->getMessage());
                    }
                    $transaction->rollBack();
                }
            }
        }

        $context = [
            'modelConsulta' => $modelConsulta,
            'model' => $receta,
            'form_steps' => $form_steps,
            'disabled' => $disabled,
            'estcomp' => $oftalmologias
        ];
        $render_func = $form_steps ? 'renderAjax' : 'render';
        $template = $form_steps ? '_form' : 'create';
        return $this->{$render_func}($template, $context);
    }

    public function createCore2($modelConsulta)
    {
        return $this->doActionCreate($modelConsulta, True);
    }

    protected function doActionCreate($consulta, $is_ajax = False)
    {
        $recetas = $consulta->recetasLentes;

        if (!$recetas) {
            $recetas = $this->getNewModel();
        }

        if (Yii::$app->request->post()) {
            $recetas_ids_viejos = ArrayHelper::getColumn($recetas, 'id');

            $recetas = FormularioDinamico::createAndLoadMultiple(
                ConsultaBalanceHidrico::classname(),
                'id',
                $recetas
            );
            foreach ($recetas as $i => $receta) {
                $receta->id_consulta = $consulta->id_consulta;
            }

            $valid = $consulta->isNewRecord ?
                $consulta->save() :
                $consulta->validate();
            $valid = FormularioDinamico::validateMultiple(
                $recetas,
                ['id']
            )
                && $valid;
            if ($valid) {
                $transaction = \Yii::$app->db->beginTransaction();
                try {
                    foreach ($recetas as $i => $receta) {
                        if (!$receta->save()) {
                            $msg = 'Error al guardar entidad BalanceHidrico: ' . $i;
                            throw new \Exception($msg);
                        }
                    }
                    $receta_ids_guardar = ArrayHelper::getColumn($recetas, 'id');
                    $receta_ids_eliminar = array_diff($recetas_ids_viejos, $receta_ids_guardar);
                    if (count($receta_ids_eliminar) > 0) {
                        // hard delete
                        ConsultaBalanceHidrico::hardDeleteGrupo($consulta->id_consulta, $receta_ids_eliminar);
                    }
                    $transaction->commit();
                    $response = null;
                    $is_ajax = Yii::$app->request->post('from_step_forms');
                    Yii::error("Is_ajax:" . $is_ajax);
                    if (null !== $is_ajax) {
                        $response = [
                            'success' => true,
                            'msg' => 'Receta cargada exitosamente.',
                            'url_siguiente' => $consulta->urlSiguiente . '?id_consulta=' . $consulta->id_consulta
                        ];
                    } else {
                        $response = $this->redirect([
                            'consulta-receta-lentes/index',
                            'id_consulta' => $consulta->id_consulta
                        ]);
                    }
                    return $response;
                } catch (\Exception $e) {
                    $transaction->rollBack();
                }
            }
        }

        $context = [
            'consulta' => $consulta,
            'recetas' => $recetas,
            'is_ajax' => $is_ajax,
        ];
        $render_func = $is_ajax ? 'renderAjax' : 'render';
        $template = $is_ajax ? '_form' : 'create';
        return $this->{$render_func}($template, $context);
    }

    /**
     * Updates an existing ConsultasRecetaLentes model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing ConsultasRecetaLentes model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    /**
     * Finds the ConsultasRecetaLentes model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return ConsultasRecetaLentes the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = ConsultasRecetaLentes::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
