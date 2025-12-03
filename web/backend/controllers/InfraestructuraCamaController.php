<?php

namespace backend\controllers;

use Yii;
use common\models\InfraestructuraCama;
use common\models\busquedas\InfraestructuraCamaBusqueda;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use yii\filters\AccessRule;

/**
 * InfraestructuraCamaController implements the CRUD actions for InfraestructuraCama model.
 */
class InfraestructuraCamaController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'ghost-access' => [
                'class' => 'webvimark\modules\UserManagement\components\GhostAccessControl',
            ],            
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ]
        ];
    }


    public function beforeAction($action)
    {
        if (!Yii::$app->user->getIdEfector()){
            Yii::$app->response->redirect(['efectores/index'])->send();
        }
    }
    /**
     * Lists all InfraestructuraCama models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new InfraestructuraCamaBusqueda();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single InfraestructuraCama model.
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
     * Creates a new InfraestructuraCama model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new InfraestructuraCama();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Updates an existing InfraestructuraCama model.
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
     * Deletes an existing InfraestructuraCama model.
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
     * Finds the InfraestructuraCama model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return InfraestructuraCama the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = InfraestructuraCama::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }

    /**
     * Listado de Camas Ocupadas.
     * @modificacion: 22/08/2022
     * actionReporte() 
     * @autor: 
     */
    public function actionReportecamasocupadas() {
        $searchModel = new InfraestructuraCamaBusqueda();
        $searchModel->estado = 'ocupada';
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('reporteCamasOcupadas', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Reporte de Camas.
     * @modificacion: 22/08/2022
     * actionReporte() 
     * @autor: 
     */
    public function actionReportecamas() {
        $searchModel = new InfraestructuraCamaBusqueda();
        //$searchModel->estado = 'ocupada';
        $dataProvider = $searchModel->searchOcupadas(Yii::$app->request->queryParams);

        return $this->render('reporteCamas', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'type'=>  Yii::$app->getRequest()->getQueryParam('type')
        ]);
    }
}
