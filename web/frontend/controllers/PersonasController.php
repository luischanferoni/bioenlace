<?php

namespace frontend\controllers;

use common\models\ConsultaDerivaciones;
use common\models\Turno;
use Yii;

use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;
use yii\helpers\BaseJson;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\data\ActiveDataProvider;

//agregamos el modulo de la extension para el control de acceso
use webvimark\modules\UserManagement\UserManagementModule;

use common\models\Persona;
use common\models\busquedas\PersonaBusqueda;
use common\models\PersonaTelefono;
use common\models\Tipo_telefono;
use common\models\Domicilio;
use common\models\Persona_domicilio;
use common\models\Barrios;
use common\models\Localidad;
use common\models\Provincia;
use common\models\Departamento;
use common\models\Persona_mails;
use common\models\Persona_hc;
use common\models\Agenda_rrhh;
use common\models\Rrhh;
use common\models\RrhhEfector;
use common\models\RrhhServicio;
use common\models\Tipo_documento;
use common\models\ConsultasConfiguracion;
use common\models\Consulta;
use common\models\Guardia;
use common\models\PersonaRepository;
use common\models\Percentilos;
use common\models\ServiciosEfector;
use common\models\DiagnosticoConsulta;
use common\models\DiagnosticoConsultaRepository as DCRepo;

use frontend\controllers\Model;
use frontend\controllers\MpiApiController;
use frontend\filters\SisseActionFilter;
use webvimark\modules\UserManagement\models\User;
use frontend\components\UserRequest;
use yii\authclient\InvalidResponseException;
use yii\httpclient\Client;
use \yii\authclient\OAuth2;
use yii\base\InvalidParamException;

/**
 * PersonasController implements the CRUD actions for persona model.
 */
class PersonasController extends Controller
{

    public $token;
    private $_mpi_api;

