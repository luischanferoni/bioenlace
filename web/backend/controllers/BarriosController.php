<?php

namespace backend\controllers;

use Yii;
use common\models\Provincia;
use common\models\Barrios;
use common\models\Departamento;
use common\models\Localidad;
use common\models\busquedas\BarriosBusqueda;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\web\Response;

/**
 * BarriosController implements the CRUD actions for Barrios model.
 */
class BarriosController extends Controller
{
    /**
     * @inheritdoc
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
     * Lists all Barrios models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new BarriosBusqueda();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single Barrios model.
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
     * Creates a new Barrios model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new Barrios();

        // if ($model->load(Yii::$app->request->post()) && $model->save()) {
        //     \Yii::$app->getSession()->setFlash('success', '<b>El barrio fue creado con éxito!.</b>');
        //     return $this->redirect(['barrios/index']);
        // } else {
        //     return $this->render('create', [
        //         'model' => $model,
        //     ]);
        // }
        if ($model->load(Yii::$app->request->post()) && $model->save()) {

            if (Yii::$app->request->isAjax){
                Yii::$app->response->format = Response::FORMAT_JSON;

                return ['success' => '<b>El barrio fue creado con éxito.</b>',
                        'opts'=>'<option value="'.$model->id_barrio.'" selected>'.$model->nombre.'</option>'];
            }else{            
                \Yii::$app->getSession()->setFlash('success', '<b>El barrio fue creado con éxito.</b>');
                return $this->redirect(['barrios/index']);
            }
            
        } elseif (Yii::$app->request->isAjax){
            $id_provincia = Yii::$app->request->get('p');
            $provincia = null;
            if($id_provincia){
                $provincia = Provincia::findOne($id_provincia);
            }
            $id_departamento = Yii::$app->request->get('d');
            $departamento = null;
            if($id_departamento){
                $departamento = Departamento::findOne($id_departamento);
            }            
            $id_localidad = Yii::$app->request->get('l');
            $localidad = null;
            if($id_localidad){
                $localidad = Localidad::findOne($id_localidad);
            }

            return $this->renderAjax('_form', [
                'model' => $model,
                'provincia' => $provincia,
                'departamento' => $departamento,
                'localidad' => $localidad
            ]);
        }else{
            $provincia = new Provincia;
            $departamento = new Departamento;
            $localidad = new Localidad;

            return $this->render('create', [
                'model' => $model,
                'provincia' => $provincia,
                'departamento' => $departamento,
                'localidad' => $localidad
            ]);            
        }        
    }

    /**
     * Updates an existing Barrios model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            \Yii::$app->getSession()->setFlash('success', '<b>El barrio fue actualizado con éxito!.</b>');
            return $this->redirect(['barrios/index']);
        } else {

            $localidad = Localidad::findOne($model->id_localidad);
            $departamento = Departamento::findOne($localidad->id_departamento);
            $provincia = Provincia::findOne($departamento->id_provincia);

            return $this->render('update', [
                'model' => $model,
                'provincia' => $provincia,
                'departamento' => $departamento,
                'localidad' => $localidad,
            ]);
        }
    }

    /**
     * Deletes an existing Barrios model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();
        \Yii::$app->getSession()->setFlash('success', '<b>El barrio fue eliminado con éxito!.</b>');
        return $this->redirect(['barrios/index']);
    }

    /**
     * Finds the Barrios model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Barrios the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Barrios::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}
