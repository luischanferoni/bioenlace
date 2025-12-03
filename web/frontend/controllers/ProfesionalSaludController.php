<?php

namespace frontend\controllers;

use Yii;
use yii\web\Controller;
use yii\base\Exception;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\helpers\Json;
use yii\helpers\ArrayHelper;

use frontend\filters\SisseActionFilter;
use common\models\ProfesionalSalud;
use common\models\Profesiones;
use common\models\Especialidades;
use common\models\busquedas\ProfesionalSaludBusqueda;

/**
 * ProfesionalSaludController implements the CRUD actions for ProfesionalSalud model.
 */
class ProfesionalSaludController extends Controller
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
            'access' => [
                'class' => SisseActionFilter::className(),
                'only' => ['create'],
                'filtrosExtra' => [SisseActionFilter::FILTRO_PACIENTE, SisseActionFilter::FILTRO_RECURSO_HUMANO],
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
     * Lists all ProfesionalSalud models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new ProfesionalSaludBusqueda();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single ProfesionalSalud model.
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
     * Crea un nuevo ProfesionalSalud.
     * @param profesiones array de id de profesiones
     * @param especialidades array de id_profesion-id_especialidad
     * 
     * El profesional puede tener asociado una profesion sin una especialidad 
     * 
     * @return mixed
     */
    public function actionCreate()
    {
        $persona = Yii::$app->session['persona'];
        $persona =  unserialize($persona);
        
        if (!$persona->id_user || $persona->id_user == 0) {
            return $this->redirect(['user/crear']);
        }

        $query_p_salud = ProfesionalSalud::find();
        $persona_profesiones_salud = $query_p_salud->andWhere(['id_persona' => $persona->id_persona])->asArray()->all();
        $persona_profesiones = ArrayHelper::getColumn($persona_profesiones_salud, 'id_profesion');

        $query_especialidades = Especialidades::find();
        $persona_especialidades = $query_especialidades
                                    ->select(['CONCAT(id_especialidad, "-", id_profesion) AS id', 'nombre'])
                                    ->where(['in', 'id_especialidad', ArrayHelper::getColumn($persona_profesiones_salud, 'id_especialidad')])
                                    ->asArray()
                                    ->all();

        $persona_especialidades = ArrayHelper::getColumn($persona_especialidades, 'id');

        if (Yii::$app->request->post()) {

            $transaction = \Yii::$app->db->beginTransaction();
            try {
                // El post de profesiones nos sirve solo para verificar 
                // las profesiones seleccionadas sin especialidad seleccionada
                $post_profesiones = Yii::$app->request->post('profesiones');
                // El siguiente array es para guardar todas las profesiones que vengan en los dos post
                $profesiones_guardadas = [];

                // Las especialidades vienen id_especialidad-id_profesion
                $post_especialidades = Yii::$app->request->post('especialidades') ? Yii::$app->request->post('especialidades') : [];

                $especialidad_profesiones_a_crear = array_diff($post_especialidades, $persona_especialidades);
                foreach($especialidad_profesiones_a_crear as $especialidad_profesion_a_crear) {
                    list($id_especialidad, $id_profesion) = explode("-", $especialidad_profesion_a_crear);
                    $profesiones_guardadas[] = $id_profesion;
                    $profesional_salud = new ProfesionalSalud();
                    $profesional_salud->id_profesion = $id_profesion;
                    $profesional_salud->id_especialidad = $id_especialidad;
                    $profesional_salud->id_persona = $persona->id_persona;
                    
                    if (!$profesional_salud->save()) {
                        var_dump($profesional_salud->getErrors());
                        throw new Exception;
                    }
                }

                // Profesiones sin especialidad
                // Obtengo las profesiones nuevas solamente 
                $profesiones_a_crear = array_diff($post_profesiones, $persona_profesiones);
                // De las profesiones nuevas saco las que no se hayan guardado ya en especialidades
                $profesiones_a_crear = array_diff($profesiones_a_crear, $profesiones_guardadas);
                if (count($profesiones_a_crear) > 0) {
                    foreach($profesiones_a_crear as $profesion_a_crear) {
                        $profesional_salud = new ProfesionalSalud();
                        $profesional_salud->id_profesion = $profesion_a_crear;
                        $profesional_salud->id_persona = $persona->id_persona;
                        
                        if (!$profesional_salud->save()) {
                            var_dump($profesional_salud->getErrors());
                            throw new Exception;
                        }
                    }
                }

                // eliminacion de las que la persona posee pero que no vengan en el post
                $profesiones_a_eliminar = array_diff($persona_especialidades, $post_especialidades);
                if (count($profesiones_a_eliminar) > 0) {
                    foreach($profesiones_a_eliminar as $profesion_a_eliminar) {
                        list($id_especialidad, $id_profesion) = explode("-", $profesion_a_eliminar);
                        $profesiones_guardadas[] = $id_profesion;
       
                        ProfesionalSalud::find()
                                ->where(
                                    ['id_profesion' => $id_profesion, 
                                    'id_especialidad' => $id_especialidad, 
                                    'id_persona' => $persona->id_persona])
                                ->one()
                                ->delete();
                    }
                }

                $profesiones_a_eliminar = array_diff($persona_profesiones, $post_profesiones);
                if (count($profesiones_a_eliminar) > 0) {
                    foreach($profesiones_a_eliminar as $profesion_a_eliminar) {
                        ProfesionalSalud::find()
                                ->where(
                                    ['id_profesion' => $profesion_a_eliminar,
                                    'id_persona' => $persona->id_persona])
                                ->one()
                                ->delete();
                    }
                }

                $transaction->commit();
                return $this->redirect(['rrhh-efector/create', 'id_persona' => $persona->id_persona]);

            } catch (Exception $e) {
                echo "sdfsd";
                var_dump($e->getMessage());die;
                $transaction->rollBack();
            }            
        }

        return $this->render('create', [
            'persona' => $persona,
            'persona_profesiones' => $persona_profesiones,
            'persona_especialidades' => $persona_especialidades,
        ]);
    }

    /**
     * Updates an existing ProfesionalSalud model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
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
     * Deletes an existing ProfesionalSalud model.
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
     * Finds the ProfesionalSalud model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return ProfesionalSalud the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = ProfesionalSalud::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }

     /**
     * Funcion para ejecutar los select dependientes de profesiones y especialidades
     * @return type
     */
    public function actionEspecialidades()
    {
        $out = [];
       
        if (!isset($_POST['depdrop_parents']) || is_null($_POST['depdrop_parents'])) {
            echo Json::encode(['output' => $out, 'selected' => '']);
        }

        // depdrop_parents es un array
        $profesiones_id = $_POST['depdrop_parents'][0];
        $especialidades = Especialidades::find()
                                ->select(['CONCAT(id_especialidad, "-", id_profesion) AS id', 'nombre AS name'])
                                ->where(['in', 'id_profesion', $profesiones_id])
                                ->asArray()
                                ->all();
        
        $out = $especialidades;

        $especialidades_seleccionadas = json_decode($_POST['depdrop_all_params']['especialidades_seleccionadas']);

        echo Json::encode(['output' => $out, 'selected' => $especialidades_seleccionadas]);

        return;
    }
}
