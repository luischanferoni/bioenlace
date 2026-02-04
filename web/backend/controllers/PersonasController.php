<?php

/**
 * Modelo Persona
 *  *  * @modificacion: 02/12/2015
 * Se modifico la funcion:
 * - actionView($id): Se modifico para enviar en el render de view, los modelos tipo_telefono,
 *  domicilio, persona_domicilio y localidad
 * - actionCreate(): Se modifico para que al hacer render create, se envien como parametro ademas del modelo persona
 *  los modelos persona_telefono, domicilio, tipo_telefono, persona_domicilio y localidad
 * - actionUpdate($id): se modifico para que al hacer el render update, se envien como parametro ademas del modelo persona
 *  los modelos persona_telefono, domicilio, tipo_telefono, persona_domicilio y localidad
 * @autor: Stella
 * @creacion: 19/10/2015
 * @modificado: 24/11/2015
 */

namespace backend\controllers;

use Yii;

use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;
use yii\helpers\BaseJson;
use yii\helpers\Json;

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
use common\models\RrhhEfector;
use common\models\Tipo_documento;
use common\controllers\Model;
use frontend\controllers\MpiApiController;
use frontend\filters\SisseActionFilter;

/**
 * PersonasController implements the CRUD actions for persona model.
 */
class PersonasController extends Controller {
    
    public $token;
    private $_mpi_api;

