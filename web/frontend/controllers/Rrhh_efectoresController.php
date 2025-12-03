<?php

namespace frontend\controllers;

use Yii;
use common\models\Rrhh_efector;
use common\models\busquedas\Rrhh_efectorBusqueda;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;

/**
 * Rrhh_efectoresController implements the CRUD actions for Rrhh_efector model.
 */
class Rrhh_efectoresController extends Controller
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
     * Lists all Rrhh_efector models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new Rrhh_efectorBusqueda();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single Rrhh_efector model.
     * @param integer $id_rr_hh
     * @param integer $id_efector
     * @param integer $id_condicion_laboral
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id_rr_hh, $id_efector, $id_condicion_laboral)
    {
        return $this->render('view', [
            'model' => $this->findModel($id_rr_hh, $id_efector, $id_condicion_laboral),
        ]);
    }

    /**
     * Creates a new Rrhh_efector model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new Rrhh_efector();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id_rr_hh' => $model->id_rr_hh, 'id_efector' => $model->id_efector, 'id_condicion_laboral' => $model->id_condicion_laboral]);
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Updates an existing Rrhh_efector model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id_rr_hh
     * @param integer $id_efector
     * @param integer $id_condicion_laboral
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id_rr_hh, $id_efector, $id_condicion_laboral)
    {
        $model = $this->findModel($id_rr_hh, $id_efector, $id_condicion_laboral);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id_rr_hh' => $model->id_rr_hh, 'id_efector' => $model->id_efector, 'id_condicion_laboral' => $model->id_condicion_laboral]);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing Rrhh_efector model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id_rr_hh
     * @param integer $id_efector
     * @param integer $id_condicion_laboral
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id_rr_hh, $id_efector, $id_condicion_laboral)
    {
        $this->findModel($id_rr_hh, $id_efector, $id_condicion_laboral)->delete();

        return $this->redirect(['index']);
    }

    /**
     * Finds the Rrhh_efector model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id_rr_hh
     * @param integer $id_efector
     * @param integer $id_condicion_laboral
     * @return Rrhh_efector the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id_rr_hh, $id_efector, $id_condicion_laboral)
    {
        if (($model = Rrhh_efector::findOne(['id_rr_hh' => $id_rr_hh, 'id_efector' => $id_efector, 'id_condicion_laboral' => $id_condicion_laboral])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }



    public function actionProfesionalesPorEfector()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $out = [];

        if (isset($_POST['depdrop_parents'])) {
            $parents = $_POST['depdrop_parents'];
            if ($parents != null) {
                $id_efector = $parents[0];
    
                $profesionales = Rrhh_efector::obtenerProfesionalesPorEfector($id_efector);
                $arrayEfectores = ArrayHelper::map($profesionales,'id_rr_hh', 'datos');

                foreach($arrayEfectores as $key => $value){
                    $out[] = ['id' => $key, 'name' => $value];
                }
              
                return ['output' => $out, 'selected' => ''];
            }
        }
        return ['output' => '', 'selected' => ''];
    }

}
