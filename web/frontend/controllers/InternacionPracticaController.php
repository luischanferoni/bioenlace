<?php

namespace frontend\controllers;

use Yii;
use common\models\SegNivelInternacionPractica;
use common\models\busquedas\SegNivelInternacionPracticaBusqueda;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use common\models\snomed\SnomedProcedimientos;
use common\models\FormularioDinamico;
use yii\filters\AccessControl;
use yii\filters\AccessRule;
use yii\web\UploadedFile;

/**
 * InternacionPracticaController implements the CRUD actions for SegNivelInternacionPractica model.
 */
class InternacionPracticaController extends Controller
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
     * Lists all SegNivelInternacionPractica models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new SegNivelInternacionPracticaBusqueda();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single SegNivelInternacionPractica model.
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
     * Creates a new SegNivelInternacionPractica model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $models = [new SegNivelInternacionPractica];
        $get = Yii::$app->request->get();

        if(!isset($get['id']) || (isset($get['id']) && empty($get['id']))) {
            throw new NotFoundHttpException('Vuelva atrás para seleccionar la internación');
        }
        
        $id_internacion = $get['id'];        

        if (!Yii::$app->request->post()) {
            return $this->render('create', [
                'models' => $models,
                'id_internacion' => $id_internacion,
            ]);
        }

        $models = FormularioDinamico::createMultiple(SegNivelInternacionPractica::classname());
        FormularioDinamico::loadMultiple($models, Yii::$app->request->post());

        // ajax validation
        if (Yii::$app->request->isAjax) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return ArrayHelper::merge(
                ActiveForm::validateMultiple($models)                    
            );
        }

        $transaction = \Yii::$app->db->beginTransaction();
        try {
            foreach ($models as $model) {
                $model->created_at = date('Y-m-d H:i:s');
                $model->create_user = Yii::$app->user->id;
                $model->id_internacion = $id_internacion;
                $model->id_rrhh_solicita = Yii::$app->request->post("id_rrhh_solicita");                
                if (! ($flag = $model->save())) {
                    $transaction->rollBack();
                    break;
                }
                $snoMed = SnomedProcedimientos::findOne(['conceptId' => $model->conceptId]);
                if (!$snoMed) {
                    $snoMed = new SnomedProcedimientos();
                    $snoMed->conceptId = $model->conceptId;
                    $snoMed->term = Yii::$app->snowstorm->busquedaPorConceptId($snoMed->conceptId);
                    $snoMed->save();// Si el registro ya existe
                }
            }
            if ($flag) {
                $transaction->commit();                        
                return $this->redirect(['internacion/view', 'id' => $model->id_internacion]);
            }
        } catch (Exception $e) {
            $transaction->rollBack();
        }
    }

    /**
     * Updates an existing SegNivelInternacionPractica model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);
        
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            $model->imageFile = UploadedFile::getInstance($model, 'imageFile');
             
            if ($model->imageFile) {
                $archivo = $model->imageFile;
                $fileName = $archivo->baseName.'_'.rand().'.' . $archivo->extension;
                $archivo->saveAs('practicas/' . $fileName); 
                
                $model->fileName = $fileName;
                 
                $model->save();              
            }    

            return $this->redirect(['internacion/view', 'id' => $model->id_internacion]);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing SegNivelInternacionPractica model.
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
     * Finds the SegNivelInternacionPractica model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return SegNivelInternacionPractica the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = SegNivelInternacionPractica::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
