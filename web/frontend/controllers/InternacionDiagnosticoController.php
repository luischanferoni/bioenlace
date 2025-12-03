<?php

namespace frontend\controllers;

use Yii;
use common\models\SegNivelInternacionDiagnostico;
use common\models\snomed\SnomedProblemas;
use common\models\busquedas\SegNivelInternacionDiagnosticoBusqueda;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use common\models\FormularioDinamico;
use common\models\SegNivelInternacion;
use yii\filters\AccessControl;
use yii\filters\AccessRule;
/**
 * InternacionDiagnosticoController implements the CRUD actions for SegNivelInternacionDiagnostico model.
 */
class InternacionDiagnosticoController extends Controller
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
     * Lists all SegNivelInternacionDiagnostico models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new SegNivelInternacionDiagnosticoBusqueda();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single SegNivelInternacionDiagnostico model.
     * @param integer $id
     * @param integer $id_internacion
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id, $id_internacion)
    {
        return $this->render('view', [
            'model' => $this->findModel($id, $id_internacion),
        ]);
    }

    /**
     * Creates a new SegNivelInternacionDiagnostico model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $models = [new SegNivelInternacionDiagnostico];
        $get = Yii::$app->request->get();

        if(isset($get['id'])){
            $id_internacion = $get['id'];
        } else {
            // TO-DO: Hacer que vuelva a seleccionar una internacion del listado de personas internadas actualmente.
            $id_internacion = 1;
        }

        //$modelInternacion= $this->findModelInternacion($id_internacion);
        //$models = $modelInternacion->segNivelInternacionDiagnosticos;
        if (Yii::$app->request->post()) {

            $models = FormularioDinamico::createMultiple(SegNivelInternacionDiagnostico::classname());
            FormularioDinamico::loadMultiple($models, Yii::$app->request->post());

             // ajax validation
            if (Yii::$app->request->isAjax) {
                Yii::$app->response->format = Response::FORMAT_JSON;
                return ArrayHelper::merge(
                    ActiveForm::validateMultiple($models)                    
                );
            }

            //$valid = Model::validateMultiple($models);
            //if ($valid) {
                $transaction = \Yii::$app->db->beginTransaction();
                try {
                    foreach ($models as $model) {
                        $model->id_internacion = $id_internacion;
                        $model->created_at = date('Y-m-d H:i:s');
                        $model->created_by = Yii::$app->user->id;
                        if (! ($flag = $model->save())) {
                            $transaction->rollBack();
                            break;
                        }else{
                            /* Guardo el conceptId & term localmente */
                            $snoMed = SnomedProblemas::findOne(['conceptId' => $model->conceptId]);
                            if (!$snoMed) {
                                $snoMed = new SnomedProblemas();
                                $snoMed->conceptId = $model->conceptId;
                                $snoMed->term = Yii::$app->snowstorm->busquedaPorConceptId($snoMed->conceptId);
                                $snoMed->save();// Si el registro ya existe
                            }
                        }
                    }
                    if ($flag) {
                        $transaction->commit();                       
                        
                         return $this->redirect(['internacion/view', 'id' => $model->id_internacion]);
                    }
                } catch (Exception $e) {
                    $transaction->rollBack();
                }
            //}         
        }

        return $this->render('create', [
            'models' => (empty($models)) ? [new SegNivelInternacionDiagnostico] : $models,
            'id_internacion' => $id_internacion,
        ]);
    }

    /**
     * Updates an existing SegNivelInternacionDiagnostico model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @param integer $id_internacion
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id, $id_internacion)
    {
        $model = $this->findModel($id, $id_internacion);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id, 'id_internacion' => $model->id_internacion]);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing SegNivelInternacionDiagnostico model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @param integer $id_internacion
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id, $id_internacion)
    {
        $this->findModel($id, $id_internacion)->delete();

        return $this->redirect(['internacion/view', 'id' => $id_internacion]);
    }

    /**
     * Finds the SegNivelInternacionDiagnostico model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @param integer $id_internacion
     * @return SegNivelInternacionDiagnostico the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id, $id_internacion)
    {
        if (($model = SegNivelInternacionDiagnostico::findOne(['id' => $id, 'id_internacion' => $id_internacion])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }

    /**
     * Finds the SegNivelInternacion model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return SegNivelInternacion the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModelInternacion($id)
    {
        if (($model = SegNivelInternacion::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
