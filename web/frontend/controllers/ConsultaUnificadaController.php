<?php

namespace frontend\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\helpers\Json;
use yii\helpers\Url;

use common\models\Consulta;
use common\models\ConsultasConfiguracion;
use common\models\Persona;
use frontend\controllers\traits\ConsultaTrait;
use frontend\components\UserRequest;

/**
 * ConsultaUnificadaController maneja el formulario unificado de consultas
 */
class ConsultaUnificadaController extends Controller
{
    use ConsultaTrait;

    /**
     * {@inheritdoc}
     */
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
     * Muestra el formulario unificado principal
     */
    public function actionIndex()
    {
        $idConsulta = Yii::$app->request->get('id_consulta');
        $id_persona = Yii::$app->request->get('id_persona');
        $parent = Yii::$app->request->get('parent');
        $parentId = Yii::$app->request->get('parent_id');

        if (!$id_persona) {
            throw new NotFoundHttpException('ID de persona requerido');
        }

        $paciente = Persona::findOne($id_persona);
        if (!$paciente) {
            throw new NotFoundHttpException('Paciente no encontrado');
        }

        // Obtener configuración de pasos
        $configuracionPasos = $this->obtenerConfiguracionPasos($idConsulta, $paciente, $parent, $parentId);
        
        if (!$configuracionPasos) {
            throw new NotFoundHttpException('No se encontró configuración de pasos para este servicio');
        }

        // Obtener modelo de consulta
        $modelConsulta = $this->obtenerModeloConsulta($idConsulta, $paciente, $parent, $parentId);

        return $this->renderAjax('index', [
            'configuracionPasos' => $configuracionPasos,
            'modelConsulta' => $modelConsulta,
            'paciente' => $paciente,
        ]);
    }

    /**
     * Carga un paso específico via AJAX
     */
    public function actionCargarPaso()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $idConsulta = Yii::$app->request->get('id_consulta');
        $id_persona = Yii::$app->request->get('id_persona');
        $paso = Yii::$app->request->get('paso', 0);
        $parent = Yii::$app->request->get('parent');
        $parentId = Yii::$app->request->get('parent_id');

