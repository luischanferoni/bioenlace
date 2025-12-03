<?php

namespace backend\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\helpers\Json;
use yii\helpers\ArrayHelper;
use common\models\webvimark\moduleusermanagement\models\rbacDB\SisseRole;


use common\models\Servicio;
use common\models\busquedas\ServicioBusqueda;

/**
 * ServiciosController implements the CRUD actions for Servicio model.
 */
class ServiciosController extends Controller
{
    public function behaviors()
    {
        //control de acceso mediante la extension
        return [
            'ghost-access'=> [
                'class' => 'frontend\components\SisseGhostAccessControl',
                'except' => ['search']
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
     * Lists all Servicio models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new ServicioBusqueda();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single Servicio model.
     * @param string $id
     * @return mixed
     */
    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Creates a new Servicio model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new Servicio();
        $roles = ArrayHelper::map(SisseRole::getAvailableRoles(true), 'name', 'description');
 //var_dump($roles);die;
        if ($model->load(Yii::$app->request->post())){
            $model->parametros = serialize(array("color" => Yii::$app->request->post('color')));
            if ($model->save()) {
                return $this->redirect(['view', 'id' => $model->id_servicio]);
            }
        } else {
            $model->parametros = array("color" => "FFFFFF");
            return $this->render('create', [
                'model' => $model,
                'roles' => $roles
            ]);
        }
    }

    /**
     * Updates an existing Servicio model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param string $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id); 
        $roles = ArrayHelper::map(SisseRole::getAvailableRoles(true), 'name', 'description');

        if ($model->load(Yii::$app->request->post())){
            $model->parametros = serialize(array("color" => Yii::$app->request->post('color')));
            $model->hallazgos_ecl = Yii::$app->request->post("Servicio")['hallazgos_ecl'];
            $model->medicamentos_ecl = Yii::$app->request->post("Servicio")['medicamentos_ecl'];
            $model->procedimientos_ecl = Yii::$app->request->post("Servicio")['procedimientos_ecl'];
            $model->acepta_turnos = Yii::$app->request->post("Servicio")['acepta_turnos'];
            $model->acepta_practicas = Yii::$app->request->post("Servicio")['acepta_practicas'];
            if ($model->save()) {
                return $this->redirect(['view', 'id' => $model->id_servicio]);
            }
        } else {
            
            $model->parametros = unserialize($model->parametros);
            return $this->render('update', [
                'model' => $model,
                'roles' => $roles
            ]);
        }
    }

    /**
     * Deletes an existing Servicio model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param string $id
     * @return mixed
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    public function actionSearch($q = null) 
    {
        $out = ['results' => ['id' => '', 'text' => '']];
        if (is_null($q)) {
            return $out;
        }

        $data = Servicio::searchServicio($q);

        echo Json::encode(['results' => array_values($data)]);
    }

    /**
     * Finds the Servicio model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param string $id
     * @return Servicio the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Servicio::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}
