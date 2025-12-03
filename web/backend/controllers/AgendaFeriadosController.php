<?php

namespace backend\controllers;

use Yii;

use yii\data\ActiveDataProvider;
use yii\base\Exception;
use yii\web\Controller;
use yii\web\UnprocessableEntityHttpException;
use yii\filters\VerbFilter;

use common\models\AgendaFeriados;
use common\models\busquedas\AgendaFeriadosBusqueda;

/**
 * AgendaFeriadosController implements the CRUD actions for AgendaFeriados model.
 */
class AgendaFeriadosController extends Controller
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
     * Lists all AgendaFeriados models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new AgendaFeriadosBusqueda();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single AgendaFeriados model.
     * @param string $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    /*public function actionView()
    {
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $desde = Yii::$app->request->get('start');
        $hasta = Yii::$app->request->get('end');

        $timeDesde = strtotime($desde);
        $mesDesde = date("F", $timeDesde);
        $diaDesde = date("D", $timeDesde);

        $timeHasta = strtotime($hasta);
        $mesHasta = date("F", $timeHasta);
        $diaHasta = date("D", $timeHasta);

        $query1 =  AgendaFeriados::find()
            ->where(['between', 'fecha', $desde, $hasta]);

        $query2 =  AgendaFeriados::find()
            ->where(['between', 'DATE_FORMAT(fecha, "%m-%d")', $mesDesde.'-'.$diaDesde, $mesHasta.'-'.$diaHasta]);

        $feriados = $query1->union($query2)->all();

        $fullcalendarEvents = [];
        foreach ($feriados as $feriado) {
            $eve = new \yii2fullcalendar\models\Event();

            $eve->id = $feriado->id;
            $eve->backgroundColor = '#f0ad4e';
            $eve->title = $feriado->titulo;

            if ($feriado->horario == AgendaFeriados::TODO_EL_DIA) {
                $eve->start = $feriado->fecha.' 00:00:00';
                $eve->end = $feriado->fecha.' 23:59:59';
            }

            if ($feriado->horario == AgendaFeriados::HASTA_MEDIODIA) {
                $eve->start = $feriado->fecha.' 00:00:00';
                $eve->end = $feriado->fecha.' 12:59:59';
            }

            if ($feriado->horario == AgendaFeriados::DESDE_MEDIODDIA) {
                $eve->start = $feriado->fecha.' 12:59:59';
                $eve->end = $feriado->fecha.' 23:59:59';
            }
            
            $fullcalendarEvents[] = $eve;
        }
        
        return $fullcalendarEvents;        
    }*/


    /**
     * Creates a new AgendaFeriados model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new AgendaFeriados();

        /*$dia = Yii::$app->request->get('dia');
        $fecha = date_create_from_format('Y-m-d', $dia);

        if (!$fecha) {
            throw new UnprocessableEntityHttpException("Formato incorrecto de fecha");
        }

        $fecha = date_format($fecha, 'd/m/Y');
        $model->fecha = $fecha;*/

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Updates an existing AgendaFeriados model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param string $id
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
     * Deletes an existing AgendaFeriados model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param string $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    /**
     * Finds the AgendaFeriados model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param string $id
     * @return AgendaFeriados the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = AgendaFeriados::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
