<?php

namespace frontend\controllers;

use Yii;
use common\models\ProfesionalEfectorServicio;
use common\models\ServiciosEfector;
use common\models\busquedas\RrhhEfectorBusqueda;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\db\Query;

/**
 * CRUD de PES por efector (URLs legacy id_rr_hh + id_efector).
 */
class RrhhEfectoresController extends Controller
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
    public function actionView($id_rr_hh, $id_efector)
    {
        return $this->render('view', [
            'model' => $this->findModel($id_rr_hh, $id_efector),
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
                $idRh = ProfesionalEfectorServicio::resolveIdRrhhForPersona((int) $model->id_persona);
                if ($idRh <= 0) {
                    return $this->redirect(['rrhh/view', 'id' => $model->id]);
                }

                return $this->redirect(['view', 'id_rr_hh' => $idRh, 'id_efector' => $model->id_efector]);
            }
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * @no_intent_catalog
     */
    public function actionUpdate($id_rr_hh, $id_efector)
    {
        $model = $this->findModel($id_rr_hh, $id_efector);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            $idRh = ProfesionalEfectorServicio::resolveIdRrhhForPersona((int) $model->id_persona);
            if ($idRh <= 0) {
                return $this->redirect(['rrhh/view', 'id' => $model->id]);
            }

            return $this->redirect(['view', 'id_rr_hh' => $idRh, 'id_efector' => $model->id_efector]);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * @no_intent_catalog
     */
    public function actionDelete($id_rr_hh, $id_efector)
    {
        $this->findModel($id_rr_hh, $id_efector)->delete();

        return $this->redirect(['index']);
    }

    /**
     * @return ProfesionalEfectorServicio
     */
    protected function findModel($id_rr_hh, $id_efector)
    {
        $idPersona = (new Query())->from('rr_hh')->select('id_persona')->where(['id_rr_hh' => (int) $id_rr_hh])->scalar();
        if ($idPersona === false || $idPersona === null) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
        $model = ProfesionalEfectorServicio::find()
            ->where([
                'id_persona' => (int) $idPersona,
                'id_efector' => (int) $id_efector,
                'deleted_at' => null,
            ])
            ->orderBy(['id' => SORT_ASC])
            ->one();
        if ($model !== null) {
            return $model;
        }
        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
