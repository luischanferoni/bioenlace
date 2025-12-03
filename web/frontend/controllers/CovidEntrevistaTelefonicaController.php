<?php

namespace frontend\controllers;

use Yii;
use common\models\CovidEntrevistaTelefonica;
use common\models\CovidInvestigacionEpidemiologica;
use common\models\CovidFactoresRiesgo;
use common\models\Persona;
use common\models\busquedas\CovidEntrevistaTelefonicaBusqueda;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use common\models\Setup;

/**
 * CovidEntrevistaTelefonicaController implements the CRUD actions for CovidEntrevistaTelefonica model.
 */
class CovidEntrevistaTelefonicaController extends Controller
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
     * Lists all CovidEntrevistaTelefonica models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new CovidEntrevistaTelefonicaBusqueda();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single CovidEntrevistaTelefonica model.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {
        $model = $this->findModel($id);
        return $this->render('view', [
            'model' => $model,
            'model_covid_investigacion_epidemiologica' => $model->covidInvestigacionEpidemiologica,
            'model_covid_factores_riesgo' => $model->covidFactoresRiesgo
        ]);
    }

    /**
     * Creates a new CovidEntrevistaTelefonica model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate($id_persona)
    {
        $model_persona = Persona::findOne($id_persona);
        $model = new CovidEntrevistaTelefonica();
        if ($model->load(Yii::$app->request->post())) {
            $model_covid_factores_riesgo = new CovidFactoresRiesgo();
            $model_covid_factores_riesgo->load(Yii::$app->request->post());
            $model_covid_investigacion_epidemiologica = new CovidInvestigacionEpidemiologica();
            $model_covid_investigacion_epidemiologica->load(Yii::$app->request->post());
            $model->fecha_primera_dosis = Setup::convert($model->fecha_primera_dosis);
            $model->fecha_segunda_dosis = Setup::convert($model->fecha_segunda_dosis);
            $model->create_user = Yii::$app->user->id;
            $model->create_efector = Yii::$app->user->getIdEfector();
            $validar = $model->validate();
            if ($validar) {
                $transaction = \Yii::$app->db->beginTransaction();
                try {
                    if ($flag = $model->save()) {
                        $model_covid_factores_riesgo->id_entrevista_telefonica = $model->id;
                        if($model_covid_factores_riesgo->validate()) {
                            if (!($flag = $model_covid_factores_riesgo->save())) {
                                $transaction->rollBack();
                                exit();
                            }
                        }
                        $model_covid_investigacion_epidemiologica->id_entrevista_telefonica = $model->id;
                        $model_covid_investigacion_epidemiologica->fecha_inicio_sintomas = Setup::convert($model_covid_investigacion_epidemiologica->fecha_inicio_sintomas);
                        $model_covid_investigacion_epidemiologica->fecha_notificacion_positivo = Setup::convert($model_covid_investigacion_epidemiologica->fecha_notificacion_positivo);
                        $model_covid_investigacion_epidemiologica->fecha_fin_aislamiento = Setup::convert($model_covid_investigacion_epidemiologica->fecha_fin_aislamiento);

                        if($model_covid_investigacion_epidemiologica->validate()) {
                            if (!($flag = $model_covid_investigacion_epidemiologica->save())) {
                                $transaction->rollBack();
                                exit();
                            }
                        }
                    }
                    if ($flag) {
                        $transaction->commit();
                        return $this->redirect(['view', 'id' => $model->id]);
                    }
                } catch (Exception $e) {
                    $transaction->rollBack();
                }
            }
        }
        return $this->render('create', [
            'model' => $model,
            'model_persona' => $model_persona,
            'model_investigacion_epidemiologica' => isset($model->covidInvestigacionEpidemiologica) ? $model->covidInvestigacionEpidemiologica : new CovidInvestigacionEpidemiologica(),
            'model_factores_riesgo' => isset($model->covidFactoresRiesgo) ? $model->covidFactoresRiesgo : new CovidFactoresRiesgo(),
        ]);
    }

    /**
     * Updates an existing CovidEntrevistaTelefonica model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);
        $model_persona = Persona::findOne($model->id_persona);
        $model_covid_factores_riesgo = $model->covidFactoresRiesgo;
        $model_covid_investigacion_epidemiologica = $model->covidInvestigacionEpidemiologica;

        if ($model->load(Yii::$app->request->post())) {
            $model->fecha_primera_dosis = Setup::convert($model->fecha_primera_dosis);
            $model->fecha_segunda_dosis = Setup::convert($model->fecha_segunda_dosis);
            $model_covid_factores_riesgo->load(Yii::$app->request->post());
            $model_covid_investigacion_epidemiologica->load(Yii::$app->request->post());
            $model_covid_investigacion_epidemiologica->fecha_inicio_sintomas = Setup::convert($model_covid_investigacion_epidemiologica->fecha_inicio_sintomas);
            $model_covid_investigacion_epidemiologica->fecha_notificacion_positivo = Setup::convert($model_covid_investigacion_epidemiologica->fecha_notificacion_positivo);
            $model_covid_investigacion_epidemiologica->fecha_fin_aislamiento = Setup::convert($model_covid_investigacion_epidemiologica->fecha_fin_aislamiento);

            $validar = $model->validate();
            if ($validar) {
                $transaction = \Yii::$app->db->beginTransaction();
                try {
                    if ($flag = $model->save()) {
                        if($model_covid_factores_riesgo->validate()) {
                            if (!($flag = $model_covid_factores_riesgo->save())) {
                                $transaction->rollBack();
                                exit();
                            }
                        }
                        if($model_covid_investigacion_epidemiologica->validate()) {
                            if (!($flag = $model_covid_investigacion_epidemiologica->save())) {
                                $transaction->rollBack();
                                exit();
                            }
                        }
                    }
                    if ($flag) {
                        $transaction->commit();
                        return $this->redirect(['view', 'id' => $model->id]);
                    }
                } catch (Exception $e) {
                    $transaction->rollBack();
                }
            }
        }

        return $this->render('update', [
            'model' => $model,
            'model_persona' => $model_persona,
            'model_investigacion_epidemiologica' => isset($model->covidInvestigacionEpidemiologica) ? $model->covidInvestigacionEpidemiologica : new CovidInvestigacionEpidemiologica(),
            'model_factores_riesgo' => isset($model->covidFactoresRiesgo) ? $model->covidFactoresRiesgo : new CovidFactoresRiesgo(),
        ]);
    }

    /**
     * Deletes an existing CovidEntrevistaTelefonica model.
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
     * Lists all CovidEntrevistaTelefonica models for a persona.
     * @param integer $id_persona
     * @return mixed
     */

    public function actionListado($id_persona)
    {
        $searchModel = new CovidEntrevistaTelefonicaBusqueda();
        $dataProvider = $searchModel->search([$searchModel->formName() => ['id_persona' => $id_persona]]);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Finds the CovidEntrevistaTelefonica model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return CovidEntrevistaTelefonica the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = CovidEntrevistaTelefonica::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
