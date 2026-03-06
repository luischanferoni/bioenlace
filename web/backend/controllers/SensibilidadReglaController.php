<?php

namespace backend\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use common\models\SensibilidadRegla;
use common\models\SensibilidadReglaServicio;
use common\models\SensibilidadCategoria;
use common\models\Servicio;

/**
 * CRUD de reglas de sensibilidad (por categoría: acción + lista de servicios que la reciben).
 * Plan: web/docs/RESUMEN_TIMELINE_PACIENTE_IA.md
 */
class SensibilidadReglaController extends Controller
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
     * Lista reglas (una por categoría).
     */
    public function actionIndex()
    {
        $dataProvider = new \yii\data\ActiveDataProvider([
            'query' => SensibilidadRegla::find()->with(['categoria', 'reglaServicios.servicio'])->orderBy(['id' => SORT_ASC]),
            'pagination' => ['pageSize' => 20],
        ]);

        return $this->render('index', [
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Crear regla para una categoría (id_categoria por GET).
     */
    public function actionCreate($id_categoria)
    {
        $categoria = SensibilidadCategoria::findOne($id_categoria);
        if (!$categoria) {
            throw new NotFoundHttpException('Categoría no encontrada.');
        }
        $existente = SensibilidadRegla::findOne(['id_categoria' => $id_categoria]);
        if ($existente) {
            return $this->redirect(['update', 'id' => $existente->id]);
        }

        $model = new SensibilidadRegla();
        $model->id_categoria = (int) $id_categoria;
        $model->accion = SensibilidadRegla::ACCION_GENERALIZAR;

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            $this->guardarServicios($model);
            return $this->redirect(['view', 'id' => $model->id]);
        }

        $servicios = Servicio::find()->orderBy('nombre')->all();
        return $this->render('create', [
            'model' => $model,
            'categoria' => $categoria,
            'servicios' => $servicios,
        ]);
    }

    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            $this->guardarServicios($model);
            return $this->redirect(['view', 'id' => $model->id]);
        }

        $servicios = Servicio::find()->orderBy('nombre')->all();
        return $this->render('update', [
            'model' => $model,
            'servicios' => $servicios,
        ]);
    }

    public function actionDelete($id)
    {
        $this->findModel($id)->delete();
        return $this->redirect(['index']);
    }

    /**
     * Guarda la lista de id_servicio desde POST (ids_servicios[]).
     */
    protected function guardarServicios(SensibilidadRegla $regla)
    {
        $ids = Yii::$app->request->post('ids_servicios', []);
        if (!is_array($ids)) {
            $ids = [];
        }
        $ids = array_map('intval', array_filter($ids));

        SensibilidadReglaServicio::deleteAll(['id_regla' => $regla->id]);
        foreach ($ids as $id_servicio) {
            $rs = new SensibilidadReglaServicio();
            $rs->id_regla = $regla->id;
            $rs->id_servicio = $id_servicio;
            $rs->save(false);
        }
    }

    /**
     * @param int $id
     * @return SensibilidadRegla
     * @throws NotFoundHttpException
     */
    protected function findModel($id)
    {
        if (($model = SensibilidadRegla::findOne($id)) !== null) {
            return $model;
        }
        throw new NotFoundHttpException('La regla solicitada no existe.');
    }
}
