<?php

namespace frontend\controllers;

use Yii;
use common\models\LaboratorioNbuSnomed;
use common\models\LaboratorioNbu;
use common\models\busquedas\LaboratorioNbuSnomedBusqueda;
use common\models\busquedas\LaboratorioNbuBusqueda;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\data\ActiveDataProvider;
use common\models\snomed\SnomedProcedimientos;

/**
 * LaboratorioNbuSnomedController implements the CRUD actions for LaboratorioNbuSnomed model.
 */
class LaboratorioNbuSnomedController extends Controller
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
     * Lists all LaboratorioNbuSnomed models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new LaboratorioNbuSnomedBusqueda();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single LaboratorioNbuSnomed model.
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
     * Creates a new LaboratorioNbuSnomed model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new LaboratorioNbuSnomed();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

      /**
     * Creates several new LaboratorioNbuSnomed model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreateMasivo()
    {
        
        
        $dataProvider = new ActiveDataProvider([

            'query' => LaboratorioNbu::find()->joinWith('laboratorioNbuSnomed', 'laboratorio_nbu_snomed.codigo = laboratorio_nbu.codigo','LEFT JOIN')
            ->where(['laboratorio_nbu_snomed.codigo'=>null])->all(),
        
            'pagination' => false,
        
        ]);
        $cuenta = 0;

        if(Yii::$app->request->post('LaboratorioNbuSnomed')) {
            $laboratorios = Yii::$app->request->post('LaboratorioNbuSnomed');
            foreach ($laboratorios as $key => $value) {
                if($value['conceptId']){
                   $model = new LaboratorioNbuSnomed();
                   $model->codigo = $value['codigo'];
                   $model->conceptId = $value['conceptId'];
                   $model->created_at = date("Y-m-d H:i:s");
                   $model->user_id = Yii::$app->user->id;
                        if ($model->validate()) {
                            $model->save();
                            $cuenta += 1;  
                            $snoMed = SnomedProcedimientos::findOne(['conceptId' => $model->conceptId]);
                                if (!$snoMed) {
                                    $snoMed = new SnomedProcedimientos();
                                    $snoMed->conceptId = $model->conceptId;
                                    $snoMed->term = Yii::$app->snowstorm->busquedaPorConceptId($model->conceptId);
                                    $snoMed->save();
                                }
                            
                        } else {                           
                            $errors = $model->errors;
                            var_dump($errors);
                        }
                        
                            
                 
                }
            }
        }
        if($cuenta > 0){
            return $this->redirect(['index']);
        } else {
            $model = new LaboratorioNbuSnomed();
            return $this->render('create', [
                'model' => $model,
                'dataProvider' => $dataProvider
            ]);
        }
    }


    /**
     * Updates an existing LaboratorioNbuSnomed model.
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
     * Deletes an existing LaboratorioNbuSnomed model.
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
     * Finds the LaboratorioNbuSnomed model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return LaboratorioNbuSnomed the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = LaboratorioNbuSnomed::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
