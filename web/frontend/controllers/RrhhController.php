<?php

namespace frontend\controllers;

use Yii;

use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\helpers\BaseJson;
use yii\helpers\Json;

use common\models\Rrhh;
use common\models\busquedas\RrhhBusqueda;
use common\models\Persona;
use common\models\Efector;
use common\models\Condiciones_laborales;
use common\models\Servicio;
use common\models\Profesiones;
use common\models\Rrhh_efector;

/**
 * RrhhController implements the CRUD actions for Rrhh model.
 */
class RrhhController extends Controller
{
    public function behaviors()
    {
         //control de acceso mediante la extension
        return [
            'ghost-access'=> [
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
     * Lists all Rrhh models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new RrhhBusqueda();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single Rrhh model.
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
     * Creates a new Rrhh model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate($idp)
    {

        $model = new Rrhh();
        $persona = new Persona();
        $model_persona = $persona::findOne($idp);
        $model_profesiones = new Profesiones();
        //$model_efector = new Efector();
        $model_condiciones_laborales = new Condiciones_laborales();
        $model_rr_hh_efector = new Rrhh_efector();
        $model_servicios = new Servicio();
//         var_dump(Yii::$app->request->post());die;

        //if ($model->load(Yii::$app->request->post()) && $model->save()) {
        if ($model->load(Yii::$app->request->post()) 
                && $model_rr_hh_efector->load(Yii::$app->request->post())) {
            //&& $model_efector->load(Yii::$app->request->post())
           // var_dump($model_rr_hh_efector->load(Yii::$app->request->post()));
           // print_r($model_rr_hh_efector);
           // die;
               
               /* $id_condicion_laboral=Yii::$app->request->post('Condiciones_laborales');
                $horario=Yii::$app->request->post('Rrhh_efector');
                $servicio=Yii::$app->request->post('Servicios');
                */
                // Guardo los datos del model RRHH
                $model->save();
                $model_rr_hh_efector->id_rr_hh = $model->id_rr_hh;
               // $model_rr_hh_efector->id_efector = $model_->id_efector;
                //$model_rr_hh_efector->id_condicion_laboral = $model_condiciones_laborales->id_condicion_laboral;
                // $model_rr_hh_efector->id_condicion_laboral = $id_condicion_laboral['id_condicion_laboral'];
                //$model_rr_hh_efector->id_servicio = $servicio['id_servicio'];
                //$model_rr_hh_efector->horario = $horario['horario'];
                // Guardo los datos del model RR_HH_EFECTORES
                $model_rr_hh_efector->save();
//                 $mje= $id_condicion_laboral['id_condicion_laboral'];

                return $this->redirect(['view', 'id' => $model->id_rr_hh]);
        } else {
            return $this->render('create', [
                'model' => $model,
                'model_persona' => $model_persona,
                //'model_efector' => $model_efector,
                //'model_condiciones_laborales' => $model_condiciones_laborales ,
                'model_rr_hh_efector' => $model_rr_hh_efector,
                //'model_servicios' => $model_servicios,
            ]);
        }
    }

    /**
     * Updates an existing Rrhh model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param string $id
     * @return mixed
     */
    public function actionUpdate($id, $idp)
    {
        $model = $this->findModel($id);
        $persona = new Persona();
        $model_persona = $persona::findOne($idp);
       /* $model_efector = new Efector();
        $model_condiciones_laborales = new Condiciones_laborales();*/
        $model_rr_hh_efector = new \common\models\Rrhh_efector();
        /*$model_servicios = new \common\models\Servicio();
        $model_especialidades = new \common\models\Especialidades();*/
        
        if ($model->load(Yii::$app->request->post()) 
            && $model_rr_hh_efector->load(Yii::$app->request->post())){
            /*&& $model_efector->load(Yii::$app->request->post())
                && $model_condiciones_laborales->load(Yii::$app->request->post())*/
               /* $id_condicion_laboral=Yii::$app->request->post('Condiciones_laborales');
                $horario=Yii::$app->request->post('Rrhh_efector');
                $servicio=Yii::$app->request->post('Servicios');
                $especialidades=Yii::$app->request->post('Especialidades');*/
                
                // Guardo los datos del model RRHH
//                $model = \common\models\RrHh::findOne($id);
//                $model->id_especialidad=$model->id_especialidad;
                $model->update();
                 print_r($model->getErrors());
                $model_rr_hh_efector = \common\models\Rrhh_efector::findOne($model->id_rr_hh);
//                $model_rr_hh_efector->id_rr_hh = $model->id_rr_hh;
               /* $model_rr_hh_efector->id_efector = $model_efector->id_efector;
                //$model_rr_hh_efector->id_condicion_laboral = $model_condiciones_laborales->id_condicion_laboral;
                $model_rr_hh_efector->id_condicion_laboral = $id_condicion_laboral['id_condicion_laboral'];
                $model_rr_hh_efector->id_servicio = $servicio['id_servicio'];
                $model_rr_hh_efector->horario = $horario['horario'];*/
                // Guardo los datos del model RR_HH_EFECTORES
                $model_rr_hh_efector->update();
                 print_r($model_rr_hh_efector->getErrors());
                
                
            return $this->redirect(['view', 'id' => $model->id_rr_hh]);
        } else {
            return $this->render('update', [
                'model' => $model,
                'model_persona' => $model_persona,
               /* 'model_efector' => $model_efector,
                'model_condiciones_laborales' => $model_condiciones_laborales ,*/
                'model_rr_hh_efector' => $model_rr_hh_efector,
                // 'model_servicios' => $model_servicios,
            ]);
        }
    }

    /**
     * Deletes an existing Rrhh model.
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
     * Finds the Rrhh model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param string $id
     * @return Rrhh the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Rrhh::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
    /**
     * Funcion para ejecutar los select dependientes de profesiones y especialidades
     * @return type
     */
     public function actionSubcat() {
        $out = [];
        if (isset($_POST['depdrop_parents'])) {
            $parents = $_POST['depdrop_parents'];
            if ($parents != null) {
                $cat_id = $parents[0];
                $out = \common\models\Rrhh:: getListaEspecialidadesXprofesion($cat_id);
//            $departamento=ArrayHelper::map(Departamento::find()->asArray()->all(), 'id_departamento', 'nombre');
                // the getSubCatList function will query the database based on the
                // cat_id and return an array like below:
                // [
                //    ['id'=>'<sub-cat-id-1>', 'name'=>'<sub-cat-name1>'],
                //    ['id'=>'<sub-cat_id_2>', 'name'=>'<sub-cat-name2>']
                // ]
                //echo Json::encode(['output'=>$out, 'selected'=>'']);
//            $params = $_POST['depdrop_params'];
                echo Json::encode(['output' => $out, 'selected' => '']);
                return;
            }
        }
        if (isset($_POST['id_profesion'])) {
//         $out = \common\models\Persona::getDepartamentoxidprovincia($_POST['id_provincia']); 
//         $params = $_POST['depdrop_params'];
//        echo Json::encode(['output'=>$out, 'selected'=>'']);
            $countDptos = \common\models\Especialidades::find()
                    ->where(['id_profesion' => $_POST['id_profesion']])
                    ->count();
            $espec = \common\models\Especialidades::find()
                     ->where(['id_profesion' => $_POST['id_profesion']])
                    ->all();
            if ($countDptos > 0) {
                foreach ($espec as $esp) {
                    $selected = ($esp->id_especialidad == $_POST['id_especialidad']) ? "selected" : "";
                    echo "<option value='$esp->id_especialidad' $selected >" . $esp->nombre . "</option>";
                }
            }
            return;
        }
        echo Json::encode(['output' => '', 'selected' => '']);
    }
    /**
     * Funcion para ejecutar los select dependientes de efectores y servicios
     * @return type
     */
     public function actionSubcatservicios() {
        $out = [];
        if (isset($_POST['depdrop_parents'])) {
            $parents = $_POST['depdrop_parents'];
            if ($parents != null) {
                $cat_id = $parents[0];
                $out = \common\models\Rrhh:: getListaServiciosXefector($cat_id);
                echo Json::encode(['output' => $out, 'selected' => '']);
                return;
}
        }
        if (isset($_POST['id_efector'])) {
            $countDptos = \common\models\Servicios::find()
                   ->join('INNER JOIN',`servicios_efector`,'servicios.id_servicio=servicios_efector.id_servicio')
                   ->where(['id_efector' => $_POST['id_efector']])
                    ->count();
            $serv = \common\models\Servicios::find()
                    ->join('INNER JOIN',`servicios_efector`,'servicios.id_servicio=servicios_efector.id_servicio')
                   ->where(['id_efector' => $_POST['id_efector']])
                    ->all();
            if ($countDptos > 0) {
                foreach ($serv as $ser) {
                    $selected = ($ser->id_servicio == $_POST['id_especialidad']) ? "selected" : "";
                    echo "<option value='$ser->id_servicio' $selected >" . $ser->nombre . "</option>";
                }
            }
            return;
        }
        echo Json::encode(['output' => '', 'selected' => '']);
    }

    public function actionRrhhAutocomplete($q = null) {

        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $out = ['results' => ['id' => '', 'text' => '']];
        
        // Obtener query de GET o POST
        if ($q === null) {
            $q = Yii::$app->request->get('q') ?: Yii::$app->request->post('q');
        }
        
        // Recopilar todos los filtros de los parámetros GET/POST
        $filters = [];
        $request = Yii::$app->request;
        
        // Filtro por profesión
        if ($request->get('id_profesion') || $request->post('id_profesion')) {
            $filters['id_profesion'] = $request->get('id_profesion') ?: $request->post('id_profesion');
        }
        if ($request->get('profesion_nombre') || $request->post('profesion_nombre')) {
            $filters['profesion_nombre'] = $request->get('profesion_nombre') ?: $request->post('profesion_nombre');
        }
        
        // Filtro por especialidad
        if ($request->get('id_especialidad') || $request->post('id_especialidad')) {
            $filters['id_especialidad'] = $request->get('id_especialidad') ?: $request->post('id_especialidad');
        }
        if ($request->get('especialidad_nombre') || $request->post('especialidad_nombre')) {
            $filters['especialidad_nombre'] = $request->get('especialidad_nombre') ?: $request->post('especialidad_nombre');
        }
        
        // Filtro por efector
        if ($request->get('id_efector') || $request->post('id_efector')) {
            $filters['id_efector'] = $request->get('id_efector') ?: $request->post('id_efector');
        }
        if ($request->get('efector_nombre') || $request->post('efector_nombre')) {
            $filters['efector_nombre'] = $request->get('efector_nombre') ?: $request->post('efector_nombre');
        }
        
        // Filtro por servicio
        if ($request->get('id_servicio') || $request->post('id_servicio')) {
            $filters['id_servicio'] = $request->get('id_servicio') ?: $request->post('id_servicio');
        }
        if ($request->get('servicio_nombre') || $request->post('servicio_nombre')) {
            $filters['servicio_nombre'] = $request->get('servicio_nombre') ?: $request->post('servicio_nombre');
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
        
        $data = \common\models\Rrhh::Autocomplete($q, $filters);

        $out['results'] = array_values($data);

        return $out;
    }        
}
