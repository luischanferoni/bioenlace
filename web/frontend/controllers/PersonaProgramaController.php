<?php

namespace frontend\controllers;

use Yii;
use common\models\PersonaPrograma;
use common\models\Programa;
use yii\data\ActiveDataProvider;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;

use frontend\filters\SisseActionFilter;

/**
 * PersonaProgramaController implements the CRUD actions for PersonaPrograma model.
 */
class PersonaProgramaController extends Controller
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
            'access' => [
                'class' => SisseActionFilter::className(),
                'only' => ['create'],
                'filtrosExtra' => [SisseActionFilter::FILTRO_PACIENTE],
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
     * Lists all PersonaPrograma models.
     * @return mixed
     */
    public function actionIndex()
    {
        $dataProvider = new ActiveDataProvider([
            'query' => PersonaPrograma::find(),
        ]);

        return $this->render('index', [
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single PersonaPrograma model.
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
     * Creates a new PersonaPrograma model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new PersonaPrograma();

        $persona = Yii::$app->session['persona'];
        $persona =  unserialize($persona);

        $programa = Yii::$app->getRequest()->getQueryParam('programa');

        $rrhh = \common\models\RrhhEfector::findOne(UserRequest::requireUserParam('idRecursoHumano'));

        switch ($programa) {
            case 'diabetes':

                $id_programa = Programa::obtenerIdPrograma('Programa Diabetes');
                $personaEmpadronada = PersonaPrograma::personaEmpadronada($persona->id_persona, $id_programa);

                if ($model->load(Yii::$app->request->post())) {

                    $model->id_persona = $persona->id_persona;
                    $model->id_programa = $id_programa;
                    $model->id_rrhh_efector = $rrhh->id_rr_hh;
                    $model->activo = PersonaPrograma::ACTIVO_SI;

                    if (!$model->save()) {

                        return $this->render('create', [
                            'model' => $model,
                            'personaEmpadronada' => $personaEmpadronada
                        ]);
                    }

                    return $this->redirect(['persona-programa-diabetes/create', 'id' => $model->id]);
                }

                return $this->render('create', [
                    'model' => $model,
                    'personaEmpadronada' => $personaEmpadronada
                ]);


                break;

            default:
                break;
        }
    }

    /**
     * Updates an existing PersonaPrograma model.
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
     * Deletes an existing PersonaPrograma model.
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
     * Finds the PersonaPrograma model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return PersonaPrograma the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = PersonaPrograma::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
