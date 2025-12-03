<?php

namespace frontend\controllers;

use common\models\busquedas\AlergiasBusqueda;
use common\models\busquedas\ConsultaPracticasBusqueda;
use common\models\Consulta;
use common\models\busquedas\ConsultaMotivosBusqueda;
use common\models\busquedas\ConsultaMedicamentosBusqueda;
use common\models\ConsultaPracticas;
use common\models\DiagnosticoConsulta;
use common\models\busquedas\DiagnosticoConsultasBusqueda;
use common\models\busquedas\PersonasAntecedenteBusqueda;
use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\db\Expression;
use yii\web\BadRequestHttpException;
// Valores de sesión: usar UserConfig via Yii::$app->user

class NomencladorController extends \yii\web\Controller
{
    public function actionMotivos()
    {
        $servicio = Yii::$app->user->getServicioActual();
        $efector = Yii::$app->user->getIdEfector();

        $searchModel = new ConsultaMotivosBusqueda();
        $searchModel->id_servicio = $servicio;
        return $this->render('motivos', [
            'searchModel'  => $searchModel,
            'dataProvider' => $searchModel->search(Yii::$app->request->queryParams,$servicio),
            'servicio' => $servicio,
        ]);
    }

    public function actionMedicamentos()
    {
        $servicio = Yii::$app->user->getServicioActual();
        $efector = Yii::$app->user->getIdEfector();

        $searchModel = new ConsultaMedicamentosBusqueda();
        $searchModel->id_servicio = $servicio;
        return $this->render('medicamentos', [
            'searchModel'  => $searchModel,
            'dataProvider' => $searchModel->search(Yii::$app->request->queryParams,$servicio),
            'servicio' => $servicio,
        ]);
    }

    public function actionAntecedentesp()
    {
        $servicio = Yii::$app->user->getServicioActual();
        $efector = Yii::$app->user->getIdEfector();

        $searchModel = new PersonasAntecedenteBusqueda();
        $searchModel->id_servicio = $servicio;
        return $this->render('antecedentesP', [
            'searchModel'  => $searchModel,
            'dataProvider' => $searchModel->search(Yii::$app->request->queryParams,$servicio,'Personal'),
            'servicio' => $servicio,
        ]);
    }

    public function actionAntecedentesf()
    {
        $servicio = Yii::$app->user->getServicioActual();
        $efector = Yii::$app->user->getIdEfector();

        $searchModel = new PersonasAntecedenteBusqueda();
        $searchModel->id_servicio = $servicio;
        return $this->render('antecedentesF', [
            'searchModel'  => $searchModel,
            'dataProvider' => $searchModel->search(Yii::$app->request->queryParams,$servicio,'Familiar'),
            'servicio' => $servicio,
        ]);
    }


    public function actionPracticas()
    {
        $servicio = Yii::$app->user->getServicioActual();
        $efector = Yii::$app->user->getIdEfector();

        $searchModel = new ConsultaPracticasBusqueda();
        $searchModel->id_servicio = $servicio;
        return $this->render('practicas', [
            'searchModel'  => $searchModel,
            'dataProvider' => $searchModel->search(Yii::$app->request->queryParams,$servicio),
            'servicio' => $servicio,
        ]);
    }

    public function actionDiagnosticos()
    {
        $servicio = Yii::$app->user->getServicioActual();
        $efector = Yii::$app->user->getIdEfector();

        $searchModel = new DiagnosticoConsultasBusqueda();
        $searchModel->id_servicio = $servicio;
        return $this->render('diagnosticos', [
            'searchModel'  => $searchModel,
            'dataProvider' => $searchModel->search(Yii::$app->request->queryParams,$servicio),
            'servicio' => $servicio,
        ]);
    }

    public function actionAlergias()
    {
        $servicio = Yii::$app->user->getServicioActual();
        $efector = Yii::$app->user->getIdEfector();

        $searchModel = new AlergiasBusqueda();
        $searchModel->id_servicio = $servicio;
        return $this->render('alergias', [
            'searchModel'  => $searchModel,
            'dataProvider' => $searchModel->search(Yii::$app->request->queryParams,$servicio),
        ]);
    }

}
