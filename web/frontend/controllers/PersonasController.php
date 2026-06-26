<?php

namespace frontend\controllers;

use Yii;

use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use yii\helpers\Url;

use common\models\Person\Persona;
use common\models\PersonaTelefono;
use common\models\Tipo_telefono;
use common\models\Domicilio;
use common\models\Persona_domicilio;
use common\models\Localidad;
use common\models\Provincia;
use common\models\Departamento;
use common\models\Persona_mails;
use common\models\Persona_hc;
use common\models\Tipo_documento;
use common\models\PersonaRepository;
use common\models\Percentilos;

use common\components\Platform\Core\Form\NestedFormModels;
use common\components\Domain\Integrations\Mpi\MpiApiClient;
use frontend\filters\SisseActionFilter;
use common\components\Platform\Core\Http\UserRequest;

/**
 * PersonasController implements the CRUD actions for persona model.
 */
class PersonasController extends Controller
{

    public $token;

    private function mpiApi(): MpiApiClient
    {
        /** @var MpiApiClient $client */
        $client = Yii::$app->get('mpi');

        return $client;
    }

    public function behaviors()
    {
        return [
            'access' => [
                'class' => SisseActionFilter::className(),
                'only' => ['view', 'crear-numero-historia-clinica'],
                'filtrosExtra' => [SisseActionFilter::FILTRO_CONTEXTO_PROFESIONAL],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [],
            ],
        ];
    }

    // CSRF deshabilitado: llamadas AJAX (Renaper, vacunas, DepDrop vía API, etc.)
    public function beforeAction($action)
    {
        $this->enableCsrfValidation = false;
        return parent::beforeAction($action);
    }

