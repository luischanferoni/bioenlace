<?php

namespace frontend\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\helpers\Json;
use yii\helpers\ArrayHelper;

use common\models\ProfesionalEfectorServicio;
use common\components\Services\ProfesionalEfectorServicio\ProfesionalEnEfectorListadoUiService;
use common\models\busquedas\RrhhEfectorBusqueda;
use common\models\Persona;
use common\models\Efector;
use common\models\Servicio;
use common\models\ServiciosEfector;

/**
 * CRUD de asignaciones PES (`profesional_efector_servicio`).
 */
class RrhhController extends Controller
{
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['post'],
                ],
            ],
        ];
    }

    /**
     * Lista asignaciones profesional–efector–servicio.
     * @no_intent_catalog
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
     * @no_intent_catalog
    */
    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * @no_intent_catalog
    */
    public function actionCreate($idp = null)
    {
        $model = new ProfesionalEfectorServicio();
        if ($idp !== null) {
            $model->id_persona = (int) $idp;
        }

        if ($model->load(Yii::$app->request->post())) {
            if ((int) $model->id_servicio <= 0 && (int) $model->id_efector > 0) {
                $firstSe = ServiciosEfector::find()
                    ->where(['id_efector' => (int) $model->id_efector])
                    ->orderBy(['id_servicio' => SORT_ASC])
                    ->one();
                if ($firstSe !== null) {
                    $model->id_servicio = (int) $firstSe->id_servicio;
                }
            }
            if ($model->save()) {
                return $this->redirect(['view', 'id' => $model->id]);
            }
        }

        $model_persona = $idp ? Persona::findOne($idp) : null;
        return $this->render('create', [
            'model' => $model,
            'model_persona' => $model_persona,
        ]);
    }

    /**
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
            'model_persona' => $model->persona,
        ]);
    }

    /**
     * @no_intent_catalog
    */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();
        return $this->redirect(['index']);
    }

    /**
     * @param string|int $id PK de `profesional_efector_servicio`
     */
    protected function findModel($id): ProfesionalEfectorServicio
    {
        $model = ProfesionalEfectorServicio::findOne(['id' => (int) $id, 'deleted_at' => null]);
        if ($model !== null) {
            return $model;
        }
        throw new NotFoundHttpException('The requested page does not exist.');
    }

    /**
     * Select dependiente: servicios / filas PES en el efector.
     * @no_intent_catalog
    */
    public function actionSubcatservicios()
    {
        $out = [];
        if (isset($_POST['depdrop_parents']) && $_POST['depdrop_parents'] != null) {
            $cat_id = $_POST['depdrop_parents'][0];
            $items = ProfesionalEfectorServicio::find()
                ->where(['id_efector' => $cat_id, 'deleted_at' => null])
                ->with('servicio')
                ->all();
            foreach ($items as $item) {
                if ($item->servicio) {
                    $out[] = ['id' => $item->id, 'name' => $item->servicio->nombre];
                }
            }
            $seen = [];
            $out = array_values(array_filter($out, function ($x) use (&$seen) {
                if (isset($seen[$x['id']])) {
                    return false;
                }
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

    /**
     * DepDrop: profesionales por efector + servicio (JSON).
     * @no_intent_catalog
     */
    public function actionProfesionalesPorServicioEfector()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $out = [];

        if (isset($_POST['depdrop_parents'])) {
            $parents = $_POST['depdrop_parents'];
            if ($parents != null) {
                $idEfector = $parents[0];
                $idServicio = $parents[1];

                $profesionales = ProfesionalEfectorServicio::obtenerMedicosPorServicioEfector($idEfector, $idServicio);
                $arrayEfectores = ArrayHelper::map($profesionales, 'id_rr_hh', 'datos');

                foreach ($arrayEfectores as $key => $value) {
                    $out[] = ['id' => $key, 'name' => $value];
                }

                return ['output' => $out, 'selected' => ''];
            }
        }

        return ['output' => '', 'selected' => ''];
    }

    /**
     * Autocomplete Select2 (misma forma que API /api/v1/recurso-humano/autocomplete).
     * @no_intent_catalog
     */
    public function actionRrhhAutocomplete($q = null)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $request = Yii::$app->request;
        $idEfector = $request->get('id_efector') ?: $request->post('id_efector');
        $idServicio = $request->get('id_servicio') ?: $request->post('id_servicio');
        if ($idEfector === null || $idEfector === '' || $idServicio === null || $idServicio === '') {
            return ['results' => []];
        }
        $q = $q ?? $request->get('q') ?? $request->post('q');
        if ($q === null || trim((string) $q) === '') {
            return ['results' => []];
        }
        $filters = [
            'id_efector' => $idEfector,
            'id_servicio' => $idServicio,
        ];

        return ['results' => array_values(ProfesionalEnEfectorListadoUiService::autocompletePorEfectorServicio((string) $q, $filters))];
    }
}