    public function behaviors() {
        return [
            'ghost-access'=> [
                'class' => 'webvimark\modules\UserManagement\components\GhostAccessControl',
                'except' => ['vacunas']
            ],
            'access' => [
                'class' => SisseActionFilter::className(),
                'only' => ['view', 'crear-numero-historia-clinica'],
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

    /**
     * Establece una conexion CURL con SumarApi
     * @return mixed
     */   
    public function caller($metodo, $parametros, $post_get, $url_ify = false) {
        if($url_ify){
            $parametros = http_build_query($parametros);            
        }
        $url = "http://170.254.60.17/SumarApi/index.php/";
        $curl = curl_init();

        if($post_get == 'GET'){
            $metodo = $metodo.'?'.http_build_query($parametros);
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
    }

    /**
     * Devuelve el token para realizar las llamadas a las funciones de la Api
     * @return string
     */
    public function token(){
        $token = $this->caller('api/get-token', array(
            "usuario" => "admin",
            "password" => "F43BZldobqBVgyUBCzjO",
        ), 'POST');

        return $token->token;
    }
    
    /**
     * Lists all persona models.
     * @return mixed
     */
    public function actionIndex() {
        $searchModel = new PersonaBusqueda();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }
    public function actionBuscarRenaper($parametros = []){
        $this->_mpi_api = new MpiApiController;
        $respuesta = [];
        if (Yii::$app->request->isAjax) {
            $data = Yii::$app->request->post();
            $dni = explode(":", $data['dni']);
            $sexo = explode(":", $data['sexo']);       
            $parametros['dni'] = $dni[0];
            $parametros['sexo'] = $sexo[0];            
        }
        $respuesta = $this->_mpi_api->caller_mpi('renaper?dni='.$parametros['dni']."&sexo=".$parametros['sexo'],'{}');          
        if(isset($respuesta['data'][0]['apellido'])){
            $apellidos_separados = self::separarApellidos($respuesta['data'][0]['apellido']);
            $respuesta['data'][0]['apellido'] = $apellidos_separados;
            $nombres_separados = self::separarApellidos($respuesta['data'][0]['nombres']);
            $respuesta['data'][0]['nombres']= $nombres_separados;
        }         
        return json_encode($respuesta);
    }

    public function actionTest($parametros = []) {

        $p = new Persona();
        $p->scenario = 'scenarioregistrar';
        //$p->id_persona = 63820;
        $p->acredita_identidad = 1;
        $p->apellido = "CHANFERONI";
        $p->nombre = "LUIS";
        $p->otro_nombre = "GUSTAVO";
        $p->sexo_biologico = 0;
        $p->genero = 2;
        $p->id_tipodoc = 1;
        $p->documento = "1111111";
        $p->documento_propio = 1;
        $p->sexo = "M";
        $p->fecha_nacimiento = "1982-07-14";
        $p->id_estado_civil = 3;
        $p->usuario_alta = "jbravo";
        $p->fecha_alta = "2017-03-16";
        $p->usuario_mod = "mdiaz";
        $p->fecha_mod = "2022-03-20";
        $p->usuario_mod = "mdiaz";

        var_dump($p->validate());
        var_dump($p->getErrors());
        $p->save(false);
    }    

    /**
     * Busca personas.
     * @return mixed
     */
    public function actionBuscarPersona() {
        $session = Yii::$app->session;
        $session->remove('persona');
        $model = new Persona();
        return $this->render('buscarPersona',['model'=> $model]);
    }
    
    private static function sexo($valor){
        if($valor == 1){
            return 'F';
        } elseif ($valor == 2) {
            # code...
            return 'M';
        } elseif ($valor == 3) {
            # code...
            return 'A';
        }
        if($valor == 'F'){
            return 1;
        } elseif ($valor == 'M') {
            # code...
            return 2;
        }
    }

    protected function calcularPesos ($parametros_candidato, $parametros_ingreso) {

        $peso_absoluto_tipo_documento = 10;
        $peso_absoluto_sexo = 10;
        $peso_absoluto_apellido = 20;
        $peso_absoluto_nombre = 10;
        $peso_absoluto_nro_documento = 30;
        $peso_absoluto_fecha_nacimiento = 20;
        
        $peso_tipo_doc = ($parametros_candidato['tipo_doc'] == Tipo_documento::getTipoDocumento($parametros_ingreso['tipo_doc'])->nombre)? $peso_absoluto_tipo_documento : 0;
        $peso_sexo = ($parametros_candidato['sexo'] == $parametros_ingreso['sexo'])? $peso_absoluto_sexo : 0;

        //--- calculos relativos
        
        $apellidos_candidato=self::separarApellidos($parametros_candidato['apellido']);
        $apellidos_ingreso=self::separarApellidos($parametros_ingreso['apellido']);
        $distancia_apellido = levenshtein(mb_strtolower($apellidos_candidato[0]), mb_strtolower($apellidos_ingreso[0])); 

        $longitud_apellido = strlen ($apellidos_candidato[0]);
        $coeficiente_apellido = ($longitud_apellido - $distancia_apellido) / $longitud_apellido;
        $peso_relativo_apellido = $coeficiente_apellido * $peso_absoluto_apellido;


        $nombres_candidato=self::separarApellidos($parametros_candidato['nombre']);
        $nombres_ingreso=self::separarApellidos($parametros_ingreso['nombre']);
        $distancia_nombre = levenshtein(mb_strtolower($nombres_candidato[0]), mb_strtolower($nombres_ingreso[0]));        
        $longitud_nombre = strlen ($nombres_candidato[0]);
        $coeficiente_nombre = ($longitud_nombre - $distancia_nombre) / $longitud_nombre;
        $peso_relativo_nombre = $coeficiente_nombre * $peso_absoluto_nombre;

        $distancia_nro_documento = levenshtein($parametros_candidato['documento'], $parametros_ingreso['documento']);  
        $longitud_nro_documento = strlen ($parametros_candidato['documento']);
        $coeficiente_nro_documento = ($longitud_nro_documento - $distancia_nro_documento) / $longitud_nro_documento;
        $peso_relativo_nro_documento = $coeficiente_nro_documento * $peso_absoluto_nro_documento;
        
        $distancia_fecha_nacimiento = levenshtein($parametros_candidato['fecha_nacimiento'], $parametros_ingreso['fecha_nacimiento']);        
        $longitud_fecha_nacimiento = strlen ($parametros_candidato['fecha_nacimiento']);
        $coeficiente_fecha_nacimiento = ($longitud_fecha_nacimiento - $distancia_fecha_nacimiento) / $longitud_fecha_nacimiento;
        $peso_relativo_fecha_nacimiento = $coeficiente_fecha_nacimiento * $peso_absoluto_fecha_nacimiento;
        //suma de pesos relativos para el score final
        $peso_candidato = $peso_tipo_doc + $peso_sexo + $peso_relativo_nombre + $peso_relativo_apellido + $peso_relativo_nro_documento + $peso_relativo_fecha_nacimiento;
        return $peso_candidato;
    }

    private static function cmp($a, $b){
        if ($a["peso_relativo"] == $b["peso_relativo"]) {
            return 0;
        }
        return ($a["peso_relativo"] > $b["peso_relativo"]) ? -1 : 1;
    }

    protected function ordenarListado($resultados, $parametros, $tipo) {
        foreach ($resultados as $key => $candidato) {
            $peso_candidato = $this->calcularPesos($candidato, $parametros);            
            $resultados[$key]['peso_relativo'] = $peso_candidato;
            $resultados[$key]['tipo'] = $tipo;
        }

        usort($resultados, "self::cmp");

        return $resultados;
    }

    protected function setModelos($post, $model,$model_persona_telefono,$model_persona_mails,$model_persona_domicilio,$model_domicilio){        
        
        $model_localidad = new Localidad();
        $model_provincia = new Provincia();
        $model_departamento = new Departamento();        
        $model_tipo_telefono = new Tipo_telefono();        
        

       if(isset($post['tipo'])){
            $tipo = $post['tipo'];
            $score = $post['score'];
            $id = $post['id'];
        }

        if($tipo == 'local') {
                //echo "//persona en la BD local <br>";       
            if(!isset($model->apellido_materno) || $model->apellido_materno == ''){
                $model->apellido_materno = $post['Persona']['apellido_materno'];
            }  
            if(!isset($model->apellido_paterno) || $model->apellido_paterno == ''){
                $model->apellido_paterno = $post['Persona']['apellido_paterno'];
            }
            if(!isset($model->sexo_biologico) || $model->sexo_biologico == 0){
                $model->sexo_biologico = $post['Persona']['sexo_biologico'];
            }  
            if(!isset($model->genero) || $model->genero == 0){
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
            
            $model_persona_telefono= $model->telefonos;
            $model_persona_mails = $model->mails;
            foreach ($model->domicilios as $key => $domicilio) {
                if (isset($domicilio->id_domicilio)){
                    $model_domicilio = $model_domicilio->findOne($domicilio->id_domicilio);
                }
            }
            if(is_object($model_domicilio->idLocalidad)){
                $id_provincia = $model_domicilio->idLocalidad->idDepartamento->id_provincia;
                $model_provincia = Provincia::findOne($id_provincia);
                $model_departamento = Departamento::findOne($model_domicilio->idLocalidad->id_departamento);
                $model_localidad = Localidad::findOne($model_domicilio->id_localidad);
            } else {
                $model_provincia = new Provincia;
                $model_departamento = new Departamento;
                $model_localidad = new Localidad;
            }
            return [$model,$model_provincia,$model_departamento,$model_localidad,$model_domicilio,$model_persona_telefono,$model_persona_mails,$tipo,$score];                       
        } elseif($tipo == 'mpi') {
            //paciente seleccionado desde el mpi, se preparan todos los modelos para guardarlo locamente
            //echo "//persona en el MPI <br>";
            $resultado = $this->_mpi_api->traerPaciente($id,$tipo);   
        
            $model_tipo_documento = new Tipo_documento();
            $tipo_doc = $model_tipo_documento->findOne($resultado["data"]['paciente']['set_minimo']['tipo_documento']);
            
            $fecha_nacimiento_mpi = $resultado["data"]['paciente']['set_minimo']['fecha_nacimiento'];
            if(strpos($fecha_nacimiento_mpi, "/")){
                list($dia,$mes,$anio) = explode("/", $resultado["data"]['paciente']['set_minimo']['fecha_nacimiento']);
            } else {
                list($anio,$mes,$dia) = explode("-", $resultado["data"]['paciente']['set_minimo']['fecha_nacimiento']);
            }
            $fecha_nacimiento = date('Y-m-d', strtotime($anio."-".$mes."-".$dia));
                
            $array_resultado = [
                'id_tipodoc' => $resultado["data"]['paciente']['set_minimo']['tipo_documento'],
                'documento' => $resultado["data"]['paciente']['set_minimo']['nro_documento'],
                'apellido' => $resultado["data"]['paciente']['set_minimo']['apellido'],
                'nombre' => $resultado["data"]['paciente']['set_minimo']['nombre'],
                'otro_apellido' => $resultado["data"]['paciente']['set_minimo']['otro_apellido'],                
                'otro_nombre' => $resultado["data"]['paciente']['set_minimo']['otros_nombres'],
                'apellido_materno' => $resultado["data"]['paciente']['set_minimo']['apellido_materno'],
                'apellido_paterno' => $resultado["data"]['paciente']['set_minimo']['apellido_paterno']?? null,
                'sexo_biologico' => $resultado["data"]['paciente']['set_minimo']['sexo_biologico'],
                'genero' => $resultado["data"]['paciente']['set_minimo']['genero'],
                'fecha_nacimiento' => $fecha_nacimiento,
                'tipoDocumento' => $tipo_doc
            ];          
            $model->setAttributes($array_resultado, false);
            
            if(isset( $resultado["data"]['paciente']['set_ampliado'])){
                // setea los datos para el modelo telefonos y mail
                if(isset($resultado["data"]['paciente']['set_ampliado']['contacto']['telefono']['fijo'])&& count($resultado["data"]['paciente']['set_ampliado']['contacto']['telefono']['fijo'])>0){
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
                if(isset($resultado["data"]['paciente']['set_ampliado']['contacto']['telefono']['celular'])&& count($resultado["data"]['paciente']['set_ampliado']['contacto']['telefono']['celular'])>0){
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
                if(isset($resultado["data"]['paciente']['set_ampliado']['contacto']['email'])&& count($resultado["data"]['paciente']['set_ampliado']['contacto']['email'])>0){
                    $email = $resultado["data"]['paciente']['set_ampliado']['contacto']['email'][0];
                    $array_email = [                        
                        'mail' => $email,
                    ];
                    $model_mail = new Persona_mails;
                    $model_mail->setAttributes($model_mail, false);
                    
                    $model_persona_mails[] = $model_mail;
                }
            //setea los datos para el modelo de provincia                
                if(isset($resultado["data"]['paciente']['set_ampliado']['residencia']['provincia']['id']) && $resultado["data"]['paciente']['set_ampliado']['residencia']['provincia']['id']!=''){
                    $cod_indec = $resultado["data"]['paciente']['set_ampliado']['residencia']['provincia']['id'];
                    $provincia = Provincia::find()
                    ->where(['cod_indec' => $cod_indec])
                    ->one();
                } elseif (isset($resultado["data"]['paciente']['set_ampliado']['residencia']['provincia']['texto']) && $resultado["data"]['paciente']['set_ampliado']['residencia']['provincia']['texto']!='') {
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
                if(isset($resultado["data"]['paciente']['set_ampliado']['residencia']['departamento']['id']) && $resultado["data"]['paciente']['set_ampliado']['residencia']['departamento']['id']!='' && is_object($model_provincia)){
                    $cod_indec_dpto = substr($resultado["data"]['paciente']['set_ampliado']['residencia']['departamento']['id'],2);
                    $departamento = Departamento::find()
                    ->where(['cod_indec' => $cod_indec_dpto])
                    ->AndWhere(['id_provincia' => $model_provincia->id_provincia])
                    ->one();
                } elseif (isset($resultado["data"]['paciente']['set_ampliado']['residencia']['departamento']['texto']) && $resultado["data"]['paciente']['set_ampliado']['residencia']['departamento']['texto']!='' && is_object($model_provincia)) {
                    $nombre_departamento = $resultado["data"]['paciente']['set_ampliado']['residencia']['departamento']['texto'];
                    $departamento = Departamento::find()
                    ->where(['nombre' => $nombre_departamento])
                    ->AndWhere(['id_provincia' => $model_provincia->id_provincia])
                    ->one();
                }
                if(is_object($departamento)){
                    $array_resultado_dpto = [
                        'id_departamento' => $departamento->id_departamento,
                        'nombre' =>$departamento->nombre
                    ];
                    $model_departamento->setAttributes($array_resultado_dpto, false);
                }
                //setea los datos para el modelo de localidad
                if(isset($resultado["data"]['paciente']['set_ampliado']['residencia']['localidad']['id']) && $resultado["data"]['paciente']['set_ampliado']['residencia']['localidad']['id']!=''){        
                    $cod_bahra = $resultado["data"]['paciente']['set_ampliado']['residencia']['localidad']['id'];
                    $localidad = Localidad::find()
                    ->where(['cod_bahra' => $cod_bahra])
                    ->one();
                } elseif (isset($resultado["data"]['paciente']['set_ampliado']['residencia']['localidad']['texto']) && $resultado["data"]['paciente']['set_ampliado']['residencia']['localidad']['texto']!='' && is_object($model_departamento)) {
                    $nombre_localidad = $resultado["data"]['paciente']['set_ampliado']['residencia']['localidad']['texto'];
                    $localidad = Localidad::find()
                    ->where(['nombre' => $nombre_localidad])
                    ->AndWhere(['id_departamento' => $model_departamento->id_departamento])
                    ->one();
                }
                if(is_object($localidad)){
                    $array_resultado_localidad = [
                        'id_localidad' => $localidad->id_localidad,
                        'nombre' =>$localidad->nombre,
                        'id_departamento' => $localidad->id_departamento
                    ];
                    $model_localidad->setAttributes($array_resultado_localidad, false);
                }
                //setea los datos para el domicilio         
                $array_resultado_domicilio = [
                    'calle' => $resultado["data"]['paciente']['set_ampliado']['residencia']['calle'],
                    'numero' => $resultado["data"]['paciente']['set_ampliado']['residencia']['numero'],
                    'id_localidad' => is_object($localidad)?$localidad->id_localidad:null,                
                ];
                
                $model_domicilio->setAttributes($array_resultado_domicilio, false);
            }

                return [$model,$model_provincia,$model_departamento,$model_localidad,$model_domicilio,$model_persona_telefono, $model_persona_mails,$tipo,$score];
                
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
            return [$model,new Provincia,new Departamento,new Localidad,new Domicilio,[new PersonaTelefono], [new Persona_mails],$tipo,$score];           
        }
    }

    private static function separarApellidos($apellido){
        /* separar el apellido en espacios */
        $tokens = explode(' ', trim($apellido));
        /* arreglo donde se guardan las "palabras" del apellido */
        $apellidos = [];
        /* palabras de apellidos compuestos */
        $tokens_especiales = array('da','das', 'de', 'del','d', 'dell', 'di', 'do', 'dos', 'du', 'la', 'las', 'le', 'li', 'lo', 'lu', 'los', 'mac', 'mc', 'van', 'vd','ver','von', 'y', 'i', 'san', 'santa','ten');

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

public function actionListaCandidatos(){
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
        if($cantidad_candidatos == 0 || $post['tipo'] == 'masmpi'){
            //buscar mpi
            $resultado = $this->_mpi_api->candidatos($parametros);  
   
            if(isset($resultado['statusCode']) && $resultado['statusCode'] == 200 && $resultado['successful'] == true && count($resultado['data'])==0){ //mostrar boton agegar persona 
                $bandera_boton_agregar = true;
            } else {
                $tipo = 'mpi';
                if(isset($resultado['data']) && count($resultado['data']) > 0) {
                    $resultados_ordenados_mpi = $resultado['data'];
                    if($resultados_ordenados_mpi[0]['score'] == 100){
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
            if($resultados_ordenados_local[0]['peso_relativo'] == 100){
                $bandera_boton_buscar = false;
            } else {
                $bandera_boton_buscar = true;                    
            }
        }
        if(is_array($resultados_ordenados_mpi)){
            $resultados_ordenados = array_merge ( $resultados_ordenados_local, $resultados_ordenados_mpi);    
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
        return $this->render('buscarPersona',['model'=> $model]);
    }
}

    /**
     * Displays a single persona model.
     * Se modifico para enviar en el render de view, los modelos tipo_telefono,
     * domicilio, persona_domicilio y localidad
     * @autor: Stella
     * @creacion: 19/10/2015
     * @param integer $id
     * @return mixed
     */
    public function actionView($id) {
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
        $num_hc = $model->obtenerNHistoriaClinica(Yii::$app->user->getIdEfector());
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
     * Vista solamente para el superadmin
     */
    public function actionAdminView($id) {        
        $this->_mpi_api = new MpiApiController;
        $model_persona_telefono = new PersonaTelefono();
        $model_tipo_telefono = new Tipo_telefono();
        $model_domicilio = new Domicilio();
        $model_persona_domicilio = new Persona_domicilio();
        $model_localidad = new Localidad();
        $model_persona_mails = new Persona_mails(); 

        $model = $this->findModel($id);
        $session = Yii::$app->getSession();
        $session->set('persona', serialize($model));

        return $this->render('admin_view', [
            'model' => $model,
            'model_tipo_telefono' => $model_tipo_telefono,
            'model_domicilio' => $model_domicilio,
            'model_persona_domicilio' => $model_persona_domicilio,
            'model_localidad' => $model_localidad,
        ]);
    }

    /**
     * Updates an existing persona model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id) {
        $model = $this->findModel($id);
        $model->scenario = 'scenarioregistrar';
        $model_persona_telefono = new PersonaTelefono();
        $model_tipo_telefono = new Tipo_telefono();
        $model_domicilio = new Domicilio();
        $model_localidad = new Localidad();
        $model_persona_mails = new Persona_mails();

        $model_persona_hc = Persona_hc::find()
        ->where(['id_persona' => $id, 'id_efector' => Yii::$app->user->idEfector])
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

        try  {
            if($model_persona_hc != null)
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
    public function actionDelete($id) {

        $this->findModel($id)->delete();

        return $this->redirect(['index']);
        
    }


    /**
     * Finds the persona model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return persona the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id) {
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
    public function actionIndexpersonarrhh() {

        $searchModel = new PersonaBusqueda();
        $dataProvider = $searchModel->searchpersonarrhh(Yii::$app->request->queryParams);
        return $this->render('indexpersonarrhh', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionPersonasAutocomplete($q = null, $id = null) {

        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $out = ['results' => ['id' => '', 'text' => '']];
        if (!is_null($q)) {
            $data = Persona::autocomplete($q);

            $out['results'] = array_values($data);
        }
        elseif ($id > 0) {
            $out['results'] = ['id' => $id, 'text' => Persona::find($id)->apellido];
        }
        return $out;
    }    

    /**
     * 
     * Funcion para crear el select dependiente de Departamentos
     */
    public function actionSubcat() {
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
    public function actionLoc() {
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
    public function actionBarrio() {
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
    // Esta funcion se agrego para solucionar error 400 Bad Request
    // con los llamados ajax a las funciones  actionLoc  y actionSubcat
    public function beforeAction($action) {
        $this->enableCsrfValidation = false;
        return parent::beforeAction($action);
    }

    public function actionValidardni() {
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

                $nombre_obrasocial='';
                if($persona_puco['NombreObraSocial']==''){
                    $nombre_obrasocial = 'No Especificada';
                }
                else {
                    $nombre_obrasocial=$persona_puco['NombreObraSocial'];
                }
                
                $mje_puco = "Tiene Obra Social ".$nombre_obrasocial.", ".$persona_puco['NombreYApellido'];
            }
            else{
                $mje_puco = "NO tiene Obra Social";
            }

            if (count($persona) > 0) {
                //Exixte el mismo DNI cargado en la base de datos
                echo $mje = "<input type='hidden' id='existedni' value='1' />"
                . "<div class='alert alert-danger' role='alert'>El DNI $dni ya existe y pertenece a " . $persona[0]['nombre'] . ", " . $persona[0]['apellido'] . "<br><b>P.U.C.O: </b>".$mje_puco.'</div>';
            }
            else{                
                echo $mje = "<input type='hidden' id='existedni' value='1' />"
                . "<div class='alert alert-danger' role='alert'><b>P.U.C.O: </b>".$mje_puco.'</div>';
            }
        }
    }
    
    //View PUCO
    public function actionViewpuco()
    {
        $dni = Yii::$app->getRequest()->getQueryParam('dni');
        $sexo = Yii::$app->getRequest()->getQueryParam('sexo');

        $this->_mpi_api = new MpiApiController;
        $respuesta = $this->_mpi_api->caller_mpi('coberturas?dni='.$dni."&sexo=".$sexo,'{}'); 

        return $this->renderAjax('viewpuco', [
            'coberturas' => $respuesta,
        ]);
    }

    public function actionVacunas()
    {
        $dni = Yii::$app->getRequest()->getQueryParam('dni');
        $sexo = $this->sexo(Yii::$app->getRequest()->getQueryParam('sexo'));

        $respuesta = Yii::$app->sisa->getVacunas($dni, $sexo);

        $data = json_decode($respuesta, true);
        $provider = new \yii\data\ArrayDataProvider([
            'allModels' => $data["aplicacionesVacunasCiudadano"]["aplicacionVacunaCiudadano"],
            'pagination' => [
                'pageSize' => 10,
            ],
        ]);

        return $this->renderAjax('_vacunas', [
            'vacunas' => $provider,
        ]);        
    }
    /**
     * Lists all persona models.
     * @modificacion: 17/03/2017
     * actionReporte() creada para mostrar los reportes
     * @autor: Stella
     */
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
                ->where(['id_persona' => $id, 'id_efector' => Yii::$app->user->getIdEfector()])
                ->one(); 

        if(!isset($model)) {
            $model = new Persona_hc();
        }

        if($model->load(Yii::$app->request->post())) {
            $model->id_persona = $id;

            if($model->save()) {
                Yii::$app->response->format = Response::FORMAT_JSON;
                return ['success' => 'La carga se realizo correctamente.'];
            }
        }
    
        return $this->renderAjax('_form_num_historia_clinica', ['model' => $model]);
    }
}