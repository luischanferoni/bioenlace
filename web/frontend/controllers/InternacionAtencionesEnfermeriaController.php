<?php

namespace frontend\controllers;

use Yii;
use common\models\SegNivelInternacionAtencionesEnfermeria;
use common\models\busquedas\SegNivelInternacionAtencionesEnfermeriaBusqueda;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\validators\NumberValidator;
use yii\validators\RequiredValidator;
use yii\validators\StringValidator;
use yii\web\Response;

/**
 * InternacionAtencionesEnfermeriaController implements the CRUD actions for SegNivelInternacionAtencionesEnfermeria model.
 */
class InternacionAtencionesEnfermeriaController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'ghost-access' => [
                'class' => 'frontend\components\SisseGhostAccessControl',
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
     * Lists all SegNivelInternacionAtencionesEnfermeria models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new SegNivelInternacionAtencionesEnfermeriaBusqueda();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single SegNivelInternacionAtencionesEnfermeria model.
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
     * Creates a new SegNivelInternacionAtencionesEnfermeria model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new SegNivelInternacionAtencionesEnfermeria();
        $get = Yii::$app->request->get();
            $error = "";

        if(isset($get['id'])){
            $id_internacion = $get['id'];
        } else {
            // TO-DO: Hacer que vuelva a seleccionar una internacion del listado de personas internadas actualmente.
            $id_internacion = 1;
        }

        //if ($model->load(Yii::$app->request->post()) && $model->save()) {
        if(Yii::$app->request->isPost){
            $model->created_at = date('Y-m-d H:i:s');
            $model->id_user = Yii::$app->user->id;
            $model->id_internacion = $id_internacion;
            
            $errores = [];
            $s_validator = new StringValidator();
            $s_validator->length = 3;
            $campos = array('TensionArterial1[271649006]', 'TensionArterial1[271650006]');
            foreach ($campos as $campo) {
                if(Yii::$app->request->post($campo) != ''){
                    if (!$s_validator->validate(Yii::$app->request->post($campo), $error)) {
                        $errores[] = '<b>'.$campo.': </b>'.$error;
                    }
                }
            }
            $campos = array('TensionArterial2[271649006]', 'TensionArterial2[271650006]');
            foreach ($campos as $campo) {
                if(Yii::$app->request->post($campo) != ''){
                    if (!$s_validator->validate(Yii::$app->request->post($campo), $error)) {
                        $errores[] = '<b>'.$campo.': </b>'.$error;
                    }
                }
            }
            $s_validator = new StringValidator();
            $s_validator->length = 5;
            $campos = array('386708005', '386709002');            
            foreach ($campos as $campo) {
                if(Yii::$app->request->post($campo) != ''){
                    if (!$s_validator->validate(Yii::$app->request->post($campo), $error)) {
                        $errores[] = '<b>'.$campo.': </b>'.$error;
                    }
                }
            }
            $n_validator = new NumberValidator();
            $campos = array('temperatura', 'glucemia_capilar', 'circunferencia_abdominal');            
            foreach ($campos as $campo) {
                if(Yii::$app->request->post($campo) != ''){
                    if (!$n_validator->validate(Yii::$app->request->post($campo), $error)) {
                        $errores[] = '<b>'.$campo.': </b>'.$error;
                    }
                }
            }
            
            Yii::$app->response->format = Response::FORMAT_JSON;

            if (count($errores) == 0) {              
                $post = Yii::$app->request->post();
                $observaciones = Yii::$app->request->post('observaciones');
                $fecha = isset($post['AtencionesEnfermeria']['fecha'])?$post['AtencionesEnfermeria']['fecha']:'';//Yii::$app->request->post('fecha');
                
                unset($post['_csrf']);
                unset($post['observaciones']);
                unset($post['AtencionesEnfermeria']);
                foreach ($post as $campo => $valor) {
                    if ($valor == '') {
                        unset($post[$campo]);
                    }
                }
                $codificado = json_encode($post);
                $model->datos = $codificado;
                $model->observaciones = $observaciones;
                $fecha = isset($fecha)?str_replace('/', '-', $fecha):'';
                
                $model->fecha= isset($fecha)?date('Y-m-d', strtotime($fecha)):date('Y-m-d');
                
                if ($model->save()) {
                    return $this->redirect(['/internacion/view', 'id' => $model->id_internacion]);
                } else {
                  echo "MODEL NOT SAVED";
                  print_r($model->getAttributes());
                  print_r($model->getErrors());
                  exit;
                }               

                
            }
            else
            {
                $error = '<b>Se produjeron los siguientes errores.</b><br><ul><li>'
                                    .implode('</li><li>', $errores).'</li></ul>'; 
            }
        }

        return $this->render('create', [
            'model' => $model,
            'id_internacion' => $id_internacion,
            'errores' => $error,
        ]);
    }

    /**
     * Updates an existing SegNivelInternacionAtencionesEnfermeria model.
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
     * Deletes an existing SegNivelInternacionAtencionesEnfermeria model.
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
     * Finds the SegNivelInternacionAtencionesEnfermeria model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return SegNivelInternacionAtencionesEnfermeria the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = SegNivelInternacionAtencionesEnfermeria::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