    /**
     * @no_intent_catalog
    */
    public function actionBuscarRenaper($parametros = [])
    {
        $respuesta = [];
        if (Yii::$app->request->isAjax) {
            $data = Yii::$app->request->post();
            $dni = explode(":", $data['dni']);
            $sexo = explode(":", $data['sexo']);
            $parametros['dni'] = $dni[0];
            $parametros['sexo'] = $sexo[0];
        }
        $respuesta = $this->mpiApi()->caller_mpi('renaper?dni=' . $parametros['dni'] . "&sexo=" . $parametros['sexo'], '{}');
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
     * @no_intent_catalog
    */
    public function actionBuscarPersona()
    {
        $session = Yii::$app->session;
        $session->remove('persona');

        return $this->redirect(['registrar-paciente']);
    }

    /**
     * @no_intent_catalog
     * @deprecated MPI legacy — retirado
     */
    public function actionListaCandidatos()
    {
        \common\components\Domain\Person\Service\PersonasMpiLegacyGate::deny();
    }

    /**
     * @no_intent_catalog
     * @deprecated MPI legacy — retirado
     */
    public function actionSeleccionarPersona($id = null, $tipo = null)
    {
        \common\components\Domain\Person\Service\PersonasMpiLegacyGate::deny();
    }

    /**
     * Ver detalles de una persona
     * @entity Pacientes
     * @tags persona,paciente,ver,detalle,historia
     * @keywords ver,mostrar,detalle,historia clínica
     * @synonyms paciente,persona,historia clínica
     * @param int $id ID de la persona
     * @no_intent_catalog
    */
    public function actionView($id)
    {
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
        $resultado_empadronado = $this->mpiApi()->traerPaciente($id, 'local');
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

    /**
     * @no_intent_catalog
    */
    public function actionDatosPersonales($id)
    {
        $federado = false;
        $resultado_empadronado = $this->mpiApi()->traerPaciente($id, 'local');
        if(isset($resultado_empadronado['successful']) && $resultado_empadronado['successful'] == true && count($resultado_empadronado['data']) == 1){
            $federado = true;
        } 
        $model = $this->findModel($id);

        return $this->renderAjax('_datos_personales', [
            'model' => $model,
            'federado' => $federado,
        ]);
    }

    /**
     * @no_intent_catalog
    */
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

    /**
     * @no_intent_catalog
    */
    public function actionDatosContacto($id)
    {        
        $model = $this->findModel($id);        
        
        return $this->renderAjax('_contacto', [
            'telefonos' => $model->telefonos,       
            'emails' => $model->mails,       
        ]);
    }

    /**
     * @no_intent_catalog
    */
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

    /**
     * Finds the persona model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return persona the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Persona::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }

    //View PUCO
    /**
     * @no_intent_catalog
    */
    public function actionViewpuco()
    {
        $dni = Yii::$app->getRequest()->getQueryParam('dni');
        $sexo = Yii::$app->getRequest()->getQueryParam('sexo');

        $respuesta = $this->mpiApi()->caller_mpi('coberturas?dni=' . $dni . "&sexo=" . $sexo, '{}');

        return $this->renderAjax('viewpuco', [
            'coberturas' => $respuesta,
        ]);
    }

    /**
     * @no_intent_catalog
    */
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

    //Action para cargar un nuevo numero de historia clinica
    /**
     * @no_intent_catalog
    */
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
     * Alta de paciente (lector DNI / Didit, sin MPI).
     * @no_intent_catalog
     */
    public function actionRegistrarPaciente()
    {
        $request = Yii::$app->request;
        $diditVerificationId = trim((string) (
            $request->get('verificationSessionId')
            ?? $request->get('session_id')
            ?? ''
        ));
        $diditStatus = trim((string) ($request->get('status') ?? ''));

        return $this->render('registrarPaciente', [
            'diditVerificationId' => $diditVerificationId,
            'diditStatus' => $diditStatus,
        ]);
    }

    /** @no_intent_catalog */
    public function actionPreviewRenaperStaff()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        try {
            $body = $this->mergedJsonBodyStaffRegistro();
            $data = (new \common\components\Domain\Person\Service\RegistroStaffPacienteService())->previewRenaper($body);

            return ['success' => true, 'data' => $data];
        } catch (\InvalidArgumentException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        } catch (\Throwable $e) {
            Yii::error('previewRenaperStaff: ' . $e->getMessage(), __METHOD__);

            return ['success' => false, 'message' => 'Error al consultar RENAPER.'];
        }
    }

    /** @no_intent_catalog */
    public function actionRegistrarPacienteSubmit()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        try {
            $body = $this->mergedJsonBodyStaffRegistro();
            $data = (new \common\components\Domain\Person\Service\RegistroStaffPacienteService())->registrar($body);

            return ['success' => true, 'data' => $data, 'persona' => $data['persona'] ?? null];
        } catch (\InvalidArgumentException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        } catch (\RuntimeException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        } catch (\Throwable $e) {
            Yii::error('registrarPacienteSubmit: ' . $e->getMessage(), __METHOD__);

            return ['success' => false, 'message' => 'Error interno al registrar paciente.'];
        }
    }

    /** @no_intent_catalog */
    public function actionCrearSesionDiditStaff()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $body = $this->mergedJsonBodyStaffRegistro();
        $callback = trim((string) ($body['callback'] ?? ''));
        if ($callback === '') {
            $callback = Url::to(['personas/registrar-paciente'], true);
        }

        $didit = Yii::$container->has(\common\components\Domain\Integrations\Identity\DiditClient::class)
            ? Yii::$container->get(\common\components\Domain\Integrations\Identity\DiditClient::class)
            : new \common\components\Domain\Integrations\Identity\DiditClient();

        $session = $didit->createVerificationSession([
            'callback' => $callback,
            'vendor_data' => 'frontend-staff-' . (int) Yii::$app->user->id,
            'language' => 'es',
        ]);

        if (empty($session['success'])) {
            return [
                'success' => false,
                'message' => (string) ($session['message'] ?? 'No se pudo crear sesión Didit.'),
            ];
        }

        return [
            'success' => true,
            'data' => [
                'session_id' => $session['session_id'] ?? '',
                'url' => $session['url'] ?? '',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mergedJsonBodyStaffRegistro(): array
    {
        $raw = Yii::$app->request->getRawBody();
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return array_merge(Yii::$app->request->get(), Yii::$app->request->post());
    }
}
