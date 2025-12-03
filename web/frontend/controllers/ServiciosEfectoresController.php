<?php

namespace frontend\controllers;

use Yii;
use common\models\ServiciosEfector;
use common\models\busquedas\ServiciosEfectorBusqueda;
use common\models\Efector;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;

/**
 * ServiciosEfectoresController implements the CRUD actions for ServiciosEfector model.
 */
class ServiciosEfectoresController extends Controller
{
    public function behaviors()
    {
        //control de acceso mediante la extension
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

    /**
     * Lists all ServiciosEfector models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new ServiciosEfectorBusqueda();
        $searchModel->id_efector = Yii::$app->user->getIdEfector();

        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        Yii::$app->params['botonera']['view'] = '../servicios-efectores/_botones';

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single ServiciosEfector model.
     * @param string $id_servicio
     * @param integer $id_efector
     * @return mixed
     */
    public function actionView($id_servicio, $id_efector)
    {
        return $this->render('view', [
            'model' => $this->findModel($id_servicio, $id_efector),
        ]);
    }

    /**
     * Creates a new ServiciosEfector model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new ServiciosEfector();
        $model->id_efector = Yii::$app->user->getIdEfector();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id_servicio' => $model->id_servicio, 'id_efector' => $model->id_efector, 'horario' => $model->horario]);
        } else {
            return $this->render('create', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Updates an existing ServiciosEfector model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param string $id_servicio
     * @param integer $id_efector
     * @return mixed
     */
    public function actionUpdate($id_servicio, $id_efector)
    {
        $model = $this->findModel($id_servicio, $id_efector);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['index']);
        } else {
            return $this->render('update', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Deletes an existing ServiciosEfector model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param string $id_servicio
     * @param integer $id_efector
     * @return mixed
     */
    public function actionDelete($id_servicio, $id_efector)
    {

        // si el servicio tiene rrhh asignado y activo, le pedimos que primero los elimine y lluego vuelva a eliminar el servicio
        $cantidadRrhhServicio = \common\models\RrhhServicio::findActive()
        ->leftJoin('rrhh_efector', 'rrhh_efector.id_rr_hh = rrhh_servicio.id_rr_hh')
        ->where("id_efector = $id_efector and id_servicio = $id_servicio and rrhh_servicio.deleted_at is null")
        ->count();

        if(isset($cantidadRrhhServicio) && $cantidadRrhhServicio > 0){
            return $this->asJson(['error' => true, 'msg' => 'El servicio que desea borrar aun tiene rrhh activos asignados, por favor primero elimine los rrhh y luego vuelva a intentar eliminar el servicio.']);
        }

        $this->findModel($id_servicio, $id_efector)->delete();

        return $this->asJson(['error' => false, 'msg' => 'El Servicio fue eliminado correctamente.']);
    }

    public function actionEfectoresPorServicio()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $out = [];

        if (isset($_POST['depdrop_parents'])) {
            $parents = $_POST['depdrop_parents'];
            if ($parents != null) {
                $id_servicio = $parents[0];
    
                $efectores = ServiciosEfector::find()
                ->joinWith('efector')
                ->where(['id_servicio' => $id_servicio])
                ->all();

                $arrayEfectores = ArrayHelper::map($efectores,'id_efector', 'efector.nombre');


                foreach($arrayEfectores as $key => $value){
                    $out[] = ['id' => $key, 'name' => $value];
                }
              
                return ['output' => $out, 'selected' => ''];
            }
        }
        return ['output' => '', 'selected' => ''];
    }

     /**
     * Vuelve deleted_at y deleted_by a null al servicio
     */
    public function actionReactivar($id_servicio, $id_efector)
    {
        $model = $this->findModel($id_servicio, $id_efector);
        if ($model->id_efector !== Yii::$app->user->getIdEfector()) {
            throw new UnauthorizedHttpException("No tiene autorizacion sobre la configuracion de este Efector");
        }

        if (is_null($model->deleted_at)) {
            return $this->asJson(['error' => true, 'msg' => 'El Servicio ya se encuentra activo']);
        }                

        $transaction = \Yii::$app->db->beginTransaction();
        try {

            $model->restore();

            $transaction->commit();
        } catch (Exception $e) {                    
            $transaction->rollBack();
            $error = json_decode($e->getMessage());

            return $this->asJson(['error' => true, 'msg' => $error]);
        }

        return $this->asJson(['error' => false, 'msg' => 'Servicio activado correctamente']);
    }

    /**
     * Finds the Servicios_efector model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param string $id_servicio
     * @param integer $id_efector
     * @return ServiciosEfector the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id_servicio, $id_efector)
    {
        if (($model = ServiciosEfector::findOne(['id_servicio' => $id_servicio, 'id_efector' => $id_efector])) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}
