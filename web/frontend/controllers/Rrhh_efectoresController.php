<?php

namespace frontend\controllers;

use Yii;
use common\models\RrhhEfector;
use common\models\busquedas\RrhhEfectorBusqueda;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;

/**
 * Rrhh_efectoresController implementa el CRUD para el modelo RrhhEfector.
 */
class Rrhh_efectoresController extends Controller
{
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

    public function actionIndex()
    {
        $searchModel = new RrhhEfectorBusqueda();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * @param integer $id_rr_hh
     * @param integer $id_efector
     */
    public function actionView($id_rr_hh, $id_efector)
    {
        return $this->render('view', [
            'model' => $this->findModel($id_rr_hh, $id_efector),
        ]);
    }

    public function actionCreate()
    {
        $model = new RrhhEfector();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id_rr_hh' => $model->id_rr_hh, 'id_efector' => $model->id_efector]);
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * @param integer $id_rr_hh
     * @param integer $id_efector
     */
    public function actionUpdate($id_rr_hh, $id_efector)
    {
        $model = $this->findModel($id_rr_hh, $id_efector);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id_rr_hh' => $model->id_rr_hh, 'id_efector' => $model->id_efector]);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * @param integer $id_rr_hh
     * @param integer $id_efector
     */
    public function actionDelete($id_rr_hh, $id_efector)
    {
        $this->findModel($id_rr_hh, $id_efector)->delete();
        return $this->redirect(['index']);
    }

    /**
     * @param integer $id_rr_hh
     * @param integer $id_efector
     * @return RrhhEfector
     * @throws NotFoundHttpException
     */
    protected function findModel($id_rr_hh, $id_efector)
    {
        if (($model = RrhhEfector::findOne(['id_rr_hh' => $id_rr_hh, 'id_efector' => $id_efector])) !== null) {
            return $model;
        }
        throw new NotFoundHttpException('The requested page does not exist.');
    }

    public function actionProfesionalesPorEfector()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $out = [];
        if (isset($_POST['depdrop_parents']) && $_POST['depdrop_parents'] != null) {
            $id_efector = $_POST['depdrop_parents'][0];
            $profesionales = RrhhEfector::obtenerMedicosPorEfector($id_efector);
            $arrayEfectores = ArrayHelper::map($profesionales, 'id_rr_hh', 'datos');
            foreach ($arrayEfectores as $key => $value) {
                $out[] = ['id' => $key, 'name' => $value];
            }
            return ['output' => $out, 'selected' => ''];
        }
        return ['output' => '', 'selected' => ''];
    }
}
