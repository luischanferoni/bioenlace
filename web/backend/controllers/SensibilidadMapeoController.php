<?php

namespace backend\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use common\models\SensibilidadMapeoSnomed;
use common\models\SensibilidadCategoria;
use common\models\busquedas\SensibilidadMapeoSnomedBusqueda;

/**
 * CRUD del mapeo código SNOMED → categoría de sensibilidad.
 * Los códigos provienen de: snomed_hallazgos, snomed_medicamentos, snomed_motivos_consulta,
 * snomed_problemas, snomed_procedimientos, snomed_sintomas, snomed_situacion.
 */
class SensibilidadMapeoController extends Controller
{
    public function behaviors()
    {
        return [
            'ghost-access' => [
                'class' => 'frontend\components\SisseGhostAccessControl',
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['post'],
                ],
            ],
        ];
    }

    /**
     * Lista mapeos SNOMED → categoría con filtros.
     */
    public function actionIndex()
    {
        $searchModel = new SensibilidadMapeoSnomedBusqueda();
        $searchModel->load(Yii::$app->request->queryParams);
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    public function actionCreate()
    {
        $model = new SensibilidadMapeoSnomed();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('create', [
            'model' => $model,
            'categorias' => SensibilidadCategoria::find()->orderBy('nombre')->all(),
        ]);
    }

    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('update', [
            'model' => $model,
            'categorias' => SensibilidadCategoria::find()->orderBy('nombre')->all(),
        ]);
    }

    public function actionDelete($id)
    {
        $this->findModel($id)->delete();
        return $this->redirect(['index']);
    }

    /**
     * @param int $id
     * @return SensibilidadMapeoSnomed
     * @throws NotFoundHttpException
     */
    protected function findModel($id)
    {
        if (($model = SensibilidadMapeoSnomed::findOne($id)) !== null) {
            return $model;
        }
        throw new NotFoundHttpException('El mapeo solicitado no existe.');
    }
}
