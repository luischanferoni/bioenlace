<?php

namespace frontend\controllers;

use Yii;
use common\models\PersonaProgramaDiabetes;
use common\models\ProfesionalEfectorServicio;
use common\models\ServiciosEfector;
use common\models\busquedas\ProfesionalEfectorServicioBusqueda;
use yii\web\Controller;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;
use yii\db\Query;

/**
 * CRUD de PES; query `id_profesional_efector_servicio` + `id_efector`.
 */
class ProfesionalEnEfectorController extends Controller
{
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
     * @no_intent_catalog
     */
    public function actionIndex()
    {
        $searchModel = new ProfesionalEfectorServicioBusqueda();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * @no_intent_catalog
     */
    public function actionView()
    {
        $req = Yii::$app->request;
        $params = array_merge($req->get(), $req->post());
        $idEfector = (int) ($params['id_efector'] ?? 0);
        $idPes = ProfesionalEfectorServicio::staffContextIdFromRequestParams($params);
        if ($idPes <= 0 || $idEfector <= 0) {
            throw new BadRequestHttpException('Hacen falta id_efector e id_profesional_efector_servicio.');
        }

        return $this->render('view', [
            'model' => $this->findModel($idPes, $idEfector),
        ]);
    }

    /**
     * @no_intent_catalog
     */
    public function actionCreate()
    {
        $model = new ProfesionalEfectorServicio();

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
                return $this->redirect(['view', 'id_profesional_efector_servicio' => (int) $model->id, 'id_efector' => $model->id_efector]);
            }
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * @no_intent_catalog
    */
    public function actionUpdate()
    {
        $req = Yii::$app->request;
        $params = array_merge($req->get(), $req->post());
        $idEfector = (int) ($params['id_efector'] ?? 0);
        $idPes = ProfesionalEfectorServicio::staffContextIdFromRequestParams($params);
        if ($idPes <= 0 || $idEfector <= 0) {
            throw new BadRequestHttpException('Hacen falta id_efector e id_profesional_efector_servicio.');
        }
        $model = $this->findModel($idPes, $idEfector);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id_profesional_efector_servicio' => (int) $model->id, 'id_efector' => $model->id_efector]);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * @no_intent_catalog
    */
    public function actionDelete()
    {
        $req = Yii::$app->request;
        $params = array_merge($req->get(), $req->post());
        $idEfector = (int) ($params['id_efector'] ?? 0);
        $idPes = ProfesionalEfectorServicio::staffContextIdFromRequestParams($params);
        if ($idPes <= 0 || $idEfector <= 0) {
            throw new BadRequestHttpException('Hacen falta id_efector e id_profesional_efector_servicio.');
        }
        $this->findModel($idPes, $idEfector)->delete();
        return $this->redirect(['index']);
    }

    /**
     * @return ProfesionalEfectorServicio
     */
    protected function findModel($id_profesional_efector_servicio, $id_efector)
    {
        $model = ProfesionalEfectorServicio::findOne([
            'id' => (int) $id_profesional_efector_servicio,
            'id_efector' => (int) $id_efector,
            'deleted_at' => null,
        ]);
        if ($model !== null) {
            return $model;
        }
        throw new NotFoundHttpException('The requested page does not exist.');
    }

    /**
     * DepDrop: opciones PES (`profesional_efector_servicio.id`) por efector (lista médica habitual).
     *
     * @no_intent_catalog
     */
    public function actionProfesionalesPorEfector()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $out = [];
        if (isset($_POST['depdrop_parents']) && $_POST['depdrop_parents'] != null) {
            $id_efector = $_POST['depdrop_parents'][0];
            $profesionales = ProfesionalEfectorServicio::obtenerMedicosPorEfector($id_efector);
            foreach ($profesionales as $row) {
                $out[] = ['id' => $row['id'], 'name' => $row['datos']];
            }
            return ['output' => $out, 'selected' => ''];
        }
        return ['output' => '', 'selected' => ''];
    }

    /**
     * DepDrop: opciones PES según el efector de la ficha `persona_programa_diabetes` (p. ej. dispensa).
     *
     * @no_intent_catalog
     */
    public function actionProfesionalesPorPersonaProgramaDiabetes()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $out = [];
        if (isset($_POST['depdrop_parents']) && $_POST['depdrop_parents'] != null) {
            $idPpd = (int) ($_POST['depdrop_parents'][0] ?? 0);
            if ($idPpd <= 0) {
                return ['output' => '', 'selected' => ''];
            }
            $ppd = PersonaProgramaDiabetes::findOne($idPpd);
            if ($ppd === null || !$ppd->id_efector) {
                return ['output' => '', 'selected' => ''];
            }
            foreach (ProfesionalEfectorServicio::listarOpcionesPorEfector((int) $ppd->id_efector) as $row) {
                $out[] = ['id' => $row['id'], 'name' => $row['datos']];
            }
            return ['output' => $out, 'selected' => ''];
        }
        return ['output' => '', 'selected' => ''];
    }
}