    public function behaviors()
    {
        return [
            'ghost-access' => [
                'class' => 'frontend\components\SisseGhostAccessControl',
                'except' => [
                    'vacunas', 
                    'buscarhome', 
                    'curvas-crecimiento',
                    'signos-vitales']
            ],
            'access' => [
                'class' => SisseActionFilter::className(),
                'only' => ['historia', 'view', 'crear-numero-historia-clinica'],
                'filtrosExtra' => [SisseActionFilter::FILTRO_RECURSO_HUMANO],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['post'],
                    'deleterrhh' => ['post'],
                ],
            ],
        ];
    }

    // Esta funcion se agrego para solucionar error 400 Bad Request
    // con los llamados ajax a las funciones  actionLoc  y actionSubcat
    public function beforeAction($action)
    {
        $this->enableCsrfValidation = false;
        return parent::beforeAction($action);
    }

    /**
     * Establece una conexion CURL con SumarApi
     * @return mixed
     */
    /*public function caller($metodo, $parametros, $post_get, $url_ify = false)
    {
        if ($url_ify) {
            $parametros = http_build_query($parametros);
        }
        $url = "http://170.254.60.17/SumarApi/index.php/";
        $curl = curl_init();

        if ($post_get == 'GET') {
            $metodo = $metodo . '?' . http_build_query($parametros);
            curl_setopt_array($curl, array(
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_URL => $url . $metodo,

            ));
        } else {
            curl_setopt_array($curl, array(
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_URL => $url . $metodo,
                CURLOPT_POST => 1,
                CURLOPT_POSTFIELDS => $parametros
            ));
        }

        $resp = curl_exec($curl);
        $respuesta = json_decode($resp);
        curl_close($curl);
        return $respuesta;
    }*/

    /**
     * Devuelve el token para realizar las llamadas a las funciones de la Api
     * @return string
     */
    /*public function token()
    {
        $token = $this->caller('api/get-token', array(
            "usuario" => "admin",
            "password" => "F43BZldobqBVgyUBCzjO",
        ), 'POST');

        return $token->token;
    }*/

    /**
     * Lists all persona models.
     * @entity Pacientes
     * @tags persona,paciente,listar,ver todos
     * @keywords listar,ver todos,mostrar,personas,pacientes
     * @synonyms paciente,persona,listado
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new PersonaBusqueda();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionBuscarRenaper($parametros = [])
    {
        $this->_mpi_api = new MpiApiController;
        $respuesta = [];
        if (Yii::$app->request->isAjax) {
            $data = Yii::$app->request->post();
            $dni = explode(":", $data['dni']);
            $sexo = explode(":", $data['sexo']);
            $parametros['dni'] = $dni[0];
            $parametros['sexo'] = $sexo[0];
        }
        $respuesta = $this->_mpi_api->caller_mpi('renaper?dni=' . $parametros['dni'] . "&sexo=" . $parametros['sexo'], '{}');
        if (isset($respuesta['data'][0]['apellido'])) {
            $apellidos_separados = self::separarApellidos($respuesta['data'][0]['apellido']);
            $respuesta['data'][0]['apellido'] = $apellidos_separados;
            $nombres_separados = self::separarApellidos($respuesta['data'][0]['nombres']);
            $respuesta['data'][0]['nombres'] = $nombres_separados;
        }
        return json_encode($respuesta);
    }

    /**
     * Busca personas.
     * @entity Pacientes
     * @tags persona,paciente,buscar,dni,documento
     * @keywords buscar,encontrar,localizar,dni,documento,persona
     * @synonyms paciente,persona,documento,cedula,identificación
     * @return mixed
     */
    public function actionBuscarPersona()
    {
        $session = Yii::$app->session;
        $session->remove('persona');
        $model = new Persona();
        return $this->render('buscarPersona', ['model' => $model]);
    }

    public function actionListaCandidatos()
    {
        $this->_mpi_api = new MpiApiController;
        $post = Yii::$app->request->post();

        $parametros['apellido'] = $post['Persona']['apellido'];
        $parametros['nombre'] = $post['Persona']['nombre'];
        $parametros['documento'] = $post['Persona']['documento'];
        $parametros['fecha_nacimiento'] = $post['Persona']['fecha_nacimiento'];
        $parametros['sexo'] = $post['Persona']['genero'];
        $parametros['tipo_doc'] = $post['Persona']['id_tipodoc'];
        $resultados_ordenados_local = [];
        $resultados_ordenados_mpi = [];
        $model = new Persona;
        $model->scenario = 'scenariobuscar';
        $model->load(Yii::$app->request->post());

        $model->apellido_materno = $post['Persona']['apellido_materno'];
        $model->apellido_paterno = $post['Persona']['apellido_paterno'];
        $model->otro_apellido = $post['Persona']['otro_apellido'];

        $valid = $model->validate();
        if ($valid) {
            $resultados = $model->listadoCandidatos($parametros);

            $resultados_ordenados_local = $this->ordenarListado($resultados, $parametros, 'local');

            $cantidad_candidatos = count($resultados_ordenados_local);
            $bandera_boton_buscar = $bandera_boton_agregar = false;
            $tipo = '';
            if ($cantidad_candidatos == 0 || $post['tipo'] == 'masmpi') {
                //buscar mpi
                $resultado = $this->_mpi_api->candidatos($parametros);

                if (isset($resultado['statusCode']) && $resultado['statusCode'] == 200 && $resultado['successful'] == true && count($resultado['data']) == 0) { //mostrar boton agegar persona 
                    $bandera_boton_agregar = true;
                } else {
                    $tipo = 'mpi';
                    if (isset($resultado['data']) && count($resultado['data']) > 0) {
                        $resultados_ordenados_mpi = $resultado['data'];
                        if ($resultados_ordenados_mpi[0]['score'] == 100) {
                            $bandera_boton_agregar = false;
                        } else {
                            $bandera_boton_agregar = true;
                        }
                    } else {
                        $bandera_boton_agregar = true;
                    }
                }
            } else {
                //agregar boton para buscar en mpi
                //verificar que el candidato no coincida 100%
                if ($resultados_ordenados_local[0]['peso_relativo'] == 100) {
                    $bandera_boton_buscar = false;
                } else {
                    $bandera_boton_buscar = true;
                }
            }

            if (is_array($resultados_ordenados_mpi)) {
                $resultados_ordenados = array_merge($resultados_ordenados_local, $resultados_ordenados_mpi);
            } else {
                $resultados_ordenados = $resultados_ordenados_local;
            }

            return $this->render('listaCandidatos', [
                'lista' => $resultados_ordenados,
                'tipo' => $tipo,
                'bandera_boton_buscar' => $bandera_boton_buscar,
                'bandera_boton_agregar' => $bandera_boton_agregar,
                'model' => $model
            ]);
        } else {
            return $this->render('buscarPersona', ['model' => $model]);
        }
    }

    /**
     * Ver detalles de una persona
     * @entity Pacientes
     * @tags persona,paciente,ver,detalle,historia
     * @keywords ver,mostrar,detalle,historia clínica
     * @synonyms paciente,persona,historia clínica
     * @param int $id ID de la persona
     */
    public function actionView($id)
    {
        //$this->layout = 'dos_columnas';
        $this->_mpi_api = new MpiApiController;
        $model_persona_telefono = new PersonaTelefono();
        $model_tipo_telefono = new Tipo_telefono();
        $model_domicilio = new Domicilio();
        $model_persona_domicilio = new Persona_domicilio();
        $model_localidad = new Localidad();
        $model_persona_mails = new Persona_mails();
        //$num_hc = Persona_hc::getHcPorPersona($id);
        $session = Yii::$app->getSession();

        /*
        $federado = false;
        $resultado_empadronado = $this->_mpi_api->traerPaciente($id, 'local');
        if(isset($resultado_empadronado['successful']) && $resultado_empadronado['successful'] == true && count($resultado_empadronado['data']) == 1){
            $federado = true;
        }
        */

        $model = $this->findModel($id);
        $idEfector = Yii::$app->user->getIdEfector();
        $num_hc = $model->obtenerNHistoriaClinica($idEfector);
        $session->set('persona', serialize($model));

        return $this->render('view', [
            'model' => $model,
            'model_tipo_telefono' => $model_tipo_telefono,
            'model_domicilio' => $model_domicilio,
            'model_persona_domicilio' => $model_persona_domicilio,
            'model_localidad' => $model_localidad,
            'num_hc' =>  $num_hc
        ]);
    }
    
    public function actionDatosPersonales($id)
    {
        $this->_mpi_api = new MpiApiController;
        $federado = false;
        $resultado_empadronado = $this->_mpi_api->traerPaciente($id, 'local');
        if(isset($resultado_empadronado['successful']) && $resultado_empadronado['successful'] == true && count($resultado_empadronado['data']) == 1){
            $federado = true;
        } 
        $model = $this->findModel($id);

        return $this->renderAjax('_datos_personales', [
            'model' => $model,
            'federado' => $federado,
        ]);
    }

    public function actionDomicilio($id)
    {        
        $model = $this->findModel($id);

        $provider = new \yii\data\ArrayDataProvider([
            'allModels' => ($model->domicilios != null) ? $model->domicilios : [],
            'pagination' => [
                'pageSize' => 10,
            ],
        ]);
        
        return $this->renderAjax('_domicilio', [
            'dataProvider' => $provider,       
        ]);
    }

    public function actionDatosContacto($id)
    {        
        $model = $this->findModel($id);        
        
        return $this->renderAjax('_contacto', [
            'telefonos' => $model->telefonos,       
            'emails' => $model->mails,       
        ]);
    }

    public function actionCurvasCrecimiento($id)
    {
        $persona = $this->findModel($id);

        $max_year = PersonaRepository::getMaxEdadConDatosCargados($persona);
        $peso_pc_data = PersonaRepository::getPercentilosPeso(
            $persona,
            $max_year
        );
        $peso_labels = Percentilos::CONFIGURACION_PERCENTILOS['peso']['labels'];

        $talla_pc_data = PersonaRepository::getPercentilosTalla(
            $persona,
            $max_year
        );
        $talla_labels = Percentilos::CONFIGURACION_PERCENTILOS['talla']['labels'];

        $pcef_pc_data = PersonaRepository::getPercentilosPCefalico(
            $persona,
            $max_year
        );
        $pcef_labels = Percentilos::CONFIGURACION_PERCENTILOS['pcefalico']['labels'];

        $imc_pc_data = PersonaRepository::getPercentilosIMC(
            $persona,
            $max_year
        );
        $imc_labels = Percentilos::CONFIGURACION_PERCENTILOS['imc']['labels'];

        $datos_crecimiento = PersonaRepository::getDatosCrecimiento($persona);

        $context = [
            'persona' => $persona,
            'peso_pc_data' => $peso_pc_data,
            'peso_labels' => $peso_labels,
            'talla_pc_data' => $talla_pc_data,
            'talla_labels' => $talla_labels,
            'pcef_pc_data' => $pcef_pc_data,
            'pcef_labels' => $pcef_labels,
            'imc_pc_data' => $imc_pc_data,
            'imc_labels' => $imc_labels,
            'datos_crecimiento' => $datos_crecimiento
        ];

        return $this->renderAjax('curvas_crecimiento', $context);
    }
    
    public function actionSignosVitales($id)
    {
        $persona = $this->findModel($id);
        $actuales = Yii::$app->getRequest()->getQueryParam('actuales', false);
        $modal = Yii::$app->getRequest()->getQueryParam('modal', false);
        
        $datos_sv = PersonaRepository::getDatosSignosVitales($persona);
        $ultimos_sv = PersonaRepository::getUltimosSignosVitales($datos_sv);

        // Datos de prueba para signos vitales (solo en entorno de desarrollo o si se solicita)
        if (defined('YII_DEBUG') && YII_DEBUG || Yii::$app->getRequest()->getQueryParam('simular_signos')) {
            $now = date('Y-m-d H:i:s');
            $fecha_formateada = date('d/m/Y H:i');
            
            // Simular datos en el formato correcto
            $ultimos_sv = [
                'peso' => [
                    'value' => '70.5',
                    'fecha' => $fecha_formateada
                ],
                'talla' => [
                    'value' => '172',
                    'fecha' => $fecha_formateada
                ],
                'imc' => [
                    'value' => '23.8',
                    'fecha' => $fecha_formateada
                ],
                'ta' => [
                    'sistolica' => '120',
                    'diastolica' => '80',
                    'fecha' => $fecha_formateada
                ]
            ];
            
            // También simular datos_sv para el modal
            $datos_sv = [
                [
                    'fecha_atencion' => $now,
                    'peso' => 70.5,
                    'talla' => 172,
                    'imc' => 23.8,
                    'ta1_sistolica' => 120,
                    'ta1_diastolica' => 80
                ],
                [
                    'fecha_atencion' => date('Y-m-d H:i:s', strtotime('-2 days')),
                    'peso' => 70.0,
                    'talla' => 172,
                    'imc' => 23.6,
                    'ta1_sistolica' => 118,
                    'ta1_diastolica' => 76
                ],
                [
                    'fecha' => date('Y-m-d H:i:s', strtotime('-7 days')),
                    'ta' => '125/82',
                    'fc' => 75,
                    'fr' => 17,
                    'temperatura' => 36.8,
                    'peso' => 79.0,
                    'talla' => 172
                ],
            ];
            // No sobrescribir $ultimos_sv aquí, ya está configurado correctamente arriba
        }
        
        // Si se solicitan signos vitales actuales
        if ($actuales) {
            // Para simulación, siempre considerar como actual
            $es_actual = (defined('YII_DEBUG') && YII_DEBUG) || Yii::$app->getRequest()->getQueryParam('simular_signos');
            $total_sv = count($datos_sv);
            
            // Obtener la fecha para el título
            $fecha_titulo = '';
            if (isset($ultimos_sv['peso']['fecha'])) {
                $fecha_titulo = $ultimos_sv['peso']['fecha'];
            } elseif (isset($ultimos_sv['talla']['fecha'])) {
                $fecha_titulo = $ultimos_sv['talla']['fecha'];
            } elseif (isset($ultimos_sv['imc']['fecha'])) {
                $fecha_titulo = $ultimos_sv['imc']['fecha'];
            } elseif (isset($ultimos_sv['ta']['fecha'])) {
                $fecha_titulo = $ultimos_sv['ta']['fecha'];
            }
            
            Yii::$app->response->format = Response::FORMAT_JSON;
            return [
                'success' => true,
                'html' => $this->renderPartial('_signos_vitales_actuales', [
                    'ultimos_sv' => $ultimos_sv,
                    'es_actual' => $es_actual,
                    'total_sv' => $total_sv,
                ]),
                'total_sv' => $total_sv,
                'tiene_mas_sv' => $total_sv > 1,
                'es_actual' => $es_actual,
                'fecha_titulo' => $fecha_titulo
            ];
        }
        
        // Si es modal, devolver vista completa
        if ($modal) {
            // Debug temporal - remover después
            \Yii::info('Datos SV para modal: ' . print_r($datos_sv, true), 'debug');
            \Yii::info('Total registros SV: ' . count($datos_sv), 'debug');
            
            $data_provider = new \yii\data\ArrayDataProvider([
                'allModels' => $datos_sv,
                'pagination' => [
                    'pageSize' => 10,
                ],
            ]);
            
            return $this->renderAjax('_signos_vitales_modal', [
                'persona' => $persona,
                'datos_sv' => $datos_sv,
                'ultimos_sv' => $ultimos_sv,
                'data_provider' => $data_provider,
            ]);
        }
        
        // Comportamiento por defecto
        $data_provider = new \yii\data\ArrayDataProvider([
            'allModels' => $datos_sv,
            'pagination' => false,
        ]);
        
        $context = [
            'persona' => $persona,
            'datos_sv' => $datos_sv,
            'ultimos_sv' => $ultimos_sv,
            'data_provider' => $data_provider,
        ];

        return $this->renderAjax('signos_vitales', $context);
    }

   /* public function renderAjax($view, $params = [])
    {
        return parent::renderAjax($view, $params); // TODO: Change the autogenerated stub
    }*/

    public function actionUpdate($id)
    {
        $model = $this->findModel($id);
        $model->scenario = 'scenarioregistrar';
        $model_persona_telefono = new PersonaTelefono();
        $model_tipo_telefono = new Tipo_telefono();
        $model_domicilio = new Domicilio();
        $model_localidad = new Localidad();
        $model_persona_mails = new Persona_mails();

        $model_persona_hc = Persona_hc::find()
            ->where(['id_persona' => $id, 'id_efector' => UserRequest::requireUserParam('idEfector')])
            ->one();

        // //obtengo el numero de historia clinica
        // $hc = $model_persona_hc->getHcPorPersona($id);
        //obtengo los datos de mail, telefono y domicilio para mostrar en la vista

        $domicilios = $model_domicilio->getDomiciliosPorPersona($id);
        $tels = $model_persona_telefono->getTelefonosPorPersona($id);
        $mailsxpersona = Persona_mails::find()
            ->where(['id_persona' => $id])
            ->all();

        $transaction = Yii::$app->db->beginTransaction();

        try {
            if ($model_persona_hc != null)
                $model_persona_hc->load(Yii::$app->request->post()) && $model_persona_hc->save();

            if ($model->load(Yii::$app->request->post()) && $model->save()) {
                $transaction->commit();
                return $this->redirect(['view', 'id' => $model->id_persona]);
            } else {
                $transaction->rollBack();
            }
        } catch (Exception $e) {
            $transaction->rollBack();
        }

        return $this->render('update', [
            'model' => $model,
            'model_persona_telefono' => $model_persona_telefono,
            'model_tipo_telefono' => $model_tipo_telefono,
            'model_domicilio' => $model_domicilio,
            'model_localidad' => $model_localidad,
            'model_persona_mails' => $model_persona_mails,
            'model_persona_hc' => $model_persona_hc,
            'domicilios' => $domicilios,
            'tels' => $tels,
            'mailsxpersona' => $mailsxpersona,
        ]);
        // if () {
        //     return $this->redirect(['view', 'id' => $model->id_persona]);
        // } else {

        // }
    }

    /**
     * Deletes an existing persona model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    public function actionDeleterrhh()
    {
        $id = Yii::$app->request->post('id');
        $rrhh = Rrhh::find()
            ->where(['id_rr_hh' => $id])
            ->one();

        if ($rrhh != null) {
            $rrhh->delete();
        }

        return "ok";
    }

    /**
     * Finds the persona model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return persona the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = persona::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }

    /**
     * Action para mostrar el listado de personas con sus datos de rrhh
     * 
     */
    public function actionIndexpersonarrhh()
    {
        $searchModel = new PersonaBusqueda();
        $dataProvider = $searchModel->searchpersonarrhh(Yii::$app->request->queryParams);
        return $this->render('indexpersonarrhh', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionPersonasAutocomplete($q = null, $id = null)
    {
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $out = ['results' => ['id' => '', 'text' => '']];
        if (!is_null($q)) {
            $data = Persona::autocomplete($q);

            $out['results'] = array_values($data);
        } elseif ($id > 0) {
            $out['results'] = ['id' => $id, 'text' => Persona::find($id)->apellido];
        }
        return $out;
    }

    /**
     * 
     * Funcion para crear el select dependiente de Departamentos
     */
    public function actionSubcat()
    {
        $out = [];
        if (isset($_POST['depdrop_parents'])) {
            $parents = $_POST['depdrop_parents'];
            if ($parents != null) {
                $cat_id = $parents[0];
                $model_persona = new Persona;
                $out = $model_persona->getDepartamentoxidprovincia($cat_id);
                echo Json::encode(['output' => $out, 'selected' => '']);
                return;
            }
        }
        if (isset($_POST['id_provincia'])) {
            $countDptos = Departamento::find()
                ->where(['id_provincia' => $_POST['id_provincia']])
                ->count();
            $dptos = Departamento::find()
                ->where(['id_provincia' => $_POST['id_provincia']])
                ->all();
            if ($countDptos > 0) {
                foreach ($dptos as $dpto) {
                    $selected = ($dpto->id_departamento == $_POST['id_departamento']) ? "selected" : "";
                    echo "<option value='$dpto->id_departamento' $selected >" . $dpto->nombre . "</option>";
                }
            }
            return;
        }
        echo Json::encode(['output' => '', 'selected' => '']);
    }

    /**
     * 
     * Funcion para crear el select dependiente de localidades
     */
    public function actionLoc()
    {
        $out = [];
        if (isset($_POST['depdrop_parents'])) {
            $parents = $_POST['depdrop_parents'];
            $cat_id = empty($parents[0]) ? null : $parents[0];
            if ($cat_id != null) {
                //$data = \common\models\Persona::getLocalidadxiddepartamento($cat_id);
                $model_persona = new Persona;
                $out = $model_persona->getLocalidadxiddepartamento($cat_id);

                echo Json::encode(['output' => $out, 'selected' => $cat_id]);
                return;
            }
        }
        if (isset($_POST['id_localidad'])) {
            // quiere decir que es un UPDATE y hay que cargar el select localidades
            $countLocs = Localidad::find()
                ->where(['id_departamento' => $_POST['id_departamento']])
                ->count();
            $locs = Localidad::find()
                ->where(['id_departamento' => $_POST['id_departamento']])
                ->all();
            if ($countLocs > 0) {
                foreach ($locs as $loc) {
                    $selected = ($loc->id_localidad == $_POST['id_localidad']) ? "selected" : "";
                    echo "<option value='$loc->id_localidad' $selected >" . $loc->nombre . "</option>";
                }
            }
        }

        echo Json::encode(['output' => '', 'selected' => '']);
    }

    /**
     * Funcion para crear el select dependiente de barrios
     */
    public function actionBarrio()
    {
        $out = [];
        $selected = '';
        if (isset($_POST['depdrop_parents'])) {
            $ids = $_POST['depdrop_parents'];
            $id_loc = empty($ids[0]) ? null : $ids[0];
            $id_barrio = empty($ids[1]) ? null : $ids[1];
            if ($id_loc != null) {
                if (!empty($_POST['depdrop_params'])) {

                    $params = $_POST['depdrop_params'];
                    $selected = $params[1];
                }

                $out = Barrios::depDropBarrios($id_loc);
            }
            if (isset($_POST['id_localidad'])) {
                $out = Barrios::depDropBarrios($_POST['id_localidad']);
            }
        }
        return Json::encode(['output' => $out, 'selected' => $selected]);
    }

    public function actionValidardni()
    {
        if (Yii::$app->request->isAjax) {
            $data = Yii::$app->request->post();
            $dni = explode(":", $data['dni']);
            $nombre = explode(":", $data['nombre']);
            $dni = $dni[0];
            $nombre = $nombre[0];
            $persona = \common\models\Persona::getDatosPersonaXDni($dni, $nombre);

            //PUCO
            $persona_puco = \common\models\Persona::existe_en_puco($dni);
            if (count($persona_puco) > 0) {

                $nombre_obrasocial = '';
                if ($persona_puco['NombreObraSocial'] == '') {
                    $nombre_obrasocial = 'No Especificada';
                } else {
                    $nombre_obrasocial = $persona_puco['NombreObraSocial'];
                }

                $mje_puco = "Tiene Obra Social " . $nombre_obrasocial . ", " . $persona_puco['NombreYApellido'];
            } else {
                $mje_puco = "NO tiene Obra Social";
            }

            if (count($persona) > 0) {
                //Exixte el mismo DNI cargado en la base de datos
                echo $mje = "<input type='hidden' id='existedni' value='1' />"
                    . "<div class='alert alert-danger' role='alert'>El DNI $dni ya existe y pertenece a " . $persona[0]['nombre'] . ", " . $persona[0]['apellido'] . "<br><b>P.U.C.O: </b>" . $mje_puco . '</div>';
            } else {
                echo $mje = "<input type='hidden' id='existedni' value='1' />"
                    . "<div class='alert alert-danger' role='alert'><b>P.U.C.O: </b>" . $mje_puco . '</div>';
            }
        }
    }

    //View PUCO
    public function actionViewpuco()
    {
        $dni = Yii::$app->getRequest()->getQueryParam('dni');
        $sexo = Yii::$app->getRequest()->getQueryParam('sexo');

        $this->_mpi_api = new MpiApiController;
        $respuesta = $this->_mpi_api->caller_mpi('coberturas?dni=' . $dni . "&sexo=" . $sexo, '{}');

        return $this->renderAjax('viewpuco', [
            'coberturas' => $respuesta,
        ]);
    }

    public function actionVacunas()
    {
        $dni = Yii::$app->getRequest()->getQueryParam('dni');
        $sexo = $this->sexo(Yii::$app->getRequest()->getQueryParam('sexo'));
        $ultima = Yii::$app->getRequest()->getQueryParam('ultima', false);
        $modal = Yii::$app->getRequest()->getQueryParam('modal', false);

        //$respuesta = Yii::$app->sisa->getVacunas($dni, $sexo);
        
        // Por defecto la respuesta es vacía, pero podemos inyectar datos de prueba
        if ((defined('YII_DEBUG') && YII_DEBUG) || Yii::$app->getRequest()->getQueryParam('simular_vacunas') || Yii::$app->getRequest()->getQueryParam('debug_vacunas')) {
            $respuesta = json_encode([
                'aplicacionesVacunasCiudadano' => [
                    'aplicacionVacunaCiudadano' => [
                        [
                            'sniVacunaNombre' => 'COVID-19',
                            'nombreGeneralVacuna' => 'Vacuna COVID-19 (Pfizer-BioNTech)',
                            'sniVacunaEsquemaNombre' => 'Esquema COVID-19 Adultos',
                            'sniDosisNombre' => 'Segunda dosis',
                            'fechaAplicacion' => date('Y-m-d'),
                            'origenNombre' => 'Centro de Salud "Dr. Juan Pérez"',
                            'origenLocalidad' => 'Rosario',
                            'origenProvincia' => 'Santa Fe'
                        ],
                        [
                            'sniVacunaNombre' => 'Influenza',
                            'nombreGeneralVacuna' => 'Vacuna Antigripal Estacional',
                            'sniVacunaEsquemaNombre' => 'Esquema Influenza Estacional',
                            'sniDosisNombre' => 'Dosis anual',
                            'fechaAplicacion' => date('Y-m-d', strtotime('-6 months')),
                            'origenNombre' => 'Vacunatorio Municipal',
                            'origenLocalidad' => 'Rosario',
                            'origenProvincia' => 'Santa Fe'
                        ],
                        [
                            'sniVacunaNombre' => 'Hepatitis B',
                            'nombreGeneralVacuna' => 'Vacuna Hepatitis B Recombinante',
                            'sniVacunaEsquemaNombre' => 'Esquema Hepatitis B Adultos',
                            'sniDosisNombre' => 'Primera dosis',
                            'fechaAplicacion' => date('Y-m-d', strtotime('-1 year')),
                            'origenNombre' => 'Hospital Provincial',
                            'origenLocalidad' => 'Rosario',
                            'origenProvincia' => 'Santa Fe'
                        ]
                    ]
                ]
            ]);
        } else {
            $respuesta = '{"aplicacionesVacunasCiudadano":{"aplicacionVacunaCiudadano":[]}}';
        }

        $data = json_decode($respuesta, true);
        $vacunas = ($data["aplicacionesVacunasCiudadano"] != null) ? $data["aplicacionesVacunasCiudadano"]["aplicacionVacunaCiudadano"] : [];

        // Si es modal, devolver vista completa
        if ($modal) {
            $provider = new \yii\data\ArrayDataProvider([
                'allModels' => $vacunas,
                'pagination' => [
                    'pageSize' => 10,
                ],
            ]);

            return $this->renderAjax('_vacunas', [
                'vacunas' => $provider,
            ]);
        }

        // Si es para última vacuna, devolver JSON
        if ($ultima) {
            $ultimaVacuna = !empty($vacunas) ? $vacunas[0] : null;
            $totalVacunas = count($vacunas);
            
            Yii::$app->response->format = Response::FORMAT_JSON;
            return [
                'success' => true,
                'html' => $this->renderPartial('_ultima_vacuna', [
                    'vacuna' => $ultimaVacuna,
                ]),
                'totalVacunas' => $totalVacunas,
                'tieneMasVacunas' => $totalVacunas > 1
            ];
        }

        // Comportamiento por defecto (para compatibilidad)
        $provider = new \yii\data\ArrayDataProvider([
            'allModels' => $vacunas,
            'pagination' => [
                'pageSize' => 10,
            ],
        ]);

        return $this->renderAjax('_vacunas', [
            'vacunas' => $provider,
        ]);
    }

    public function actionReporte()
    {
        $searchModel = new PersonaBusqueda();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('reporte', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionReportesestadisticos()
    {
        return $this->render('reportesEstadisticos');
    }
    /**
     * Lists all persona models.
     * @modificacion: 16/01/2018
     * actionReporteCdentral() y actionReportesestadisticosCentral creada para
     *  mostrar los reportes sin filtro de efector
     * @autor: Mercedes Diaz
     */
    public function actionReporteCentral()
    {
        return $this->render('reporte_central');
    }

    public function actionReportesestadisticosCentral()
    {
        return $this->render('reportesEstadisticos_central');
    }

    //Action para cargar un nuevo numero de historia clinica

    public function actionCrearNumeroHistoriaClinica($id)
    {
        $model = Persona_hc::find()
            ->where(['id_persona' => $id, 'id_efector' => UserRequest::requireUserParam('idEfector')])
            ->one();

        if (!isset($model)) {
            $model = new Persona_hc();
        }

        if ($model->load(Yii::$app->request->post())) {
            $model->id_persona = $id;

            if ($model->save()) {
                Yii::$app->response->format = Response::FORMAT_JSON;
                return ['success' => 'La carga se realizo correctamente.'];
            }
        }

        return $this->renderAjax('_form_num_historia_clinica', ['model' => $model]);
    }

    private static function sexo($valor)
    {
        if ($valor == 1) {
            return 'F';
        } elseif ($valor == 2) {
            # code...
            return 'M';
        } elseif ($valor == 3) {
            # code...
            return 'A';
        }
        if ($valor == 'F') {
            return 1;
        } elseif ($valor == 'M') {
            # code...
            return 2;
        }
    }

    protected function calcularPesos($parametros_candidato, $parametros_ingreso)
    {
        $peso_absoluto_tipo_documento = 10;
        $peso_absoluto_sexo = 10;
        $peso_absoluto_apellido = 20;
        $peso_absoluto_nombre = 10;
        $peso_absoluto_nro_documento = 30;
        $peso_absoluto_fecha_nacimiento = 20;

        $peso_tipo_doc = ($parametros_candidato['tipo_doc'] == Tipo_documento::getTipoDocumento($parametros_ingreso['tipo_doc'])->nombre) ? $peso_absoluto_tipo_documento : 0;
        $peso_sexo = ($parametros_candidato['sexo'] == $parametros_ingreso['sexo']) ? $peso_absoluto_sexo : 0;

        //--- calculos relativos

        $apellidos_candidato = self::separarApellidos($parametros_candidato['apellido']);
        $apellidos_ingreso = self::separarApellidos($parametros_ingreso['apellido']);
        $distancia_apellido = levenshtein(mb_strtolower($apellidos_candidato[0]), mb_strtolower($apellidos_ingreso[0]));

        $longitud_apellido = strlen($apellidos_candidato[0]);
        $coeficiente_apellido = ($longitud_apellido - $distancia_apellido) / $longitud_apellido;
        $peso_relativo_apellido = $coeficiente_apellido * $peso_absoluto_apellido;


        $nombres_candidato = self::separarNombres($parametros_candidato['nombre']);
        $nombres_ingreso = self::separarNombres($parametros_ingreso['nombre']);
        $distancia_nombre = levenshtein(mb_strtolower($nombres_candidato[0]), mb_strtolower($nombres_ingreso[0]));
        $longitud_nombre = strlen($nombres_candidato[0]);
        $coeficiente_nombre = ($longitud_nombre - $distancia_nombre) / $longitud_nombre;
        $peso_relativo_nombre = $coeficiente_nombre * $peso_absoluto_nombre;

        $distancia_nro_documento = levenshtein($parametros_candidato['documento'], $parametros_ingreso['documento']);
        $longitud_nro_documento = strlen($parametros_candidato['documento']);
        $coeficiente_nro_documento = ($longitud_nro_documento - $distancia_nro_documento) / $longitud_nro_documento;
        $peso_relativo_nro_documento = $coeficiente_nro_documento * $peso_absoluto_nro_documento;

        $distancia_fecha_nacimiento = levenshtein($parametros_candidato['fecha_nacimiento'], $parametros_ingreso['fecha_nacimiento']);
        $longitud_fecha_nacimiento = strlen($parametros_candidato['fecha_nacimiento']);
        $coeficiente_fecha_nacimiento = ($longitud_fecha_nacimiento - $distancia_fecha_nacimiento) / $longitud_fecha_nacimiento;
        $peso_relativo_fecha_nacimiento = $coeficiente_fecha_nacimiento * $peso_absoluto_fecha_nacimiento;
        //suma de pesos relativos para el score final
        $peso_candidato = $peso_tipo_doc + $peso_sexo + $peso_relativo_nombre + $peso_relativo_apellido + $peso_relativo_nro_documento + $peso_relativo_fecha_nacimiento;
        return $peso_candidato;
    }

    private static function cmp($a, $b)
    {
        if ($a["peso_relativo"] == $b["peso_relativo"]) {
            return 0;
        }
        return ($a["peso_relativo"] > $b["peso_relativo"]) ? -1 : 1;
    }

    protected function ordenarListado($resultados, $parametros, $tipo)
    {

        foreach ($resultados as $key => $candidato) {
            $peso_candidato = $this->calcularPesos($candidato, $parametros);
            $resultados[$key]['peso_relativo'] = $peso_candidato;
            $resultados[$key]['tipo'] = $tipo;
        }

        usort($resultados, "self::cmp");

        return $resultados;
    }

    protected function setModelos($post, $model, $model_persona_telefono, $model_persona_mails, $model_persona_domicilio, $model_domicilio)
    {
        $model_localidad = new Localidad();
        $model_provincia = new Provincia();
        $model_departamento = new Departamento();
        $model_tipo_telefono = new Tipo_telefono();


        if (isset($post['tipo'])) {
            $tipo = $post['tipo'];
            $score = $post['score'];
            $id = $post['id'];
        }

        if ($tipo == 'local') {
            //echo "//persona en la BD local <br>";       
            if (!isset($model->apellido_materno) || $model->apellido_materno == '') {
                $model->apellido_materno = $post['Persona']['apellido_materno'];
            }
            if (!isset($model->apellido_paterno) || $model->apellido_paterno == '') {
                $model->apellido_paterno = $post['Persona']['apellido_paterno'];
            }
            if (!isset($model->sexo_biologico) || $model->sexo_biologico == 0) {
                $model->sexo_biologico = $post['Persona']['sexo_biologico'];
            }
            if (!isset($model->genero) || $model->genero == 0) {
                $model->genero = $post['Persona']['genero'];
            }
            /*if(isset($post['Persona']['nombre']) && $model->nombre != $post['Persona']['nombre']){
                $model->nombre = $post['Persona']['nombre'];
            }  
            if(isset($post['Persona']['otro_nombre']) && $model->otro_nombre = $post['Persona']['otro_nombre']){
                $model->otro_nombre = $post['Persona']['otro_nombre'];
            }
            if(isset($post['Persona']['apellido']) && $model->apellido != $post['Persona']['apellido']){
                $model->apellido = $post['Persona']['apellido'];
            }  
            if(isset($post['Persona']['otro_apellido']) && $model->otro_apellido != $post['Persona']['otro_apellido']){
                $model->otro_apellido = $post['Persona']['otro_apellido'];                
            }*/

            $model_persona_telefono = $model->telefonos;
            $model_persona_mails = $model->mails;
            foreach ($model->domicilios as $key => $domicilio) {
                if (isset($domicilio->id_domicilio)) {
                    $model_domicilio = $model_domicilio->findOne($domicilio->id_domicilio);
                }
            }
            if (is_object($model_domicilio->localidad)) {
                $id_provincia = $model_domicilio->localidad->departamento->id_provincia;
                $model_provincia = Provincia::findOne($id_provincia);
                $model_departamento = Departamento::findOne($model_domicilio->localidad->id_departamento);
                $model_localidad = Localidad::findOne($model_domicilio->id_localidad);
            } else {
                $model_provincia = new Provincia;
                $model_departamento = new Departamento;
                $model_localidad = new Localidad;
            }
            return [$model, $model_provincia, $model_departamento, $model_localidad, $model_domicilio, $model_persona_telefono, $model_persona_mails, $tipo, $score];
        } elseif ($tipo == 'mpi') {
            //paciente seleccionado desde el mpi, se preparan todos los modelos para guardarlo locamente
            //echo "//persona en el MPI <br>";
            $resultado = $this->_mpi_api->traerPaciente($id, $tipo);

            $model_tipo_documento = new Tipo_documento();
            $tipo_doc = $model_tipo_documento->findOne($resultado["data"]['paciente']['set_minimo']['tipo_documento']);

            $fecha_nacimiento_mpi = $resultado["data"]['paciente']['set_minimo']['fecha_nacimiento'];
            if (strpos($fecha_nacimiento_mpi, "/")) {
                list($dia, $mes, $anio) = explode("/", $resultado["data"]['paciente']['set_minimo']['fecha_nacimiento']);
            } else {
                list($anio, $mes, $dia) = explode("-", $resultado["data"]['paciente']['set_minimo']['fecha_nacimiento']);
            }
            $fecha_nacimiento = date('Y-m-d', strtotime($anio . "-" . $mes . "-" . $dia));

            $array_resultado = [
                'id_tipodoc' => $resultado["data"]['paciente']['set_minimo']['tipo_documento'],
                'documento' => $resultado["data"]['paciente']['set_minimo']['nro_documento'],
                'apellido' => $resultado["data"]['paciente']['set_minimo']['apellido'],
                'nombre' => $resultado["data"]['paciente']['set_minimo']['nombre'],
                'otro_apellido' => $resultado["data"]['paciente']['set_minimo']['otro_apellido'],
                'otro_nombre' => $resultado["data"]['paciente']['set_minimo']['otros_nombres'],
                'apellido_materno' => $resultado["data"]['paciente']['set_minimo']['apellido_materno'],
                'apellido_paterno' => $resultado["data"]['paciente']['set_minimo']['apellido_paterno'] ?? null,
                'sexo_biologico' => $resultado["data"]['paciente']['set_minimo']['sexo_biologico'],
                'genero' => $resultado["data"]['paciente']['set_minimo']['genero'],
                'fecha_nacimiento' => $fecha_nacimiento,
                'tipoDocumento' => $tipo_doc
            ];
            $model->setAttributes($array_resultado, false);

            if (isset($resultado["data"]['paciente']['set_ampliado'])) {
                // setea los datos para el modelo telefonos y mail
                if (isset($resultado["data"]['paciente']['set_ampliado']['contacto']['telefono']['fijo']) && count($resultado["data"]['paciente']['set_ampliado']['contacto']['telefono']['fijo']) > 0) {
                    $model_persona_telefono = [];
                    $telefono_particular = $resultado["data"]['paciente']['set_ampliado']['contacto']['telefono']['fijo'][0];
                    $array_telefono_fijo = [
                        'id_tipo_telefono' => 1,
                        'numero' => $telefono_particular,
                        'comentario' => 'Importado del MPI',
                    ];
                    $model_particular = new PersonaTelefono;
                    $model_particular->setAttributes($array_telefono_fijo, false);

                    $model_persona_telefono[] = $model_particular;
                }
                if (isset($resultado["data"]['paciente']['set_ampliado']['contacto']['telefono']['celular']) && count($resultado["data"]['paciente']['set_ampliado']['contacto']['telefono']['celular']) > 0) {
                    $telefono_celular = $resultado["data"]['paciente']['set_ampliado']['contacto']['telefono']['celular'][0];
                    $array_telefono_celular = [
                        'id_tipo_telefono' => 2,
                        'numero' => $telefono_celular,
                        'comentario' => 'Importado del MPI',
                    ];
                    $model_celular = new PersonaTelefono;
                    $model_celular->setAttributes($array_telefono_celular, false);

                    $model_persona_telefono[] = $model_celular;
                }
                if (isset($resultado["data"]['paciente']['set_ampliado']['contacto']['email']) && count($resultado["data"]['paciente']['set_ampliado']['contacto']['email']) > 0) {
                    $email = $resultado["data"]['paciente']['set_ampliado']['contacto']['email'][0];
                    $array_email = [
                        'mail' => $email,
                    ];
                    $model_mail = new Persona_mails;
                    $model_mail->setAttributes($model_mail, false);

                    $model_persona_mails[] = $model_mail;
                }
                //setea los datos para el modelo de provincia  
                
                $provincia = null;
                
                if (isset($resultado["data"]['paciente']['set_ampliado']['residencia']['provincia']['id']) && $resultado["data"]['paciente']['set_ampliado']['residencia']['provincia']['id'] != '') {
                    $cod_indec = $resultado["data"]['paciente']['set_ampliado']['residencia']['provincia']['id'];
                    $provincia = Provincia::find()
                        ->where(['cod_indec' => $cod_indec])
                        ->one();
                } elseif (isset($resultado["data"]['paciente']['set_ampliado']['residencia']['provincia']['texto']) && $resultado["data"]['paciente']['set_ampliado']['residencia']['provincia']['texto'] != '') {
                    $nombre = $resultado["data"]['paciente']['set_ampliado']['residencia']['provincia']['texto'];
                    $provincia = Provincia::find()
                        ->where(['nombre' => $nombre])
                        ->one();
                }
                if (is_object($provincia)) {
                    $array_resultado_prov = [
                        'id_provincia' => $provincia->id_provincia,
                    ];
                    $model_provincia->setAttributes($array_resultado_prov, false);
                }

                //setea los datos para el modelo de departamento

                $departamento = null;
                
                if (isset($resultado["data"]['paciente']['set_ampliado']['residencia']['departamento']['id']) && $resultado["data"]['paciente']['set_ampliado']['residencia']['departamento']['id'] != '' && is_object($model_provincia)) {
                    $cod_indec_dpto = substr($resultado["data"]['paciente']['set_ampliado']['residencia']['departamento']['id'], 2);
                    $departamento = Departamento::find()
                        ->where(['cod_indec' => $cod_indec_dpto])
                        ->AndWhere(['id_provincia' => $model_provincia->id_provincia])
                        ->one();
                } elseif (isset($resultado["data"]['paciente']['set_ampliado']['residencia']['departamento']['texto']) && $resultado["data"]['paciente']['set_ampliado']['residencia']['departamento']['texto'] != '' && is_object($model_provincia)) {
                    $nombre_departamento = $resultado["data"]['paciente']['set_ampliado']['residencia']['departamento']['texto'];
                    $departamento = Departamento::find()
                        ->where(['nombre' => $nombre_departamento])
                        ->AndWhere(['id_provincia' => $model_provincia->id_provincia])
                        ->one();
                }
                if (is_object($departamento)) {
                    $array_resultado_dpto = [
                        'id_departamento' => $departamento->id_departamento,
                        'nombre' => $departamento->nombre
                    ];
                    $model_departamento->setAttributes($array_resultado_dpto, false);
                }
                //setea los datos para el modelo de localidad

                $localidad = null;

                if (isset($resultado["data"]['paciente']['set_ampliado']['residencia']['localidad']['id']) && $resultado["data"]['paciente']['set_ampliado']['residencia']['localidad']['id'] != '') {
                    $cod_bahra = $resultado["data"]['paciente']['set_ampliado']['residencia']['localidad']['id'];
                    $localidad = Localidad::find()
                        ->where(['cod_bahra' => $cod_bahra])
                        ->one();
                } elseif (isset($resultado["data"]['paciente']['set_ampliado']['residencia']['localidad']['texto']) && $resultado["data"]['paciente']['set_ampliado']['residencia']['localidad']['texto'] != '' && is_object($model_departamento)) {
                    $nombre_localidad = $resultado["data"]['paciente']['set_ampliado']['residencia']['localidad']['texto'];
                    $localidad = Localidad::find()
                        ->where(['nombre' => $nombre_localidad])
                        ->AndWhere(['id_departamento' => $model_departamento->id_departamento])
                        ->one();
                }
                if (is_object($localidad)) {
                    $array_resultado_localidad = [
                        'id_localidad' => $localidad->id_localidad,
                        'nombre' => $localidad->nombre,
                        'id_departamento' => $localidad->id_departamento
                    ];
                    $model_localidad->setAttributes($array_resultado_localidad, false);
                }
                //setea los datos para el domicilio         
                $array_resultado_domicilio = [
                    'calle' => $resultado["data"]['paciente']['set_ampliado']['residencia']['calle'],
                    'numero' => $resultado["data"]['paciente']['set_ampliado']['residencia']['numero'],
                    'id_localidad' => is_object($localidad) ? $localidad->id_localidad : null,
                ];

                $model_domicilio->setAttributes($array_resultado_domicilio, false);
            }

            return [$model, $model_provincia, $model_departamento, $model_localidad, $model_domicilio, $model_persona_telefono, $model_persona_mails, $tipo, $score];
        } elseif ($tipo == '') {
            //echo "//Cuando no se encuentra un candidato 100% correcto y se decide agregar al paciente";

            $array_resultado = [
                'id_tipodoc' => $post["Persona"]['id_tipodoc'],
                'documento' => $post["Persona"]['documento'],
                'apellido' => $post["Persona"]['apellido'],
                'otro_apellido' => $post["Persona"]['otro_apellido'],
                'nombre' => $post["Persona"]['nombre'],
                'otro_nombre' => $post["Persona"]['otro_nombre'],
                'apellido_materno' => $post["Persona"]['apellido_materno'],
                'apellido_paterno' => $post["Persona"]['apellido_paterno'],
                'sexo_biologico' => $post["Persona"]['sexo_biologico'],
                'genero' => $post["Persona"]['genero'],
                'fecha_nacimiento' => $post["Persona"]['fecha_nacimiento'],
            ];
            $model->setAttributes($array_resultado, false);
            $score = 0;
            $tipo = 'nuevo';
            return [$model, new Provincia, new Departamento, new Localidad, new Domicilio, [new PersonaTelefono], [new Persona_mails], $tipo, $score];
        }
    }

    public function actionSeleccionarPersona($id = null, $tipo = null)
    {
        $this->_mpi_api = new MpiApiController;
        $post = Yii::$app->request->post();
        if (isset($post['tipo'])) {
            $tipo = $post['tipo'];
            $score = $post['score'];
            $id = $post['id'];
        }

        $model_persona_telefono = [new PersonaTelefono()];
        $model_persona_mails = [new Persona_mails()];
        $model_persona_domicilio = new Persona_Domicilio();
        $model_domicilio = new Domicilio;
        if (isset($post['tipo']) && $post['tipo'] == 'local') {
            $model = $this->findModel($id);
            $model_persona_telefono = $model->telefonos;
            $model_persona_mails = $model->mails;
            $domicilios = $model->domicilios;
            $model_persona_domicilio = end($domicilios);
            $model_domicilio = !empty($model_persona_domicilio) ? $model_persona_domicilio->domicilio : new Domicilio;
        } else {
            $model = new Persona();
        }

        $model_localidad = new Localidad();
        $model_provincia = new Provincia();
        $model_departamento = new Departamento();
        $model_tipo_telefono = new Tipo_telefono();
        $model->scenario = 'scenarioregistrar';

        // carga de modelos de acuerdo al origen de los datos.      
        if (!isset($post['Provincia']) && $post['tipo'] != 'nuevo') {
            list($model, $model_provincia, $model_departamento, $model_localidad, $model_domicilio, $model_persona_telefono, $model_persona_mails, $tipo, $score) = $this->setModelos($post, $model, $model_persona_telefono, $model_persona_mails, $model_persona_domicilio, $model_domicilio);
        }

        //Aqui se guardan todos los datos tanto localmente como en el mpi (de acuerdo al resultado de la consulta)        

        if (isset($post['Provincia'])) {
            if (isset($post['Persona']['acredita_identidad']) && $post['Persona']['acredita_identidad'] == 0) {
                $acredita_identidad = 'false';
                $model->acredita_identidad = 0;
            } else {
                $acredita_identidad = 'true';
                $model->acredita_identidad = 1;
            }

            if (isset($model->id_persona)) {
                //Para eliminar los registros de telefonos o emails seleccionados
                $oldIDsTels = ArrayHelper::map($model_persona_telefono, 'id_persona_telefono', 'id_persona_telefono');
                $model_persona_telefono = Model::createMultiple(PersonaTelefono::classname(), 'id_persona_telefono', $model_persona_telefono);
                Model::loadMultiple($model_persona_telefono, Yii::$app->request->post());
                $deletedIDsTels = array_diff($oldIDsTels, array_filter(ArrayHelper::map($model_persona_telefono, 'id_persona_telefono', 'id_persona_telefono')));

                $oldIDsEmails = ArrayHelper::map($model_persona_mails, 'id_persona_mail', 'id_persona_mail');
                $model_persona_mails = Model::createMultiple(Persona_mails::classname(), 'id_persona_mail', $model_persona_mails);
                Model::loadMultiple($model_persona_mails, Yii::$app->request->post());
                $deletedIDsEmails = array_diff($oldIDsEmails, array_filter(ArrayHelper::map($model_persona_mails, 'id_persona_mail', 'id_persona_mail')));
            } else {
                $model_persona_telefono = Model::createMultiple(PersonaTelefono::classname(), 'id_persona_telefono');
                Model::loadMultiple($model_persona_telefono, Yii::$app->request->post());

                $model_persona_mails = Model::createMultiple(Persona_mails::classname(), 'id_persona_mail');
                Model::loadMultiple($model_persona_mails, Yii::$app->request->post());
            }
            // validate all models
            $model->scenario = 'scenarioregistrar';
            $model->load(Yii::$app->request->post());
            $valid = $model->validate();
            $valid = Model::validateMultiple($model_persona_telefono) && $valid;
            $valid = Model::validateMultiple($model_persona_mails) && $valid;

            if ($valid) {
                $transaction = \Yii::$app->db->beginTransaction();
                try {
                    if ($flag = $model->save()) {
                        if (!empty($deletedIDsTels)) {
                            // hard delete
                            PersonaTelefono::deleteAll(['id_persona_telefono' => $deletedIDsTels]);
                        }
                        foreach ($model_persona_telefono as $model_persona_tel) {
                            $model_persona_tel->id_persona = $model->id_persona;
                            if (!($flag = $model_persona_tel->save())) {
                                $transaction->rollBack();
                                break;
                            }
                        }
                        if (!empty($deletedIDsEmails)) {
                            // hard delete
                            Persona_mails::deleteAll(['id_persona_mail' => $deletedIDsEmails]);
                        }
                        foreach ($model_persona_mails as $model_persona_mail) {
                            $model_persona_mail->id_persona = $model->id_persona;
                            if (!($flag = $model_persona_mail->save())) {
                                $transaction->rollBack();
                                break;
                            }
                        }

                        $model_domicilio->load(Yii::$app->request->post());
                        /*
						if($model_domicilio->isNewRecord || !empty($model_domicilio->dirtyAttributes)){
							if($model_domicilio->save()){
								$model_persona_domicilio = new Persona_domicilio;
                                $model_persona_domicilio->id_persona = $model->id_persona;
                                $model_persona_domicilio->id_domicilio = $model_domicilio->id_domicilio;
                                $model_persona_domicilio->activo = 'SI';
                                $model_persona_domicilio->usuario_alta = Yii::$app->user->username;
                                $model_persona_domicilio->fecha_alta = date('Y-m-d');
								$model_persona_domicilio->save(false);    
							}
						}
						*/

                        if ($model_domicilio->validate() && $model_domicilio->save()) {
                            $model_persona_domicilio = Persona_domicilio::findOne($model_domicilio->id_domicilio);
                            if (!isset($model_persona_domicilio->id_persona) || $model_persona_domicilio->id_persona == '') {
                                $model_persona_domicilio = new Persona_domicilio;
                                $model_persona_domicilio->id_persona = $model->id_persona;
                                $model_persona_domicilio->id_domicilio = $model_domicilio->id_domicilio;
                                $model_persona_domicilio->activo = 'SI';
                                $model_persona_domicilio->usuario_alta = Yii::$app->user->username;
                                $model_persona_domicilio->fecha_alta = date('Y-m-d');
                            }
                            $model_persona_domicilio->save();
                        }


                        if ($post['Provincia']['id_provincia']) {
                            $provincia = $model_provincia->findOne($post['Provincia']['id_provincia']);
                        }
                        if ($post['Departamento']['id_departamento']) {
                            $departamento = $model_departamento->findOne($post['Departamento']['id_departamento']);
                        }
                        if ($post['Domicilio']['id_localidad']) {
                            $localidad = $model_localidad->findOne($post['Domicilio']['id_localidad']);
                        }
                        //consulta si esta empadronado con mi id local
                        if (isset($model->id_persona)) {
                            $identificador = $model->id_persona;
                            $fuente = 'local';
                        }
                        $paciente_local_empadronado = $this->_mpi_api->traerPaciente($identificador, $fuente);
                        if ($tipo == 'local' || $tipo == 'nuevo' || $tipo == '') {

                            if (isset($paciente_local_empadronado['successful']) && $paciente_local_empadronado['successful'] == false && $paciente_local_empadronado['statusCode'] == 404) {
                                /* echo "//empadrona el paciente en el mpi, en caso de ser nuevo<br>";  */
                                $nombreBarrio = "";
                                if(is_object($model_persona_domicilio) && is_object($model_persona_domicilio->domicilio) &&is_object($model_persona_domicilio->domicilio->modelBarrio) ){
                                    $nombreBarrio = $model_persona_domicilio->domicilio->modelBarrio->nombre;
                                }
                                $parametros = [
                                    'id_persona' => $model->id_persona,
                                    'id_tipodoc' => $model->id_tipodoc,
                                    'documento' => $model->documento,
                                    'apellido' => $model->apellido,
                                    'otro_apellido' => $model->otro_apellido,
                                    'apellido_materno' => $model->apellido_materno,
                                    'apellido_paterno' => $model->apellido_paterno,
                                    'nombre' => $model->nombre,
                                    'otro_nombre' => $model->otro_nombre,
                                    'sexo' => self::sexo($model->sexo),
                                    'sexo_biologico' => $model->sexo_biologico,
                                    'genero' => $model->genero,
                                    'fecha_nacimiento' => $model->fecha_nacimiento,
                                    'acredita_identidad' => $model->acredita_identidad,
                                    'telefonos' => $model_persona_telefono,
                                    'mails' => $model_persona_mails,
                                    'provincia' => $provincia,
                                    'departamento' => $departamento,
                                    'localidad' => $localidad,
                                    'calle' => $post['Domicilio']['calle'],
                                    'numero' => $post['Domicilio']['numero'],
                                    'domicilio' => $post['Domicilio'],
                                    'barrio' => $nombreBarrio
                                ];
                                $resultado = $this->_mpi_api->empadronar($parametros);
                            } else {
                                //echo "//Esta opcion se deja para actualizar el registro cuando lo tengo localmente y tambien en el mpi"; 
                            }
                        } elseif ($tipo == 'mpi') {
                            if (isset($id) and $id != "") {
                                $mpi_id = $id;
                            }
                            $parametros = [
                                'mpi' => $mpi_id,
                                'local_id' => $model->id_persona,
                                'telefonos' => $model->telefonos,
                                'mails' => $model->mails,
                                'provincia' => $provincia,
                                'departamento' => $departamento,
                                'localidad' => $localidad,
                                'calle' => $post['Domicilio']['calle'],
                                'numero' => $post['Domicilio']['numero'],
                                'domicilio' => $post['Domicilio'],
                                'barrio' => $model_persona_domicilio->domicilio->modelBarrio->nombre
                            ];
                            $resultado = $this->_mpi_api->asociar($parametros);
                        }
                    }
                    if ($flag) {
                        $transaction->commit();
                        $this->layout = 'dos_columnas';
                        return $this->redirect(['view', 'id' => $model->id_persona]);
                    }
                } catch (Exception $e) {
                    $transaction->rollBack();
                }
            }
        }

        return $this->render('seleccionarPersona', [
            'id' => $id,
            'tipo' => $tipo,
            'score' => $score,
            'model' => $model,
            'model_domicilio' => $model_domicilio,
            'model_localidad' => $model_localidad,
            'model_provincia' => $model_provincia,
            'model_departamento' => $model_departamento,
            'model_persona_telefono' => (empty($model_persona_telefono)) ? [new PersonaTelefono] : $model_persona_telefono,
            'model_persona_mails' => (empty($model_persona_mails)) ? [new Persona_mails] : $model_persona_mails,
            'model_tipo_telefono' => $model_tipo_telefono
        ]);
    }

    private static function separarApellidos($apellido)
    {
        /* separar el apellido en espacios */
        $tokens = explode(' ', trim($apellido));
        /* arreglo donde se guardan las "palabras" del apellido */
        $apellidos = [];
        /* palabras de apellidos compuestos */
        $tokens_especiales = array('da', 'das', 'de', 'del', 'd', 'dell', 'di', 'do', 'dos', 'du', 'la', 'las', 'le', 'li', 'lo', 'lu', 'los', 'mac', 'mc', 'van', 'vd', 'ver', 'von', 'y', 'i', 'san', 'santa', 'ten');

        $prev = "";
        foreach ($tokens as $token) {
            $_token = strtolower($token);
            if (in_array($_token, $tokens_especiales)) {
                $prev .= "$token ";
            } else {
                $apellidos[] = $prev . $token;
                $prev = "";
            }
        }
        if (count($apellidos) > 2) {
            $primer_apellido = $apellidos[0];
            unset($apellidos[0]);
            $otros_apellidos = implode(' ', $apellidos);
            $apellidos[0] = $primer_apellido;
            $apellidos[1] = $otros_apellidos;
        }
        return $apellidos;
    }

    private static function separarNombres($nombre)
    {
        /* separar el apellido en espacios */
        $tokens = explode(' ', trim($nombre));
        /* arreglo donde se guardan las "palabras" del apellido */
        $apellidos = [];
        /* palabras de apellidos compuestos */
        $tokens_especiales = array('da','das', 'de', 'del','d', 'dell', 'di', 'do', 'dos', 'du', 'la', 'las', 'le', 'li', 'lo', 'lu', 'los', 'mac', 'mc', 'van', 'vd','ver','von', 'y', 'i', 'san','ten');
    
        $prev = "";
        foreach($tokens as $token) {
            $_token = strtolower($token);
            if(in_array($_token, $tokens_especiales)) {
                $prev .= "$token ";
            } else {
                $apellidos[] = $prev. $token;
                $prev = "";
            }
        }
        if (count($apellidos) > 2) {
            $primer_apellido = $apellidos[0];
            unset($apellidos[0]);
            $otros_apellidos = implode(' ', $apellidos);
            $apellidos[0] = $primer_apellido;
            $apellidos[1] = $otros_apellidos;
        }

        return $apellidos;
    }  

    /**
     * Para la busqueda de personas desde el index
     * @return mixed
     */
    public function actionBuscarhome()
    {
        $personas = Persona::find()
            ->where(['like', 'documento', $_POST['documento']])
            ->andWhere([
                'and', ['like', 'nombre', $_POST['nombre']], ['like', 'apellido', $_POST['apellido']]
            ])->andWhere('deleted_at is NULL')->limit(3)->all();

        $result = "";
        $urlBuscarPersona = Url::to(['/personas/buscar-persona']);
        $urlViewPersona = Url::to(['/personas']);

        //TODO: sino se encuentra nada devolver error y poner el enlace al timeline de persona
        if (count($personas) > 0) {
            foreach ($personas as $key => $value) {
                $url = self::crearUrlBotonV2($value);
                $result .=  '<div class="d-flex align-items-center bd-highlight p-3 mb-2 bg-soft-white rounded">
                <div class="bg-soft-white avatar-30 rounded bd-highlight">
                        <svg width="25" viewBox="0 0 31 27" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M30.0785 8.21373H23.9029C21.029 8.21878 18.7009 10.4888 18.6957 13.2908C18.6918 16.0992 21.0225 18.3793 23.9029 18.3831H30.0837V18.8292C30.0837 23.7281 27.1138 26.625 22.0881 26.625H8.91384C3.88681 26.625 0.916992 23.7281 0.916992 18.8292V8.15938C0.916992 3.26049 3.88681 0.375 8.91384 0.375H22.0829C27.1087 0.375 30.0785 3.26049 30.0785 8.15938V8.21373ZM7.82884 8.20235H16.0538H16.059H16.0694C16.6851 8.19982 17.1829 7.71069 17.1803 7.10907C17.1777 6.50872 16.6748 6.02338 16.059 6.02591H7.82884C7.21699 6.02844 6.72051 6.51251 6.71792 7.11034C6.71533 7.71069 7.2131 8.19982 7.82884 8.20235Z" fill="currentColor"></path>
                        <path opacity="0.4" d="M21.3885 13.9326C21.6935 15.3198 22.9097 16.2957 24.2982 16.2704H29.0377C29.6154 16.2704 30.084 15.7919 30.084 15.2005V11.5086C30.0827 10.9185 29.6154 10.4388 29.0377 10.4375H24.1866C22.6072 10.4426 21.3315 11.7536 21.334 13.3692C21.334 13.5583 21.3526 13.7474 21.3885 13.9326Z" fill="currentColor"></path>
                        <ellipse cx="24.2503" cy="13.3542" rx="1.45833" ry="1.45833" fill="currentColor"></ellipse>
                    </svg>
                </div>
                <a href="' . $url . '" class="text-white d-flex"> <div class="ms-3" style="width: 100%;">                                
                    <div class="d-flex align-items-center justify-content-between">
                        <h5 class="mb-0 d-flex align-items-center">
                            ' . $value["nombre"] . ' ' . $value["apellido"] . '
                        </h5>                                    
                    </div>
                    <p class="mb-1"><strong>DNI: </strong> ' . $value["documento"] . '</p>
                </div> </a>
                
                    <a  href="' . $url . '" class="d-flex bd-highlight ms-auto btn btn-success btn-sm me-2 mt-2">
                        <svg class="ms-1" width="18" viewBox="0 0 18 17" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12.9785 3.53978L13.0276 12.0773L4.48926 12.0903" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                            <path opacity="0.4" d="M13.0263 12.0773L2.38157 1.50895" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                            </svg>
                    </a>    

                </div>
            ';
            }
        } else {
            $result .=  '<div class="d-flex align-items-center p-3 mb-2 bg-soft-white rounded">
            <div class="bg-soft-white avatar-30 rounded">
                    <svg width="25" viewBox="0 0 31 27" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M30.0785 8.21373H23.9029C21.029 8.21878 18.7009 10.4888 18.6957 13.2908C18.6918 16.0992 21.0225 18.3793 23.9029 18.3831H30.0837V18.8292C30.0837 23.7281 27.1138 26.625 22.0881 26.625H8.91384C3.88681 26.625 0.916992 23.7281 0.916992 18.8292V8.15938C0.916992 3.26049 3.88681 0.375 8.91384 0.375H22.0829C27.1087 0.375 30.0785 3.26049 30.0785 8.15938V8.21373ZM7.82884 8.20235H16.0538H16.059H16.0694C16.6851 8.19982 17.1829 7.71069 17.1803 7.10907C17.1777 6.50872 16.6748 6.02338 16.059 6.02591H7.82884C7.21699 6.02844 6.72051 6.51251 6.71792 7.11034C6.71533 7.71069 7.2131 8.19982 7.82884 8.20235Z" fill="currentColor"></path>
                    <path opacity="0.4" d="M21.3885 13.9326C21.6935 15.3198 22.9097 16.2957 24.2982 16.2704H29.0377C29.6154 16.2704 30.084 15.7919 30.084 15.2005V11.5086C30.0827 10.9185 29.6154 10.4388 29.0377 10.4375H24.1866C22.6072 10.4426 21.3315 11.7536 21.334 13.3692C21.334 13.5583 21.3526 13.7474 21.3885 13.9326Z" fill="currentColor"></path>
                    <ellipse cx="24.2503" cy="13.3542" rx="1.45833" ry="1.45833" fill="currentColor"></ellipse>
                </svg>
            </div>
            <div class="ms-3" style="width: 100%;">                                
                <div class="d-flex align-items-center justify-content-between">
                    <h5 class="mb-0 d-flex align-items-center">
                        No se encontraron resultados.
                    </h5>                                    
                </div>                
            </div>     
        </div>';
        }

        $result .= '<div class="d-flex align-items-center p-3 mb-2 bg-soft-white rounded">
                        <div class="ms-3" style="width: 100%;">                                
                            <div class="d-flex align-items-center justify-content-center">
                                    <a href="' . $urlBuscarPersona . '" class="btn btn-primary me-2 mt-2 w-100">Buscar más</a>                                
                            </div>                
                        </div>     
                    </div>';

        return $result;
    }

    private function crearUrlBotonV2($paciente)
    {
        if (User::hasPermission('paciente/historia')) {
            return Url::toRoute('paciente/historia/' . $paciente->id_persona);
        }

        return Url::toRoute('personas/' . $paciente->id_persona);
    }
}
