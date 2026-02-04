<?php

namespace frontend\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\helpers\Json;

use common\models\RrhhEfector;
use common\models\busquedas\RrhhEfectorBusqueda;
use common\models\Persona;
use common\models\Efector;
use common\models\Servicio;

/**
 * RrhhController implementa el CRUD para el modelo RrhhEfector (recursos humanos por efector).
 */
class RrhhController extends Controller
{
    public function behaviors()
    {
        return [
            'ghost-access' => [
                'class' => 'frontend\components\SisseGhostAccessControl',
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['post'],
                ],
            ],
        ];
    }

    /**
     * Lista todos los modelos RrhhEfector.
     */
    public function actionIndex()
    {
        $searchModel = new RrhhEfectorBusqueda();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Muestra un modelo RrhhEfector.
     */
    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Crea un nuevo RrhhEfector.
     */
    public function actionCreate($idp = null)
    {
        $model = new RrhhEfector();
        if ($idp !== null) {
            $model->id_persona = $idp;
        }

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id_rr_hh]);
        }

        $model_persona = $idp ? Persona::findOne($idp) : null;
        return $this->render('create', [
            'model' => $model,
            'model_persona' => $model_persona,
        ]);
    }

    /**
     * Actualiza un RrhhEfector existente.
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id_rr_hh]);
        }

        return $this->render('update', [
            'model' => $model,
            'model_persona' => $model->persona,
        ]);
    }

    /**
     * Elimina un RrhhEfector.
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();
        return $this->redirect(['index']);
    }

    /**
     * @param string $id id_rr_hh
     * @return RrhhEfector
     * @throws NotFoundHttpException
     */
    protected function findModel($id)
    {
        if (($model = RrhhEfector::findOne($id)) !== null) {
            return $model;
        }
        throw new NotFoundHttpException('The requested page does not exist.');
    }

    /**
     * Select dependiente: servicios por efector.
     */
    public function actionSubcatservicios()
    {
        $out = [];
        if (isset($_POST['depdrop_parents']) && $_POST['depdrop_parents'] != null) {
            $cat_id = $_POST['depdrop_parents'][0];
            $items = RrhhEfector::find()
                ->joinWith('rrhhServicio')
                ->andWhere(['rrhh_efector.id_efector' => $cat_id])
                ->andWhere('rrhh_servicio.deleted_at IS NULL')
                ->all();
            foreach ($items as $item) {
                foreach ($item->rrhhServicio as $rs) {
                    if ($rs->servicio) {
                        $out[] = ['id' => $rs->id, 'name' => $rs->servicio->nombre];
                    }
                }
            }
            $seen = [];
            $out = array_values(array_filter($out, function ($x) use (&$seen) {
                if (isset($seen[$x['id']])) return false;
                $seen[$x['id']] = true;
                return true;
            }));
            echo Json::encode(['output' => $out, 'selected' => '']);
            return;
        }
        if (isset($_POST['id_efector'])) {
            $servicios = Servicio::find()
                ->join('INNER JOIN', 'servicios_efector', 'servicios.id_servicio = servicios_efector.id_servicio')
                ->where(['servicios_efector.id_efector' => $_POST['id_efector']])
                ->all();
            foreach ($servicios as $s) {
                echo "<option value=\"{$s->id_servicio}\">{$s->nombre}</option>";
            }
            return;
        }
        echo Json::encode(['output' => '', 'selected' => '']);
    }

    public function actionRrhhAutocomplete($q = null)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $out = ['results' => ['id' => '', 'text' => '']];
        $q = $q ?? Yii::$app->request->get('q') ?? Yii::$app->request->post('q');
        $filters = [];
        $request = Yii::$app->request;
        if ($request->get('id_efector') || $request->post('id_efector')) {
            $filters['id_efector'] = $request->get('id_efector') ?: $request->post('id_efector');
        }
        if ($request->get('efector_nombre') || $request->post('efector_nombre')) {
            $filters['efector_nombre'] = $request->get('efector_nombre') ?: $request->post('efector_nombre');
        }
        if ($request->get('id_servicio') || $request->post('id_servicio')) {
            $filters['id_servicio'] = $request->get('id_servicio') ?: $request->post('id_servicio');
        }
        if ($request->get('servicio_nombre') || $request->post('servicio_nombre')) {
            $filters['servicio_nombre'] = $request->get('servicio_nombre') ?: $request->post('servicio_nombre');
        }
        if ($request->get('limit') || $request->post('limit')) {
            $filters['limit'] = $request->get('limit') ?: $request->post('limit');
        }
        if ($request->get('sort_by') || $request->post('sort_by')) {
            $filters['sort_by'] = $request->get('sort_by') ?: $request->post('sort_by');
        }
        if ($request->get('sort_order') || $request->post('sort_order')) {
            $filters['sort_order'] = $request->get('sort_order') ?: $request->post('sort_order');
        }
        if ($q === null && empty($filters)) {
            return $out;
        }
        $out['results'] = array_values(RrhhEfector::autocompleteRrhh($q, $filters));
        return $out;
    }
}
