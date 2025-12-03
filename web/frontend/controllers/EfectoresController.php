<?php

namespace frontend\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use common\models\ValidarArchivo; //incluyo el modelo que me permite validar el archivo
use yii\web\UploadedFile; //incluyo la extensión para cargar el archivo
use yii\helpers\Json;

use common\models\Efector;
use common\models\busquedas\EfectorBusqueda;
use common\models\busquedas\RrhhEfectorBusqueda;

//agregamos el modulo de la extension para el control de acceso
use webvimark\modules\UserManagement\UserManagementModule;

/**
 *
 * La clase EfectoresController implementa las action que posibilitan la gestión de
 * efectores de la bd SISSE.
 */
class EfectoresController extends Controller
{
    public function behaviors()
    {
        //control de acceso mediante la extensión
        return [
            'ghost-access' => [
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
     * Lists all Efector models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new EfectorBusqueda();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,

        ]);
    }

    /**
     * Displays a single Efector model.
     * @param integer $id
     * @return mixed
     */
    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Updates an existing Efector model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id_efector]);
        } else {
            return $this->render('update', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Finds the Efector model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Efector the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Efector::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('La pagina solicitada no existe.');
        }
    }

    //Action para mostrar el listado de efectores por usuario
    public function actionIndexuserefector()
    {
        $this->layout = 'main_sinmenuizquierda';
        $searchModel = new EfectorBusqueda();
        $array_efectores = Yii::$app->user->getEfectores() ?? [];
        $dataProvider = $searchModel->search(['EfectorBusqueda' => ['efectores' => array_keys($array_efectores)]]);

        return $this->render('indexuserefector', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionSearch($q = null)
    {
        $out = ['results' => ['id' => '', 'text' => '']];
        if (is_null($q)) {
            return $out;
        }

        $data = Efector::liveSearch($q);

        echo Json::encode(['results' => array_values($data)]);
    }
}
