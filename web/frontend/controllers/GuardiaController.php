<?php

namespace frontend\controllers;

use Yii;
use common\models\Guardia;
use common\models\busquedas\GuardiaBusqueda;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;

use common\models\CoberturaMedica;
use common\models\Telefono;

/**
 * GuardiaController implements the CRUD actions for Guardia model.
 */
class GuardiaController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            /*'ghost-access' => [
                'class' => 'frontend\components\SisseGhostAccessControl',
            ],*/
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Lists all Guardia models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new GuardiaBusqueda();
        $dataProvider = $searchModel->searchNoFinalizadas(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Libro de Guardia.
     * @return mixed
     */
    public function actionLibroGuardia()
    {
        $searchModel = new GuardiaBusqueda();
        $dataProvider = $searchModel->searchLibro(Yii::$app->request->queryParams);

        return $this->render('libroGuardia', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single Guardia model.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Creates a new Guardia model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new Guardia();

        $persona = Yii::$app->session['persona'];
        $persona =  unserialize($persona);

        if(!isset($persona->id_persona)) {
            \Yii::$app->getSession()->setFlash('info', '<b>Debe seleccionar un paciente previamente.</b>');
            return $this->redirect(['personas/buscar-persona']);
        }
        //Solicitamos coberturas activas para el paciente seleccionado.
        $coberturas_api = [];
        $cobertura_medica_key = sprintf("cobertura_medica_%s", $persona->id_persona);
        $telefono = new Telefono();
        $filtro_cobertura = null;
        $coberturas_count = 0; //count($coberturas_api);
        $cobertura_default = null;
        if($coberturas_count > 0) {
            $filtro_cobertura = ArrayHelper::getColumn($coberturas_api, 'codigo');
            if($coberturas_count == 1) {
                $cobertura_default = $filtro_cobertura[0];
            }
        }
        $coberturas = CoberturaMedica::getCoberturasForSelect($filtro_cobertura);
        $coberturas = ArrayHelper::map($coberturas, 'codigo', 'nombre');

        if($cobertura_default !== null) {
            $model->obra_social = $cobertura_default;
        }
        
        $model->id_efector = Yii::$app->user->getIdEfector();
        $model->id_rr_hh = Yii::$app->user->getIdRecursoHumano();
        $model->fecha = date("d/m/Y");
        $telefono = new Telefono();

        if ($model->load(Yii::$app->request->post())) {

            $telefono->load(Yii::$app->request->post());

            $model->datos_contacto_tel = '';

            if ($model->ingresa_con == 'familiar' || $model->ingresa_con == 'otro' || $model->ingresa_con == 'policia') {

                $telefono->scenario = Telefono::VALIDAR_TELEFONO;
                $validarTelefono = $telefono->validate();

                if ($validarTelefono) {
                    $model->datos_contacto_tel = $telefono->prefijo . $telefono->codArea . $telefono->numTelefono;
                }
            }
            $model->scenario = Guardia::INGRESO_PACIENTE;
            $validar = $model->validate();

            if ($validar) {

                if ($model->save()) {
                    return $this->redirect(['view', 'id' => $model->id]);
                }

            }
        }

        return $this->render('create', [
            'model' => $model,
            'persona' => $persona,
            'telefono' => $telefono,
            'coberturas' => $coberturas,
        ]);
    }

    /**
     * Updates an existing Guardia model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionFinalizar($id)
    {
        $model = $this->findModel($id);
        $model->fecha_fin = date("d/m/Y");

        if ($model->load(Yii::$app->request->post())) {
            
            $model->scenario = Guardia::EGRESO_PACIENTE;
            $validar = $model->validate();

            if ($validar) {
                if ($model->save()) {
                    return $this->redirect(['view', 'id' => $model->id]);
                }
            }
        }
        return $this->render('finalizar', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing Guardia model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    /**
     * Finds the Guardia model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Guardia the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Guardia::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
