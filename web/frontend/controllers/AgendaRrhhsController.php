<?php

namespace frontend\controllers;

use Yii;

use common\models\Agenda_rrhh;
use common\models\busquedas\Agenda_rrhhBusqueda;
use common\models\Persona;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\web\Response;
use yii\widgets\ActiveForm;
use common\models\Tipo_dia;

/**
 * AgendaRrhhsController implements the CRUD actions for Agenda_rrhh model.
 */
class AgendaRrhhsController extends Controller {

    public function behaviors() {
         //control de acceso mediante la extensión
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
     * Lists all Agenda_rrhh models.
     * @return mixed
     */
    public function actionIndex() 
    {
        $searchModel = new Agenda_rrhhBusqueda();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
                    'searchModel' => $searchModel,
                    'dataProvider' => $dataProvider,
        ]);
    }

        /**
     * Displays a single Agenda_rrhh model.
     * @param string $id
     * @return mixed
     */
    public function actionView($id)
    {
        return $this->render('view', [
                    'model' => $this->findModel($id),
        ]);
    }

    /**
     * Creates a new Agenda_rrhh model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new Agenda_rrhh();
        $model->id_efector = yii::$app->user->getIdEfector();
        $idRrhh = Yii::$app->request->get('id');
        
        if(isset($idRrhh)){
            $model->id_rr_hh = Yii::$app->request->get('id');
        }
        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            $model->save();
            return $this->redirect(['view', 'id' => $model->id_agenda_rrhh]);
        } else {
            return $this->render('create', [
                        'model' => $model,
            ]);
        }
    }

    /**
     * Updates an existing Agenda_rrhh model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param string $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id_agenda_rrhh]);
        } else {
            return $this->render('update', [
                        'model' => $model,
            ]);
        }
    }

    /**
     * Deletes an existing Agenda_rrhh model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param string $id
     * @return mixed
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    /**
     * Finds the Agenda_rrhh model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param string $id
     * @return Agenda_rrhh the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Agenda_rrhh::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }

    public function actionJsoncalendar($start = NULL, $end = NULL, $_ = NULL)
    {

        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $times = \frontend\modules\timetrack\models\Timetable::find()->where(array('category' => \frontend\modules\timetrack\models\Timetable::CAT_TIMETRACK))->all();

        $eventos = array();

        foreach ($times AS $time) {
            //Testing
            $event = new \yii2fullcalendar\models\Event();
            $event->id = $time->id;
            $event->title = $time->categoryAsString;
            $event->start = date('Y-m-d\TH:i:s\Z', strtotime($time->date_start . ' ' . $time->time_start));
            $event->end = date('Y-m-d\TH:i:s\Z', strtotime($time->date_end . ' ' . $time->time_end));
            $eventos[] = $event;
        }

        return $eventos;
    }

}
