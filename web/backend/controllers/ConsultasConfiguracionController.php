<?php

namespace backend\controllers;

use common\models\busquedas\ConsultasConfiguracionBusqueda;
use Yii;

use yii\data\ActiveDataProvider;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\helpers\Url;

use common\models\ConsultasConfiguracion;
/**
 * ConsultasConfiguracionController implements the CRUD actions for ConsultasConfiguracion model.
 */
class ConsultasConfiguracionController extends Controller
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
            ],
        ];
    }

    /**
     * Lists all ConsultasConfiguracion models.
     * @return mixed
     */
    public function actionIndex()
    {      

        $searchModel = new ConsultasConfiguracionBusqueda();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'dataProvider' => $dataProvider,
            'searchModel' => $searchModel,
        ]);
    }

    /**
     * Displays a single ConsultasConfiguracion model.
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
     * Creates a new ConsultasConfiguracion model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new ConsultasConfiguracion();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Updates an existing ConsultasConfiguracion model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if (Yii::$app->request->post()) {
            $newModel = new ConsultasConfiguracion();
            $model->delete();
            if($newModel->load(Yii::$app->request->post()) && $newModel->save()) {                
                return $this->redirect(['view', 'id' => $newModel->id]);
            }            
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    public function actionCheckearUrls()
    {
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $pasos = Yii::$app->request->post('pasos');

        $urls = explode(",", $pasos);

        foreach ($urls as $i => $url) {
            if ($url == "") {
                continue;
            }
            $urlFinal = Url::toRoute(trim($url));
            $urlFinal = str_replace("/admin/", "/", $urlFinal);

            $urlsFinales[] = '<span class="badge badge-pill border border-primary text-primary">'.
                            '<a href="'.$urlFinal.'" target="_blank">PASO '.$i.': url -> "'.$urlFinal.'"</a>'.
                            '</span> ';
        }

        return $urlsFinales;
    }

    /**
     * Deletes an existing ConsultasConfiguracion model.
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
     * Finds the ConsultasConfiguracion model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return ConsultasConfiguracion the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = ConsultasConfiguracion::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
