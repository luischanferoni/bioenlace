<?php

namespace frontend\controllers;

use Yii;
use yii\data\ActiveDataProvider;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;

use common\models\SegNivelInternacion;
use common\models\SegNivelInternacionRepository;
use common\models\SegNivelInternacionHcama;
use common\models\Persona;

/**
 * InternacionHcamaController implements the CRUD actions for SegNivelInternacionHcama model.
 */
class InternacionHcamaController extends Controller
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
     * Lists all SegNivelInternacionHcama models.
     * @return mixed
     */
    public function actionIndex($id)
    {
        $internacion = $this->findModelInternacion($id);
        $dataProvider = new ActiveDataProvider([
            'query' => SegNivelInternacionHcama::findByInternacionId(
                    $internacion->id
                    )]
            );

        $context = [
            'internacion' => $internacion,
            'id_internacion' => $internacion->id, 
        ];
        return $this->render('index', [
            'dataProvider' => $dataProvider,
            'context' => $context,
        ]);
    }

    /**
     * Displays a single SegNivelInternacionHcama model.
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
     * Creates a new SegNivelInternacionHcama model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate($id)
    {
        // Se utiliza esta accion para realizar el cambio de cama
        // de internacion
        
        $internacion = $this->findModelInternacion($id);
        
        $model = new SegNivelInternacionHcama();
        $model->id_internacion = $internacion->id;
        
        if ($model->load(Yii::$app->request->post())
            && $model->validate()
           ) {
            try {
                SegNivelInternacionRepository::doCambioCama(
                        $internacion,
                        $model
                );
                $msg = 'Cambio de cama efectuado con éxito.';
                Yii::$app->session->setFlash('success', $msg);
                return $this->redirect([
                    'internacion/view',
                    'id' => $internacion->id]);
            } catch (Exception $e) {
                $model->addError('motivo', $e->getMessage());
            }
        }
        
        $efector = Yii::$app->user->getIdEfector();
        $cama_actual = SegNivelInternacionHcama::getCamaActualLabel($internacion->id_cama);
        $camas_libres = SegNivelInternacionHcama::getCamasDisponiblesForSelect($efector);
        $context = [
            'internacion' => $internacion,
            'camas' => $camas_libres,
            'paciente' => $internacion->paciente->getNombreCompleto(Persona::FORMATO_NOMBRE_A_OA_N_ON),
            'cama_actual' => $cama_actual['label'],
            'id_internacion' => $internacion->id,
        ];

        return $this->render('create', [
            'model' => $model,
            'context' => $context
        ]);
    }

    /**
     * Updates an existing SegNivelInternacionHcama model.
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
     * Deletes an existing SegNivelInternacionHcama model.
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
     * Finds the SegNivelInternacionHcama model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return SegNivelInternacionHcama the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = SegNivelInternacionHcama::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
    
    
    protected function findModelInternacion($id)
    {
        if (($model = SegNivelInternacion::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
