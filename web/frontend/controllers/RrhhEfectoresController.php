<?php

namespace frontend\controllers;

use Yii;
use common\models\Rrhh_efector;
use common\models\busquedas\Rrhh_efectorBusqueda;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;

/**
 * RrhhEfectoresController implements the CRUD actions for Rrhh_efector model.
 */
class RrhhEfectoresController extends Controller
{
    public function behaviors()
    {
         //control de acceso mediante la extension
        return [
            'ghost-access'=> [
                'class' => 'frontend\components\SisseGhostAccessControl',
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['post'],
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
     * @param string $id_rr_hh
     * @param integer $id_efector
     * @return mixed
     */
    public function actionView($id_rr_hh, $id_efector)
    {
        return $this->render('view', [
            'model' => $this->findModel($id_rr_hh, $id_efector),
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
            return $this->redirect(['view', 'id_rr_hh' => $model->id_rr_hh, 'id_efector' => $model->id_efector]);
        } else {
            return $this->render('create', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Updates an existing Rrhh_efector model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param string $id_rr_hh
     * @param integer $id_efector
     * @return mixed
     */
    public function actionUpdate($id_rr_hh, $id_efector)
    {
        $model = $this->findModel($id_rr_hh, $id_efector);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id_rr_hh' => $model->id_rr_hh, 'id_efector' => $model->id_efector]);
        } else {
            return $this->render('update', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Deletes an existing Rrhh_efector model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param string $id_rr_hh
     * @param integer $id_efector
     * @return mixed
     */
    public function actionDelete($id_rr_hh, $id_efector)
    {
        $this->findModel($id_rr_hh, $id_efector)->delete();

        return $this->redirect(['index']);
    }

    /**
     * Finds the Rrhh_efector model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param string $id_rr_hh
     * @param integer $id_efector
     * @return Rrhh_efector the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id_rr_hh, $id_efector)
    {
        if (($model = Rrhh_efector::findOne(['id_rr_hh' => $id_rr_hh, 'id_efector' => $id_efector])) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}
