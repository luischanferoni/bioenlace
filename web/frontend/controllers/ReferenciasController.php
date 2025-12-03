<?php

namespace frontend\controllers;

use common\models\Consulta;
use common\models\Servicio;
use Yii;
use common\models\Referencia;
use common\models\ConsultaDerivaciones;
use common\models\busquedas\ReferenciasBusquedas;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use webvimark\modules\UserManagement\models\User;

use yii\helpers\Json;

/**
 * ReferenciasController implements the CRUD actions for referencia model.
 */
class ReferenciasController extends Controller
{
    public function behaviors()
    {
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
     * Lists all referencia models.
     * @return mixed
     */
    public function actionIndex()
    {
        $efector = Yii::$app->user->getIdEfector();
        $servicio = Yii::$app->user->getServicioActual();
        $nombre = Servicio::find()->where('id_servicio = '.$servicio)->one();
        if($nombre->nombre == 'ADMINISTRAR EFECTOR' or $nombre->nombre == 'ADMINISTRACION') $servicio = false;
        $searchModel = new ConsultaDerivaciones();
        $dataProvider = $searchModel->porEfectorPorServicio($efector,$servicio);

        return $this->render('index', [
            'searchModel'  => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single referencia model.
     * @param string $id
     * @return mixed
     */
    public function actionView($id,$idc)
    {
         $model = new Referencia();
        $persona = Referencia::getDatosPersonaxIdConsulta($idc);
        return $this->render('view', [
            'model' => $this->findModel($id),
             'persona' => $persona,
        ]);
    }

    /**
     * Creates a new referencia model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate($idc)
    {
        $model = new Referencia();
        $model_servicios = new \common\models\Servicio();
        $persona = Referencia::getDatosPersonaxIdConsulta($idc);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {

            // $this->generarSMS('nuevo', $model->id_efector_referenciado);

            return $this->redirect(['view', 'id' => $model->id_referencia, 'idc' => $idc]);
        } else {
            return $this->render('create', [
                'model' => $model,
                 'model_servicios' => $model_servicios,
                 'persona' => $persona,
            ]);
        }
    }

    /**
     * Updates an existing referencia model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param string $id
     * @return mixed
     */
    public function actionUpdate($id, $idc)
    {
        $model = $this->findModel($id);
        $persona = Referencia::getDatosPersonaxIdConsulta($idc);
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            // $this->generarSMS('actualizacion', $model->id_efector_referenciado);
            return $this->redirect(['view', 'id' => $model->id_referencia, 'idc' => $model->id_consulta]);
        } else {
            return $this->render('update', [
                'model' => $model,
                'persona' => $persona,
            ]);
        }
    }

    /**
     * Deletes an existing referencia model.
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
     * Finds the referencia model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param string $id
     * @return referencia the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Referencia::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }

    public function actionServicios() {
        $id_efector_referenciado = $_POST['id_efector_referenciado'];
        $id = isset($_POST['id_servicio']) ? $_POST['id_servicio'] : NULL;
        $out = ['more' => false];
//        if (!is_null($id_efector_referenciado)) {
        if (isset($id_efector_referenciado) && ($id_efector_referenciado!=0)) {
            $sql = 'SELECT s.id_servicio as id, s.nombre as text
                    FROM servicios s
                    INNER JOIN servicios_efector se ON (s.id_servicio = se.id_servicio)
                    WHERE se.id_efector = ' . $id_efector_referenciado;

            $command = Yii::$app->db->createCommand($sql);
            $data = $command->queryAll();
            $out['results'] = array_values($data);
        } elseif ($id > 0) {
            $out['results'] = ['id' => $id, 'text' => Servicio::find($id)->nombre];
            $out['selected'] = ['id' => $id, 'text' => Servicio::find($id)->nombre];
        } else {
            //$out['results'] = ['id' => 0, 'text' => 'No matching records found'];
            // no se ha seleccionado nigun efector
            $out['results'] = [];
        }
        if ($id > 0) {
            $out['results'] = ['id' => $id, 'text' => Servicio::find($id)->nombre];
            $out['selected'] = ['id' => $id, 'text' => Servicio::find($id)->nombre];
        }

        echo Json::encode($out);
        //return;
    }

    /*
    * Esta es para enviar a los administradores de que existe una nueva referencia
    */
    // private function generarSMS($tipo, $id_efector)
    // {
    //     $usuarios = User::getPorRol('Administrativo', $id_efector);

    //     $telefonos = [];
    //     foreach ($usuarios as $key => $u) {
    //         $telefonos[] = $u['telefono'];
    //     }

    //     $mensaje = "Actualizacion";
    //     if($tipo = 'nuevo'){
    //         $mensaje = "Nuevo";
    //     }

    //     Yii::$app->miciudad->enviarSMS($telefonos, $mensaje);
    // }

}