        try {
            $paciente = Persona::findOne($id_persona);
            if (!$paciente) {
                return ['success' => false, 'msg' => 'Paciente no encontrado'];
            }

            $configuracionPasos = $this->obtenerConfiguracionPasos($idConsulta, $paciente, $parent, $parentId);
            if (!$configuracionPasos || !isset($configuracionPasos[$paso])) {
                return ['success' => false, 'msg' => 'Paso no encontrado'];
            }

            $pasoConfig = $configuracionPasos[$paso];
            $modelConsulta = $this->obtenerModeloConsulta($idConsulta, $paciente, $parent, $parentId);

            // Cargar el controlador específico del paso
            $contenido = $this->cargarContenidoPaso($pasoConfig, $modelConsulta, $paciente);

            return [
                'success' => true,
                'contenido' => $contenido,
                'titulo' => $pasoConfig['titulo']
            ];

        } catch (\Exception $e) {
            Yii::error('Error cargando paso: ' . $e->getMessage());
            return ['success' => false, 'msg' => 'Error interno del servidor'];
        }
    }

    /**
     * Guarda el progreso de un paso específico
     */
    public function actionGuardarPaso()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $idConsulta = Yii::$app->request->post('id_consulta');
        $id_persona = Yii::$app->request->post('id_persona');
        $paso = Yii::$app->request->post('paso', 0);
        $parent = Yii::$app->request->post('parent');
        $parentId = Yii::$app->request->post('parent_id');

        try {
            $paciente = Persona::findOne($id_persona);
            if (!$paciente) {
                return ['success' => false, 'msg' => 'Paciente no encontrado'];
            }

            $configuracionPasos = $this->obtenerConfiguracionPasos($idConsulta, $paciente, $parent, $parentId);
            if (!$configuracionPasos || !isset($configuracionPasos[$paso])) {
                return ['success' => false, 'msg' => 'Paso no encontrado'];
            }

            $pasoConfig = $configuracionPasos[$paso];
            $modelConsulta = $this->obtenerModeloConsulta($idConsulta, $paciente, $parent, $parentId);

            // Guardar el paso específico
            $resultado = $this->guardarPasoEspecifico($pasoConfig, $modelConsulta, $paciente);

            if ($resultado['success']) {
                // Actualizar el progreso en la consulta
                $this->actualizarProgresoConsulta($modelConsulta, $paso);
            }

            return $resultado;

        } catch (\Exception $e) {
            Yii::error('Error guardando paso: ' . $e->getMessage());
            return ['success' => false, 'msg' => 'Error interno del servidor'];
        }
    }

    /**
     * Finaliza la consulta completa
     */
    public function actionFinalizar()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $idConsulta = Yii::$app->request->post('id_consulta');
        $id_persona = Yii::$app->request->post('id_persona');
        $parent = Yii::$app->request->post('parent');
        $parentId = Yii::$app->request->post('parent_id');

        try {
            $paciente = Persona::findOne($id_persona);
            if (!$paciente) {
                return ['success' => false, 'msg' => 'Paciente no encontrado'];
            }

            $modelConsulta = $this->obtenerModeloConsulta($idConsulta, $paciente, $parent, $parentId);
            $configuracionPasos = $this->obtenerConfiguracionPasos($idConsulta, $paciente, $parent, $parentId);

            // Verificar que todos los pasos requeridos estén completados
            $pasosFaltantes = $this->verificarPasosRequeridos($modelConsulta, $configuracionPasos);
            
            if (!empty($pasosFaltantes)) {
                return [
                    'success' => false, 
                    'msg' => 'Debe completar todos los pasos requeridos antes de finalizar',
                    'pasos_faltantes' => $pasosFaltantes
                ];
            }

            // Marcar consulta como finalizada
            $modelConsulta->estado = Consulta::ESTADO_FINALIZADA;
            $modelConsulta->paso_completado = Consulta::PASO_FINALIZADA;
            $modelConsulta->fecha_fin = date('Y-m-d H:i:s');
            
            if ($modelConsulta->save()) {
                return [
                    'success' => true,
                    'msg' => 'Consulta finalizada correctamente',
                    'redirect_url' => Url::to(['/consultas/historialconsultas'])
                ];
            } else {
                return ['success' => false, 'msg' => 'Error al finalizar la consulta'];
            }

        } catch (\Exception $e) {
            Yii::error('Error finalizando consulta: ' . $e->getMessage());
            return ['success' => false, 'msg' => 'Error interno del servidor'];
        }
    }

    /**
     * Obtiene la configuración de pasos desde la base de datos
     */
    private function obtenerConfiguracionPasos($idConsulta, $paciente, $parent, $parentId)
    {
        if ($idConsulta) {
            $modelConsulta = Consulta::findOne($idConsulta);
            if ($modelConsulta) {
                $configuracion = ConsultasConfiguracion::findOne($modelConsulta->id_configuracion);
            }
        } else {
            // Determinar configuración basada en el servicio y encounter class
            $resultadoValidacion = ConsultasConfiguracion::validarPermisoAtencion($parent, $parentId, $paciente);
            if (!$resultadoValidacion['success']) {
                return [];
            }

            $configuracion = ConsultasConfiguracion::find()
                ->where(['id_servicio' => $resultadoValidacion['idServicio']])
                ->andWhere(['encounter_class' => $resultadoValidacion['encounterClass']])
                ->andWhere('deleted_at is null')
                ->one();
        }

        if (!$configuracion) {
            return [];
        }

        $jsonPasos = json_decode($configuracion->pasos_json, true);
        return $jsonPasos['conf'] ?? [];
    }

    /**
     * Obtiene o crea el modelo de consulta
     */
    private function obtenerModeloConsulta($idConsulta, $paciente, $parent, $parentId)
    {
        if ($idConsulta) {
            $modelConsulta = Consulta::findOne($idConsulta);
            if (!$modelConsulta) {
                throw new NotFoundHttpException('Consulta no encontrada');
            }
            return $modelConsulta;
        }

        // Crear nueva consulta
        $resultadoValidacion = ConsultasConfiguracion::validarPermisoAtencion($parent, $parentId, $paciente);
        if (!$resultadoValidacion['success']) {
            throw new \Exception($resultadoValidacion['msg']);
        }

        list($urlAnterior, $urlActual, $urlSiguiente, $idConfiguracion) = ConsultasConfiguracion::getUrlPorServicioYEncounterClass(
            $resultadoValidacion['idServicio'], 
            $resultadoValidacion['encounterClass']
        );

        if (!$idConfiguracion) {
            throw new \Exception('Error: Servicio sin configuración');
        }

        $modelConsulta = new Consulta();
        $modelConsulta->urlSiguiente = $urlSiguiente ?? 'fin';
        $modelConsulta->urlAnterior = $urlAnterior;
        $modelConsulta->paso_completado = 0;
        $modelConsulta->id_configuracion = $idConfiguracion;
        $modelConsulta->parent_class = Consulta::PARENT_CLASSES[$parent] ?? '';
        $modelConsulta->parent_id = $parentId;
        $modelConsulta->id_rr_hh = Yii::$app->user->getIdRecursoHumano();
        $modelConsulta->id_servicio = Yii::$app->user->getServicioActual();
        $modelConsulta->id_persona = $paciente->id_persona;
        $modelConsulta->id_efector = Yii::$app->user->getIdEfector();
        $modelConsulta->editando = 0;
        $modelConsulta->estado = Consulta::ESTADO_EN_PROGRESO;

        if (!$modelConsulta->save()) {
            throw new \Exception('Error al crear la consulta');
        }

        return $modelConsulta;
    }

    /**
     * Carga el contenido de un paso específico
     */
    private function cargarContenidoPaso($pasoConfig, $modelConsulta, $paciente)
    {
        $url = $pasoConfig['url'];
        $params = [
            'id_consulta' => $modelConsulta->id_consulta,
            'id_persona' => $paciente->id_persona,
            'ajax' => 1,
            'form_steps' => false
        ];

        // Determinar el controlador y acción
        $urlParts = explode('/', trim($url, '/'));
        $controlador = $urlParts[0] ?? '';
        $accion = $urlParts[1] ?? '';

        // Crear instancia del controlador específico
        $controladorClass = 'frontend\\controllers\\' . ucfirst($controlador) . 'Controller';
        
        if (!class_exists($controladorClass)) {
            throw new \Exception("Controlador no encontrado: {$controladorClass}");
        }

        $controladorInstance = new $controladorClass('consulta-unificada', Yii::$app);
        
        // Simular request
        $request = clone Yii::$app->request;
        foreach ($params as $key => $value) {
            $request->setQueryParams(array_merge($request->getQueryParams(), [$key => $value]));
        }

        // Ejecutar la acción
        $resultado = $controladorInstance->runAction($accion, $params);
        
        return $resultado;
    }

    /**
     * Guarda un paso específico
     */
    private function guardarPasoEspecifico($pasoConfig, $modelConsulta, $paciente)
    {
        $url = $pasoConfig['url'];
        $relacion = $pasoConfig['relacion'];

        // Determinar el controlador y acción
        $urlParts = explode('/', trim($url, '/'));
        $controlador = $urlParts[0] ?? '';
        $accion = $urlParts[1] ?? '';

        // Crear instancia del controlador específico
        $controladorClass = 'frontend\\controllers\\' . ucfirst($controlador) . 'Controller';
        
        if (!class_exists($controladorClass)) {
            return ['success' => false, 'msg' => "Controlador no encontrado: {$controladorClass}"];
        }

        $controladorInstance = new $controladorClass('consulta-unificada', Yii::$app);
        
        // Simular request POST
        $request = clone Yii::$app->request;
        $postData = Yii::$app->request->post();
        $postData['id_consulta'] = $modelConsulta->id_consulta;
        $postData['id_persona'] = $paciente->id_persona;
        
        $request->setBodyParams($postData);

        try {
            // Ejecutar la acción de guardado
            $resultado = $controladorInstance->runAction($accion, $postData);
            
            if (is_array($resultado) && isset($resultado['success'])) {
                return $resultado;
            } else {
                return ['success' => true, 'msg' => 'Paso guardado correctamente'];
            }
            
        } catch (\Exception $e) {
            Yii::error('Error guardando paso: ' . $e->getMessage());
            return ['success' => false, 'msg' => 'Error al guardar el paso'];
        }
    }

    /**
     * Actualiza el progreso de la consulta
     */
    private function actualizarProgresoConsulta($modelConsulta, $paso)
    {
        if ($paso > $modelConsulta->paso_completado) {
            $modelConsulta->paso_completado = $paso;
            $modelConsulta->save();
        }
    }

    /**
     * Verifica que todos los pasos requeridos estén completados
     */
    private function verificarPasosRequeridos($modelConsulta, $configuracionPasos)
    {
        $pasosFaltantes = [];
        
        foreach ($configuracionPasos as $index => $paso) {
            if (isset($paso['requerido']) && $paso['requerido']) {
                $relacion = $paso['relacion'];
                $children = $modelConsulta->$relacion;
                
                if (empty($children)) {
                    $pasosFaltantes[] = $paso['titulo'];
                }
            }
        }
        
        return $pasosFaltantes;
    }
}
