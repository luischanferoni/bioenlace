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
use frontend\components\UserRequest;
use common\models\ProfesionalEfectorServicio;

/**
 * PersonaProgramaController implements the CRUD actions for PersonaPrograma model.
 */
class PersonaProgramaController extends Controller
{
    /**
     * PES del profesional en contexto (sesión o parámetro `id_profesional_efector_servicio`).
     */
    private function resolveProfesionalEfectorServicioParaAlta(?int $idContextoPesParam): ?ProfesionalEfectorServicio
    {
        if ($idContextoPesParam !== null && $idContextoPesParam > 0) {
            $idPersona = ProfesionalEfectorServicio::resolveIdPersonaFromStaffContextId($idContextoPesParam);
            if ($idPersona !== null && $idPersona > 0) {
                $idEfector = (int) Yii::$app->user->getIdEfector();
                if ($idEfector > 0) {
                    $pes = ProfesionalEfectorServicio::find()
                        ->where(['id_persona' => $idPersona, 'id_efector' => $idEfector, 'deleted_at' => null])
                        ->orderBy(['id' => SORT_ASC])
                        ->one();
                    if ($pes !== null) {
                        return $pes;
                    }
                }

                return ProfesionalEfectorServicio::find()
                    ->where(['id_persona' => $idPersona, 'deleted_at' => null])
                    ->orderBy(['id_efector' => SORT_ASC, 'id' => SORT_ASC])
                    ->one();
            }
        }
        $idPes = (int) (Yii::$app->user->getIdProfesionalEfectorServicio() ?? 0);
        if ($idPes > 0) {
            $pes = ProfesionalEfectorServicio::findOne(['id' => $idPes, 'deleted_at' => null]);
            if ($pes !== null) {
                return $pes;
            }
        }
        $idPesSesion = (int) (Yii::$app->user->getIdRecursoHumano() ?? 0);
        if ($idPesSesion > 0) {
            $idPersona = ProfesionalEfectorServicio::resolveIdPersonaFromStaffContextId($idPesSesion);
            if ($idPersona !== null && $idPersona > 0) {
                $idEfector = (int) Yii::$app->user->getIdEfector();
                if ($idEfector > 0) {
                    return ProfesionalEfectorServicio::find()
                        ->where(['id_persona' => $idPersona, 'id_efector' => $idEfector, 'deleted_at' => null])
                        ->orderBy(['id' => SORT_ASC])
                        ->one();
                }
            }
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
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
     * @no_intent_catalog
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
     * @no_intent_catalog
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
     * @no_intent_catalog
    */
    public function actionCreate()
    {
        $model = new PersonaPrograma();

        $persona = Yii::$app->session['persona'];
        $persona =  unserialize($persona);

        $programa = Yii::$app->getRequest()->getQueryParam('programa');
        $idContextoPesParam = null;
        try {
            $idContextoPesParam = (int) UserRequest::requireUserParam('idRecursoHumano');
            if ($idContextoPesParam <= 0) {
                $idContextoPesParam = null;
            }
        } catch (\Throwable $e) {
            $idContextoPesParam = null;
        }
        $pesCtx = $this->resolveProfesionalEfectorServicioParaAlta($idContextoPesParam);
        if ($pesCtx === null) {
            throw new NotFoundHttpException('No se pudo determinar el contexto profesional (PES) en sesión.');
        }

        switch ($programa) {
            case 'diabetes':

                $id_programa = Programa::obtenerIdPrograma('Programa Diabetes');
                $personaEmpadronada = PersonaPrograma::personaEmpadronada($persona->id_persona, $id_programa);

                if ($model->load(Yii::$app->request->post())) {

                    $model->id_persona = $persona->id_persona;
                    $model->id_programa = $id_programa;
                    $model->id_profesional_efector_servicio = (int) $pesCtx->id;
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
     * @no_intent_catalog
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
     * @no_intent_catalog
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
