<?php

namespace frontend\controllers;

use Yii;
use yii\helpers\Url;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\httpclient\Client;

use common\components\Clinical\Service\EncounterSumarAutofacturacionContext;
use common\models\sumar\Autofacturacion;
use common\models\busquedas\AutofacturacionEncounterBusqueda;
use common\models\ProfesionalEfectorServicio;

class AutofacturacionController extends Controller
{
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * @no_intent_catalog
    */
    public function actionIndex()
    {
        $searchModel = new AutofacturacionEncounterBusqueda();
        $searchModel->id_efector = Yii::$app->user->getIdEfector();
        $searchModel->conAutofacturacion = true;
        $searchModel->listadoConsultasEnviadas = false;

        $searchModel->fecha_desde = date('Y-m-01');
        $searchModel->fecha_hasta = date('Y-m-d');

        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * @no_intent_catalog
    */
    public function actionMapearSumar()
    {
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;       

        $ctx = $this->requireSumarContext(Yii::$app->request->post());
        if ($ctx === null) {
            return ['error' => true, 'message' => 'Encounter no encontrado'];
        }
        $encounterId = (int) $ctx->encounter->id;
        $persona = $ctx->obtenerPaciente();
        if ($persona === null) {
            return ['error' => true, 'message' => 'Paciente no encontrado para el encounter'];
        }

        $beneficiariosSumar = Yii::$app->autofacturacionSumar->consultaBeneficiarioConDatos($persona->documento, $persona->sexoLetra, $persona->fecha_nacimiento, $persona->tipoDocumento->nombre);
       // var_dump($beneficiariosSumar);die;
        $result = Json::decode($beneficiariosSumar);
        
        if(isset($result['data']) && count($result['data']) > 0)
        {
            foreach ($result['data'] as $key => $beneficiario) {
                $arrayBeneficiarios[] = [
                    'clave_beneficiario' => $beneficiario['clave_beneficiario'],
                    'apellido_benef' => $beneficiario['apellido'],
                    'nombre_benef' => $beneficiario['nombre'],
                    'numero_doc' => $beneficiario['dni'],
                    'fecha_nacimiento' => $beneficiario['fecha_nac'],
                ];
            }
        }
       

        //$codigos = [321303213, 321303255];

        /*$arrayCodigos[] = [
            'codigo' => 321303213, 
            'descripcion' => 'hjksdfh', 
            'grupo' => 'sdfsdf', 
            'sexo' => 1, 
            'ruralidad' => 5,
        ];*/

        /* $beneficiarios = \common\models\BeneficiarioSumar::find()
           ->where([
                    'tipo_documento' => $persona->tipoDocumento->nombre,
                    'numero_doc' => $persona->documento,
                    'sexo' => $sexo[$persona->sexo_biologico],
                    'fecha_nacimiento_benef' => $persona->fecha_nacimiento,
                    'activo' => ['S', '1'] // 1
            ])
            ->all();
        
        if (count($beneficiarios) == 0) {
            return ['error' => true, 'message' => 'No se encontraron beneficiarios'];
        }

        $arrayBeneficiarios = [];
        foreach ($beneficiarios as $beneficiario) {
            $arrayBeneficiarios[] = [
                            'id_beneficiarios' => $beneficiario->id_beneficiarios,
                            'clave_beneficiario' => $beneficiario->clave_beneficiario,
                            'apellido_benef' => $beneficiario->apellido_benef,
                            'nombre_benef' => $beneficiario->nombre_benef,
                            'numero_doc' => $beneficiario->numero_doc,
                            ];
        }*/
        //$ambitos = ['ATECION_AMBULATORIA', 'SegNivelInternacion' => 'INTERNACION', 'bla' => 'VISITA_DOMICILIARIA'];
        $ambito = $ctx->ambitoSumar();
        
        $codigosDiagnosticos = $ctx->codigosDiagnosticosSnomed();

        //$codigosDiagnosticos = '102506008';
       

       /* foreach ($codigosDiagnosticos as $diagnostico) {                    
            $procedimientos = $consulta->practicasPostDiagnostico;
            $codigosProcedimientos = ArrayHelper::getColumn($procedimientos, 'codigo');
            $diagnosticoProcedimientos[$diagnostico] = 
        }*/

        $diagnosticoProcedimientos[102506008] = [763288003];
        
        //$codigosProcedimientos = '763288003';
        $datosConsulta = array(
            'tipo' => "sumar",
            'ambito' => $ambito,
            'especialidad' => 4901000221100, //TODO: enviar la profesion y especialidades del profesional             
            'diagnosticoProcedimientos' => $diagnosticoProcedimientos,
        );
       
        //var_dump(json_encode($datosConsulta));die;
        /*$client = new Client();
        $mapear = $client->createRequest()
                ->setMethod('POST')
                ->setUrl(Url::toRoute('mapear/site/mapear', true))                
                ->setData($datosConsulta)
                ->setOptions(['timeout' => 100])
                ->send();


        //var_dump($mapear->getContent());die; 

        if (!$mapear->isOk) {
            return ['error' => true, 'message' => 'No se encontraron reglas'];
        }*/
        
        // El mapear devuelve todos los conceptos que se cumplen para una determinada consulta, de esos conceptos nos importan los codigos sumar
        // asociados.

        //  $concepto_destino = $mapear->data['conceptos'];
        //  $codigos = ArrayHelper::getColumn($concepto_destino, 'codigo');
        $codigos = ['CTC008A97', 'CTC001A97'];        
        // prueba para traer los datos extra de los codigos como si requiere DR o TRZ, el grupo etario o el sexo
        $codigos_finales = [];
        foreach ($codigos as $clave => $codigo) {
            $arrayPrestaciones = null;
            $datos_respuesta = Yii::$app->autofacturacionSumar->consultaDatosCodigo($codigo);
            $array_respuestas = json_decode($datos_respuesta);            
            //var_dump($consulta->parent->fecha);die;
            $fechaRef = $ctx->fechaReferenciaPaciente() ?? date('Y-m-d');
            $grupoEtario = $persona->getGrupoEtareoSumar($fechaRef);
            //$grupoEtario = "HOMBRES";
            //$sexoLetra = "M";
            $banderaPrestacion = false;
            foreach ($array_respuestas->data as $key => $codigoSumar) {
                if ($codigoSumar->grupos == $grupoEtario) {
                    if(($codigoSumar->grupos == "MUJERES" || $codigoSumar->grupos == "HOMBRES") || (!is_null($codigoSumar->sexo) && $codigoSumar->sexo == $persona->sexoLetra) || is_null($codigoSumar->sexo)) {
                        $banderaPrestacion = true;
                        $arrayPrestaciones = ['codigo' => $codigoSumar->codigo, 'descripcion' => $codigoSumar->descripcion];
                    }
                }
            }
            if ($banderaPrestacion != false && is_array($arrayPrestaciones)) {
                $codigos_finales[] = $arrayPrestaciones;
            }
            
        }
       
        // $codigos = \common\models\NomencladorSumar::find()
        //                 ->select('codigo')
        //                 ->where([
        //                     'in', "TRIM(REPLACE(codigo,'-',''))", ArrayHelper::getColumn($reglas, 1)
        //                     ])
        //                 ->asArray()
        //                 ->all();

        
        $autofacturacion = new Autofacturacion();
        $autofacturacion->encounter_id = $encounterId;
        $arrayBeneficiarios = isset($arrayBeneficiarios) && is_array($arrayBeneficiarios) ? $arrayBeneficiarios : [];
        $autofacturacion->beneficiarios = json_encode($arrayBeneficiarios);
        $autofacturacion->codigos = json_encode($codigos_finales);
        $idPesCtx = (int) (Yii::$app->user->getIdProfesionalEfectorServicio() ?? 0);
        if ($idPesCtx <= 0) {
            $rh = Yii::$app->user->getIdProfesionalEfectorServicio();
            $idPesCtx = $rh !== null && $rh !== '' ? (int) $rh : 0;
        }
        $autofacturacion->id_profesional_efector_servicio = $idPesCtx > 0 ? $idPesCtx : null;

        if (!$autofacturacion->save()) {
            //return $autofacturacion->getErrors();
             return ['error' => true, 'message' => 'Ocurrió un error al intentar mapear'];
        }

        return [
            'error' => false,
            'message' => $this->renderPartial(
                '_resultado_mapear',
                [
                    'id_consulta' => $encounterId,
                    'beneficiario' => $arrayBeneficiarios,
                    'codigos' => $codigos
                ]
            ),
        ];
    }

    /**
     * @no_intent_catalog
    */
    public function actionEnviarSumar()
    {

        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $ctx = $this->requireSumarContext(Yii::$app->request->post());
        if ($ctx === null) {
            return ['error' => true, 'message' => 'Encounter no encontrado'];
        }
        $encounterId = (int) $ctx->encounter->id;
        $codigo_prestacion = Yii::$app->request->post('codigo');
        $clave_beneficiario = Yii::$app->request->post('beneficiario');

        $autofacturacion = $ctx->getAutofacturacion();
        if ($autofacturacion === null) {
            return ['error' => true, 'message' => 'No hay mapeo SUMAR para este encounter'];
        }

        $prestaciones = [
            'prestacion_id' => $encounterId, 'cuie' => 'G07080', 'clave_beneficiario' => '1401601601002895', 'codigo' => $codigo_prestacion,
            'cantidad' => 1, 'apellido' => 'Sezella', 'nombre' => 'Mauro', 'dni' => 38227268, 'fecha_prestacion' => $ctx->fechaPrestacion()
        ];

        $respuesta_sumar = Yii::$app->autofacturacionSumar->informarPrestaciones($prestaciones);
        $autofacturacion->codigo_enviado = $codigo_prestacion;
        $autofacturacion->beneficiario_enviado = $clave_beneficiario;
        $autofacturacion->fecha_envio = date("Y-m-d h:i:s");
        $autofacturacion->respuesta_sumar = $respuesta_sumar;

        if (!$autofacturacion->save()) {
            return ['error' => true, 'message' => 'Ocurrió un error al intentar mapear'];
        }

        return ['error' => false, 'message' => 'El encounter fue enviado a sumar'];
    }

    /**
     * @param array<string, mixed> $input
     */
    private function requireSumarContext(array $input): ?EncounterSumarAutofacturacionContext
    {
        return EncounterSumarAutofacturacionContext::fromRequest($input);
    }

    /**
     * @no_intent_catalog
    */
    public function actionConsultasEnviadas()
    {
        $searchModel = new AutofacturacionEncounterBusqueda();
        $searchModel->id_efector = Yii::$app->user->getIdEfector();
        
        $searchModel->conAutofacturacion = true;
        $searchModel->listadoConsultasEnviadas = true;

        if (isset(Yii::$app->request->getQueryParam('ItemOrderSearch')['fecha_envio'])) {
            
            $date = Yii::$app->request->getQueryParam('ItemOrderSearch')['fecha_envio'];
            $date = explode(' - ', $date);
            $searchModel->fecha_desde = $date[0];
            $searchModel->fecha_hasta = $date[1];
        }

        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('consultas_enviadas', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * @no_intent_catalog
    */
    public function actionConsultasNoProcesadas()
    {
        $searchModel = new AutofacturacionEncounterBusqueda();
        $searchModel->id_efector = Yii::$app->user->getIdEfector();

        $searchModel->conAutofacturacion = false;      

        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('consultas_no_procesadas', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }    
}