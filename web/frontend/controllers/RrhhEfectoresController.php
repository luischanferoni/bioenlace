<?php

namespace frontend\controllers;

use Yii;
use common\models\RrhhEfector;
use common\models\busquedas\RrhhEfectorBusqueda;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;

/**
 * RrhhEfectoresController implementa el CRUD para el modelo RrhhEfector.
 */
class RrhhEfectoresController extends Controller
{
    public function behaviors()
    {
        return [
            'ghost-access' => [
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

    public function actionIndex()
    {
        $searchModel = new RrhhEfectorBusqueda();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

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

    public function actionDelete($id_rr_hh, $id_efector)
    {
        $this->findModel($id_rr_hh, $id_efector)->delete();
        return $this->redirect(['index']);
    }

    /**
     * @param string $id_rr_hh
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
}
