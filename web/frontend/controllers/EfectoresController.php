<?php

namespace frontend\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use common\models\ValidarArchivo; //incluyo el modelo que me permite validar el archivo
use yii\web\UploadedFile; //incluyo la extensión para cargar el archivo
use yii\helpers\Json;

use common\models\Efector;
use common\models\busquedas\EfectorBusqueda;
use common\models\busquedas\RrhhEfectorBusqueda;

//agregamos el modulo de la extension para el control de acceso
use webvimark\modules\UserManagement\UserManagementModule;

/**
 *
 * La clase EfectoresController implementa las action que posibilitan la gestión de
 * efectores de la bd SISSE.
 */
class EfectoresController extends Controller
{
    public function behaviors()
    {
        //control de acceso mediante la extensión
        return [
            'ghost-access' => [
                'class' => 'frontend\components\SisseGhostAccessControl',
                'except' => ['search']
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
     * Lists all Efector models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new EfectorBusqueda();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,

        ]);
    }

    /**
     * Displays a single Efector model.
     * @param integer $id
     * @return mixed
     */
    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Updates an existing Efector model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id_efector]);
        } else {
            return $this->render('update', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Finds the Efector model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Efector the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Efector::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('La pagina solicitada no existe.');
        }
    }

    /**
     * Lista los efectores del usuario actual
     * @tags efectores,listar,ver todos
     * @keywords listar,ver todos,mostrar,efectores
     * @synonyms efectores,centros de salud,establecimientos
     */
    public function actionIndexuserefector()
    {
        $this->layout = 'main_sinmenuizquierda';
        $searchModel = new EfectorBusqueda();
        $array_efectores = Yii::$app->user->getEfectores() ?? [];
        $dataProvider = $searchModel->search(['EfectorBusqueda' => ['efectores' => array_keys($array_efectores)]]);

        return $this->render('indexuserefector', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionSearch($q = null)
    {
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $out = ['results' => ['id' => '', 'text' => '']];
        
        // Obtener query de GET o POST
        if ($q === null) {
            $q = Yii::$app->request->get('q') ?: Yii::$app->request->post('q');
        }
        
        // Recopilar todos los filtros de los parámetros GET/POST
        $filters = [];
        $request = Yii::$app->request;
        
        // Filtros de localización
        if ($request->get('id_localidad') || $request->post('id_localidad')) {
            $filters['id_localidad'] = $request->get('id_localidad') ?: $request->post('id_localidad');
        }
        if ($request->get('id_departamento') || $request->post('id_departamento')) {
            $filters['id_departamento'] = $request->get('id_departamento') ?: $request->post('id_departamento');
        }
        if ($request->get('localidad_nombre') || $request->post('localidad_nombre')) {
            $filters['localidad_nombre'] = $request->get('localidad_nombre') ?: $request->post('localidad_nombre');
        }
        if ($request->get('departamento_nombre') || $request->post('departamento_nombre')) {
            $filters['departamento_nombre'] = $request->get('departamento_nombre') ?: $request->post('departamento_nombre');
        }
        
        // Filtro por servicio
        if ($request->get('id_servicio') || $request->post('id_servicio')) {
            $filters['id_servicio'] = $request->get('id_servicio') ?: $request->post('id_servicio');
        }
        
        // Filtros de características del efector
        if ($request->get('dependencia') || $request->post('dependencia')) {
            $filters['dependencia'] = $request->get('dependencia') ?: $request->post('dependencia');
        }
        if ($request->get('tipologia') || $request->post('tipologia')) {
            $filters['tipologia'] = $request->get('tipologia') ?: $request->post('tipologia');
        }
        if ($request->get('estado') || $request->post('estado')) {
            $filters['estado'] = $request->get('estado') ?: $request->post('estado');
        }
        
        // Filtro por geolocalización
        $lat = $request->get('latitud') ?: $request->post('latitud');
        $lng = $request->get('longitud') ?: $request->post('longitud');
        if ($lat && $lng) {
            $filters['latitud'] = $lat;
            $filters['longitud'] = $lng;
            $filters['radio_km'] = $request->get('radio_km') ?: $request->post('radio_km') ?: 10; // Por defecto 10 km
        }
        
        // Límite de resultados
        if ($request->get('limit') || $request->post('limit')) {
            $filters['limit'] = $request->get('limit') ?: $request->post('limit');
        }
        
        // Parámetros de ordenamiento
        if ($request->get('sort_by') || $request->post('sort_by')) {
            $filters['sort_by'] = $request->get('sort_by') ?: $request->post('sort_by');
        }
        if ($request->get('sort_order') || $request->post('sort_order')) {
            $filters['sort_order'] = $request->get('sort_order') ?: $request->post('sort_order');
        }
        
        // Si no hay query ni filtros, retornar vacío
        if (is_null($q) && empty($filters)) {
            return $out;
        }

        $data = Efector::liveSearch($q, $filters);

        return ['results' => array_values($data)];
    }
}
