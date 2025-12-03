<?php

namespace frontend\controllers;

use common\models\BeneficiarioSumar;
use frontend\modules\mapear\models\ConceptoDestino;
use frontend\modules\mapear\models\Regla;


use Yii;
use yii\helpers\Url;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\httpclient\Client;

use common\models\sumar\Autofacturacion;
use common\models\Consulta;
use common\models\busquedas\ConsultaBusqueda;

class AutofacturacionController extends Controller
{
    public function behaviors()
    {
        return [
            'ghost-access' => [
                'class' => 'frontend\components\SisseGhostAccessControl',
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    public function actionIndex()
    {
        $searchModel = new ConsultaBusqueda();
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

    public function actionMapearSumar()
    {
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;       

        $id_consulta = Yii::$app->request->post('id_consulta');

        $consulta = Consulta::findOne($id_consulta);    
        
        $persona = $consulta->obtenerPaciente();       

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
        switch ($consulta->parent_class) {
            case '\common\models\Turno':
                $ambito = 712877007;
                break;
            case '\common\models\SegNivelInternacion':
                $ambito = 'INTERNACION';
                break;
            case '\common\models\Guardia':
                $ambito = 'GUARDIA';
                break;
            default:
                $ambito = 712877007;
                break;
        }
        
        $diagnosticos = $consulta->diagnosticoConsultas;
        $codigosDiagnosticos = ArrayHelper::getColumn($diagnosticos, 'codigo');

        //$codigosDiagnosticos = '102506008';
       

       /* foreach ($codigosDiagnosticos as $diagnostico) {                    
            $procedimientos = $consulta->consultaPracticas;
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
            $datos_respuesta = Yii::$app->autofacturacionSumar->consultaDatosCodigo($codigo);
            $array_respuestas = json_decode($datos_respuesta);            
            //var_dump($consulta->parent->fecha);die;
            $grupoEtario = $persona->getGrupoEtareoSumar($consulta->parent->fecha);
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
            if($banderaPrestacion != false) {
                $codigos_finales[] = $arrayPrestaciones;
            }
            
        }
        var_dump($codigos_finales);
       
       // var_dump($codigos);die;

        // $codigos = \common\models\NomencladorSumar::find()
        //                 ->select('codigo')
        //                 ->where([
        //                     'in', "TRIM(REPLACE(codigo,'-',''))", ArrayHelper::getColumn($reglas, 1)
        //                     ])
        //                 ->asArray()
        //                 ->all();

        
        $autofacturacion = new Autofacturacion();
        $autofacturacion->id_consulta = $id_consulta;
        $autofacturacion->beneficiarios = json_encode($arrayBeneficiarios);
        $autofacturacion->codigos = json_encode($codigos_finales);
        $autofacturacion->id_rr_hh = Yii::$app->user->getIdRecursoHumano();

        if (!$autofacturacion->save()) {
            //return $autofacturacion->getErrors();
             return ['error' => true, 'message' => 'Ocurrió un error al intentar mapear'];
        }

        return [
            'error' => false,
            'message' => $this->renderPartial(
                '_resultado_mapear',
                [
                    'id_consulta' => $id_consulta,
                    'beneficiario' => $arrayBeneficiarios,
                    'codigos' => $codigos
                ]
            ),
        ];
    }

    public function actionEnviarSumar()
    {

        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $id_consulta = Yii::$app->request->post('id_consulta');
        $codigo_prestacion = Yii::$app->request->post('codigo');
        $clave_beneficiario = Yii::$app->request->post('beneficiario');

        //$beneficiario = BeneficiarioSumar::buscarBeneficiario($clave_beneficiario);
        $consulta = Consulta::findOne($id_consulta);
        $fecha_hora = (explode(" ", $consulta->created_at));
        $fecha_prestacion = $fecha_hora[0];

        $prestaciones = [
            'prestacion_id' => $id_consulta, 'cuie' => 'G07080', 'clave_beneficiario' => '1401601601002895', 'codigo' => $codigo_prestacion,
            'cantidad' => 1, 'apellido' => 'Sezella', 'nombre' => 'Mauro', 'dni' => 38227268, 'fecha_prestacion' => $fecha_prestacion
        ];

        $respuesta_sumar = Yii::$app->autofacturacionSumar->informarPrestaciones($prestaciones);
        $consulta->autofacturacion->codigo_enviado = $codigo_prestacion;
        $consulta->autofacturacion->beneficiario_enviado = $clave_beneficiario;
        $consulta->autofacturacion->fecha_envio = date("Y-m-d h:i:s");
        $consulta->autofacturacion->respuesta_sumar = $respuesta_sumar;

        if (!$consulta->autofacturacion->save()) {
            return ['error' => true, 'message' => 'Ocurrió un error al intentar mapear'];
        }

        return ['error' => false, 'message' => 'La consulta fue enviada a sumar'];
    }

    public function actionConsultasEnviadas()
    {
        $searchModel = new ConsultaBusqueda();
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

    public function actionConsultasNoProcesadas()
    {
        $searchModel = new ConsultaBusqueda();
        $searchModel->id_efector = Yii::$app->user->getIdEfector();

        $searchModel->conAutofacturacion = false;      

        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('consultas_no_procesadas', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }    
}