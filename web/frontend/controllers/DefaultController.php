<?php

namespace frontend\controllers;

use Yii;
use yii\web\Controller;
use frontend\components\UserRequest;
use yii\filters\VerbFilter;
use yii\base\Exception;

use frontend\filters\SisseActionFilter;
use frontend\components\SisseHtmlHelpers;

use common\models\Persona;
use common\models\Consulta;
use common\models\ConsultaDerivaciones;
use common\models\Turno;

class DefaultController extends Controller
{
    use \frontend\controllers\traits\ConsultaTrait;

    public function behaviors()
    {
        //control de acceso mediante la extensión
        return [
            'ghost-access' => [
                'class' => 'frontend\components\SisseGhostAccessControl',
            ],
            'access' => [
                'class' => SisseActionFilter::className(),
                'only' => [
                        'create',
                    ],
                'filtrosExtra' => [],//SisseActionFilter::FILTRO_CONSULTA
                'allowed' => function () {                    
                    
                    $idConsulta =  Yii::$app->request->get('id_consulta');
                    $parent =  Yii::$app->request->get('parent');

                    $consulta = Consulta::findOne($idConsulta);
                    
                    // o es la continuacion de pasos con id_consulta existente
                    // o es el primer paso con parent inexistente
                    if (is_null($consulta) || isset($parent)){
                        return true;
                    }                    
                        
                    if ($consulta->id_rr_hh == UserRequest::requireUserParam('idRecursoHumano')) {
                            return true;
                    }

                    return false;
                },
                'errorMessage' => 'Ocurrió un error con la consulta',
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['post'],
                ],
            ],
        ];
    }
 
    public function actionCreate()
    {
        $session = Yii::$app->getSession();
        $parent = Yii::$app->request->get('parent');
        $parentId = Yii::$app->request->get('parent_id');
        $idConsulta = Yii::$app->request->get('id_consulta');
        $id_persona = Yii::$app->request->get('id_persona');
        $paciente = Persona::findOne($id_persona);
        
        //$paciente = unserialize($session->get('persona'));

        $anterior = (!Yii::$app->request->post() && Yii::$app->request->get('anterior'))? true : false;
        $pasoEspecifico = (!Yii::$app->request->post() && (Yii::$app->request->get('paso') || Yii::$app->request->get('paso') == "0"))? intval(Yii::$app->request->get('paso')) : false;
        
        $consulta = Consulta::getModeloConsulta($idConsulta, $paciente, $parent, $parentId, $anterior, $pasoEspecifico);
        if (!$consulta['success']) {
            \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            $resultado = [
                'success' => false, 
                'msg' => $consulta['msg'],
                'url_siguiente' => null
            ];
        } else {

            $modelConsulta = $consulta['model'];                       

            $resultado = $this->createCore($modelConsulta);
            
            if (Yii::$app->request->post())  {
                // Si NO es un array es casi seguro por un error, ante errores devolvemos la vista
                if (is_array($resultado) && $resultado['success'] === true) {
                    
                    if ($modelConsulta->urlSiguiente == 'fin') {
                         // En las configuraciones con solo un paso no se validan las relaciones requeridas.                      
                        if(!$modelConsulta->tienePasoUnico()){
                            // Se valida que todas las relaciones requeridas tienen datos 
                            $consulta = Consulta::findOne($idConsulta);                     
                            $pasosRequeridosFaltantes = $consulta->validarPasosConfiguracionRequeridos();
                            if(count($pasosRequeridosFaltantes)> 0) {
                                \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
                                $resultado = [
                                    'success' => false, 
                                    'msg' => 'Debe Completar los pasos obligatorios requeridos.',
                                    'url_siguiente' => null,
                                    'pasosRequeridosFaltantes'=> $pasosRequeridosFaltantes
                                ];
                                return $resultado;
                            }
                        }
                                               
                        $modelConsulta->paso_completado = 999;
                    } else {
                        if (!$anterior) {
                            $modelConsulta->paso_completado = $modelConsulta->paso_completado + 1;
                        }
                    }

                    $modelConsulta->save();

                    // Para saber si se trata del primer paso
                    // id_consulta no esta en el primer paso (es nueva), para los siguientes pasos ya se encuentra en urlSiguiente
                    if ($modelConsulta->urlAnterior === null) {                        
                        $this->actualizarEstadoPadre($modelConsulta);
                    }

                    if (!strpos($modelConsulta->urlSiguiente, 'id_consulta') && $modelConsulta->urlSiguiente != 'fin') {
                        $modelConsulta->urlSiguiente = $modelConsulta->urlSiguiente. '?id_consulta=' . $modelConsulta->id_consulta . '&id_persona=' . $id_persona;
                    }
                   
                    if ($modelConsulta->urlSiguiente === 'fin') {                        
                       
                        //Si este paso es el ultimo, cambio en el turno el campo atendido a 'SI' y asigno el rrhh que lo atendio.
                        
                        if ($modelConsulta->parent_class == '\common\models\Turno') {
                            Turno::cambiarCampoAtendido($modelConsulta->parent_id, Turno::ATENDIDO_SI);
                            $turno = Turno::findOne($modelConsulta->parent_id);
                            Turno::cargarRrhhServicioAsignado($modelConsulta->parent_id, $turno->id_servicio_asignado);
                            $consultaPS = ConsultaDerivaciones::getPracticaSolicitadasPorIdConsultaSolicitada($turno->id_consulta_referencia);
                            if($consultaPS):
                                $consultaPS->id_consulta_responde = $modelConsulta->id_consulta;
                                $consultaPS->save();
                            endif;
                        }
                    }   

                    \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

                    $resultado['url_siguiente'] = $modelConsulta->urlSiguiente;
                }
            }
        }


        return $resultado;
       
    }

    public function actualizarEstadoPadre($modelConsulta){

        if ($modelConsulta->parent_class == Consulta::PARENT_CLASSES[Consulta::PARENT_TURNO]) {

            Turno::cambiarCampoAtendido($modelConsulta->parent_id, Turno::ATENDIDO_SI);
            $turno = Turno::findOne($modelConsulta->parent_id);
            Turno::cargarRrhhServicioAsignado($modelConsulta->parent_id, $turno->id_servicio_asignado);

        }

        return true;

    }

    public function createCore($modelConsulta){
        return [
            'success' => false,
            'msg' => 'La operación no tiene un formulario definido.'                
        ]; 
    }
}
