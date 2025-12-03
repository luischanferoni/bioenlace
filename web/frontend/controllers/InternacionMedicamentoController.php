<?php

namespace frontend\controllers;

use Yii;
use common\models\SegNivelInternacionMedicamento;
use common\models\busquedas\SegNivelInternacionMedicamentoBusqueda;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use common\models\snomed\SnomedMedicamentos;
use common\models\FormularioDinamico;
use yii\filters\AccessControl;
use yii\filters\AccessRule;

/**
 * InternacionMedicamentoController implements the CRUD actions for SegNivelInternacionMedicamento model.
 */
class InternacionMedicamentoController extends Controller
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
     * Lists all SegNivelInternacionMedicamento models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new SegNivelInternacionMedicamentoBusqueda();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single SegNivelInternacionMedicamento model.
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
     * Creates a new SegNivelInternacionMedicamento model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $models = [new SegNivelInternacionMedicamento];
        $get = Yii::$app->request->get();

        if(isset($get['id'])){
            $id_internacion = $get['id'];
        } else {
            // TO-DO: Hacer que vuelva a seleccionar una internacion del listado de personas internadas actualmente.
            $id_internacion = 1;
        }


        if (Yii::$app->request->post()) {

            $models = FormularioDinamico::createMultiple(SegNivelInternacionMedicamento::classname());
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
                        $model->id_internacion = $id_internacion;                      
                        $model->created_at = date('Y-m-d H:i:s');
                        $model->fecha_indicacion = date('Y-m-d H:i:s');
                        $model->fecha_suspencion = date('Y-m-d H:i:s');
                        $model->user_suspencion = Yii::$app->user->id;
                        $model->create_user = Yii::$app->user->id;
                         if (! ($flag = $model->save())) {
                            $transaction->rollBack();
                            break;
                        }else{

                            $snoMed = SnomedMedicamentos::findOne(['conceptId' => $model->conceptId]);
                            if (!$snoMed) {
                                $snoMed = new SnomedMedicamentos();
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
        }

        return $this->render('create', [
            'models' => $models,
            'id_internacion' => $id_internacion,
        ]);
    }

    /**
     * Updates an existing SegNivelInternacionMedicamento model.
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
     * Deletes an existing SegNivelInternacionMedicamento model.
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
     * Finds the SegNivelInternacionMedicamento model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return SegNivelInternacionMedicamento the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = SegNivelInternacionMedicamento::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
