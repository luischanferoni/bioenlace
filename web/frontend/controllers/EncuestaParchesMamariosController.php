<?php

namespace frontend\controllers;

use Yii;
use yii\base\Exception;
use yii\data\ActiveDataProvider;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;

use frontend\filters\SisseActionFilter;
use common\models\AtencionesEnfermeria;
use common\models\EncuestaParchesMamarios;
use common\models\PersonasAntecedente;
use common\models\busquedas\EncuestaParchesMamariosBusqueda;
use common\models\ConsultaAtencionesEnfermeria;
use common\models\Consulta;

/**
 * EncuestaParchesMamariosController implements the CRUD actions for EncuestaParchesMamarios model.
 */
class EncuestaParchesMamariosController extends Controller
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
            // Este es para no permitir crear una encuesta para un paciente que ya la tiene
            'access' => [
                'class' => SisseActionFilter::className(),
                'only' => ['create'],
                'filtrosExtra' => [SisseActionFilter::FILTRO_PACIENTE, SisseActionFilter::FILTRO_RECURSO_HUMANO],
                'allowed' => function () {
                    $persona = Yii::$app->session['persona'];
                    $persona =  unserialize($persona);

                    if ($persona->sexo_biologico != 1 || $persona->edad < 18) {
                        return false;
                    }

                    $query = EncuestaParchesMamarios::find();
                    $encuesta = $query->andWhere(['id_persona' => $persona->id_persona])
                            ->andWhere(['deleted_at' => null])
                            ->one();

                    if ($encuesta === null) {
                        return true;
                    }

                    if ($encuesta->resultado_indicado == 'No concluyente' || $encuesta->fechaUltimaEPM() > 330) {
                        return true;
                    }

                    return false;
                },
                'errorMessage' => 'El paciente ya tiene una encuesta cargada o no cumple ciertos requisitos',
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
     * Lists all EncuestaParchesMamarios models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new EncuestaParchesMamariosBusqueda();
        if(Yii::$app->user->getIdEfector() != 811){
            $searchModel->id_efector = Yii::$app->user->getIdEfector();
        }
                
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
                    'searchModel' => $searchModel,
                    'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single EncuestaParchesMamarios model.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {
        return $this->renderAjax('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Creates a new EncuestaParchesMamarios model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new EncuestaParchesMamarios();
        $post = Yii::$app->request->post();
        $session = Yii::$app->getSession();
        $persona = unserialize($session['persona']);

        if (!$model->load(Yii::$app->request->post()) || (!$post['162879003p'] && $post['162879003t'])) {
            return $this->render('create', [
                'model' => $model,
                'modelAtencionEnfermeria' => isset($model_a_enf) ? $model_a_enf : NULL
            ]);
        }
        // Se guarda el id de la tabla rrhh_efector
        $model->id_rr_hh = Yii::$app->user->getIdRecursoHumano();

        $transaction = \Yii::$app->db->beginTransaction();
        try {
            // En el before save se controla la session de persona y demas campos
            if ($model->save()) {

                if (!$persona) {
                    $model->addError('id', 'Hubo un error, por favor intente crear la encuesta luego');
                    throw new Exception();
                }                
                if(isset($post['162879003p']) || isset($post['162879003t'])){
                    $nuevaConsulta = new Consulta();
                    $nuevaConsulta->parent_class = Consulta::PARENT_CLASSES[Consulta::PARENT_ENCUESTA_PARCHES];
                    $nuevaConsulta->parent_id = $model->id;
                    $nuevaConsulta->id_persona = $persona->id_persona;
                    $nuevaConsulta->id_efector = $model->operador->id_efector;
                    $nuevaConsulta->id_rr_hh = $model->id_operador;
                    $nuevaConsulta->id_servicio = 5;
                    $nuevaConsulta->id_configuracion = 0;
                    $nuevaConsulta->paso_completado = 999;
                    $nuevaConsulta->save();

                    if (!$nuevaConsulta->validate()) {
                       // var_dump($nuevaConsulta->getErrors());
                        $model->addError('id', 'Hubo un error con la consulta, por favor intente crear la encuesta luego');
                        throw new Exception();
                    }

                    $model_a_enf = new ConsultaAtencionesEnfermeria();
                    $model_a_enf->id_consulta = $nuevaConsulta->id_consulta;
                    // TODO: rehacer todo esto, el id de persona va en el before save de Atenciones Enfermeria
                    $post_a_e = ['162879003p'=> $post['162879003p'], '162879003t' => $post['162879003t']];
                    $codificado = json_encode($post_a_e);
                    $model_a_enf->datos = $codificado;
                    $model_a_enf->id_persona = $persona->id_persona;
                    $model_a_enf->fecha_creacion = $model->fecha_prueba;

                    if (!$model_a_enf->validate()) {
                        //var_dump($model_a_enf->getErrors());
                        //$model->addError('id', 'Hubo un error con los datos de enfermeria, por favor intente crear la encuesta luego');
                        throw new Exception();
                    }

                    // el link no unicamente setea las columnas parent_id y parent sino tambien guarda en base
                    // por esto el validate anterior es importante
                    $model->link('atencionEnfermeria', $model_a_enf);
                }
                // Registramos con snomed los antecedentes en caso de tenerlos y de no tenerlos aún registrados en el sistema
                // 254837009
                if ($model->antecedente_cancer_mama == 'SI') {
                    $antecedente = PersonasAntecedente::getPersonasAntecedentePorSnomed($persona->id_persona, '254837009', 'Personal');
                    if (count($antecedente) <= 0) {
                        $p_a = new PersonasAntecedente();
                        $p_a->id_consulta = $nuevaConsulta->id_consulta;
                        $p_a->id_persona = $persona->id_persona;
                        $p_a->codigo = '254837009';
                        $p_a->tipo_antecedente = 'Personal';
                        $p_a->origen_id_antecedente = 'snomed';
                        if (!$p_a->validate()) {
                            $model->addError('antecedente_cancer_mama', 'Hubo un error con los antecedentes, por favor intente crear la encuesta luego');
                            throw new Exception();
                        }
                        $model->link('personasAntecedentes', $p_a);
                    }
                }

                if ($model->antecedente_cirugia_mamaria == 'SI') {
                    $antecedente = PersonasAntecedente::getPersonasAntecedentePorSnomed($persona->id_persona, '428540007', 'Personal');
                    if (count($antecedente) <= 0) {
                        $p_a = new PersonasAntecedente();
                        $p_a->id_consulta = $nuevaConsulta->id_consulta;
                        $p_a->id_persona = $persona->id_persona;
                        $p_a->codigo = '428540007';
                        $p_a->tipo_antecedente = 'Personal';
                        $p_a->origen_id_antecedente = 'snomed';
                        if (!$p_a->validate()) {
                            $model->addError('antecedente_cirugia_mamaria', 'Hubo un error con los antecedentes, por favor intente crear la encuesta luego');
                            throw new Exception();
                        }
                        $model->link('personasAntecedentes', $p_a);
                    }
                }

                if ($model->antecedente_familiar_cancer_mamario_ovarico == 'SI') {
                    $antecedente = PersonasAntecedente::getPersonasAntecedentePorSnomed($persona->id_persona, '254843006', 'Familiar');
                    
                    if (count($antecedente) <= 0) {
                        $p_a = new PersonasAntecedente();
                        $p_a->id_consulta = $nuevaConsulta->id_consulta;
                        $p_a->id_persona = $persona->id_persona;
                        $p_a->codigo = '254843006';
                        $p_a->tipo_antecedente = 'Familiar';
                        $p_a->origen_id_antecedente = 'snomed';
                        if (!$p_a->validate()) {
                            $model->addError('antecedente_familiar_cancer_mamario_ovarico', 'Hubo un error con los antecedentes, por favor intente crear la encuesta luego');
                            throw new Exception();
                        }
                        $model->link('personasAntecedentes', $p_a);
                    }
                }

                $transaction->commit();
                
                return $this->redirect(['personas/view', 'id' => $model->id_persona]);
            }
        } catch (Exception $e) {
            $transaction->rollBack();
            //var_dump($e->getMessage());
        }
        return $this->render('create', [
            'model' => $model,
            'modelAtencionEnfermeria' => isset($model_a_enf) ? $model_a_enf : NULL
        ]);
    }

    /**
     * Updates an existing EncuestaParchesMamarios model.
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
     * Finds the EncuestaParchesMamarios model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return EncuestaParchesMamarios the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = EncuestaParchesMamarios::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('La página parece no existir.');
    }
}
