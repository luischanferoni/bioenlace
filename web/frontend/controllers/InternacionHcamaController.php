<?php

namespace frontend\controllers;

use Yii;
use yii\data\ActiveDataProvider;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\web\GoneHttpException;

use common\models\SegNivelInternacion;
use common\models\SegNivelInternacionHcama;

/**
 * Historial de cambios de cama (solo lectura).
 *
 * Cambio de cama activo: API / intent `internacion.cambio-cama-flow`.
 */
class InternacionHcamaController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }
        if (in_array($action->id, ['create', 'update', 'delete'], true)) {
            throw new GoneHttpException(
                'Cambio de cama migrado a API e asistente. '
                . 'Use /internacion/view#cambio-cama o intent internacion.cambio-cama-flow.'
            );
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
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
     * @no_intent_catalog
     */
    public function actionIndex($id)
    {
        $internacion = $this->findModelInternacion($id);
        $dataProvider = new ActiveDataProvider([
            'query' => SegNivelInternacionHcama::findByInternacionId(
                $internacion->id
            ),
        ]);

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
     * @no_intent_catalog
     */
    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
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
