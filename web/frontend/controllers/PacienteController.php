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

// Modelos
use common\models\Persona;
use common\models\Consulta;
use common\models\Turno;
use common\models\ServiciosEfector;
use common\models\ConsultaAtencionesEnfermeria;
use common\models\SegNivelInternacion;
use common\models\Guardia;
use common\models\Alergias;
use common\models\PersonasAntecedente;
use common\models\DiagnosticoConsultaRepository as DCRepo;
use \frontend\components\UserRequest;
// Filtros y componentes
use frontend\filters\SisseActionFilter;
use webvimark\modules\UserManagement\models\User;
use yii\authclient\InvalidResponseException;

/**
 * PacienteHistorialController implementa las acciones CRUD para el modelo PacienteHistoriall.
 */
class PacienteController extends Controller
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => SisseActionFilter::className(),
                'only' => ['historia', 'formulario-consulta', 'test'],
                'filtrosExtra' => [SisseActionFilter::FILTRO_CONTEXTO_PROFESIONAL],
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
     * Obtiene el historial de un paciente específico.
     *
     * La vista usa timeline por API (`views/paciente/timeline/timeline.php`). Cualquier rearmado de SQL local
     * debe filtrar también por {@see \common\models\ProfesionalEfectorServicio} además de `consultas.id_rr_hh`
     * (ver `web/docs/dominio/MIGRACION_PES_ESTADO.md`). Listados ambulatorios: {@see \frontend\modules\api\v1\controllers\PacientesController::turnosAmbulatorioMedico}.
     *
     * @param integer $paciente_id
     * @return mixed
     * @no_intent_catalog
    */
    public function actionHistoria($id)
    {
        $this->layout = 'blanco';

        $paciente = Persona::findOne($id);        
        // Migración: el resumen clínico se consume desde la API (GET /api/v1/personas/{id}/historia-clinica).
        // El frontend sólo renderiza la vista.
        return $this->render('timeline/timeline', [
            'persona' => $paciente,
        ]);
    }

    /**
     * Endpoint AJAX para obtener el estado del formulario y mensajes.
     * @param integer $id ID del paciente
     * @return Response JSON con HTML del formulario y mensajes
     * @no_intent_catalog
    */
    public function actionFormularioConsulta($id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        
        try {
            $paciente = $this->findModel($id);
            
            $idConsulta = Yii::$app->request->get('id_consulta');
            $parentId = Yii::$app->request->get('parent_id');
            $parent = Yii::$app->request->get('parent');

            // Sin parent, validarPermisoAtencion asume GENERICO_AMB y bloquea si turnoHoy(); alinear con el
            // turno de la sesión cuando la URL solo trae id (+ fecha del listado, etc.).
            if (!$idConsulta && ($parent === null || $parent === '')) {
                $idTurnoGet = Yii::$app->request->get('id_turno');
                if ($idTurnoGet !== null && $idTurnoGet !== '') {
                    $parent = Consulta::PARENT_TURNO;
                    $parentId = (int) $idTurnoGet;
                } elseif (Yii::$app->user->getEncounterClass() === Consulta::ENCOUNTER_CLASS_AMB) {
                    // turnoHoy también coincide por id_profesional_efector_servicio en sesión (PES)
                    $turnoSesion = $paciente->turnoHoy(
                        Yii::$app->user->getServicioActual(),
                        Yii::$app->user->getIdProfesionalEfectorServicio(),
                        Yii::$app->user->getIdEfector()
                    );
                    if ($turnoSesion) {
                        $parent = Consulta::PARENT_TURNO;
                        $parentId = (int) $turnoSesion->id_turnos;
                    }
                }
            }

            // La validación de internación/guardia ahora se hace en validarPermisoAtencion
            $mostrarFormulario = true;
            $mensajeCondicion = '';
            $mensajeCambioEfector = '';

            // Obtener configuración de pasos para el formulario
            $resultadoConfiguracion = $this->obtenerConfiguracion($idConsulta, $paciente, $parent, $parentId);

            if (!$resultadoConfiguracion['success']) {
                    $mensajeCondicion = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> 
                            <strong>Error:</strong>
                            <small class="text-muted">'.$resultadoConfiguracion['msg'].'</small></div>';
                    $mostrarFormulario = false;
            }

            // Generar HTML del formulario
            $formularioHtml = '';
            if ($mostrarFormulario) {
                $idConfiguracion = $resultadoConfiguracion['idConfiguracion'] ?? null;
                $formularioHtml = $this->renderPartial('_formulario_consulta', [
                    'paciente' => $paciente,
                    'idConfiguracion' => $idConfiguracion,
                    'idConsulta' => $idConsulta,
                    'parent' => $parent,
                    'parentId' => $parentId,
                ]);
            }

            $response = [
                'success' => true,
                'mostrarFormulario' => $mostrarFormulario,
                'formularioHtml' => $formularioHtml,
                'mensajeCondicion' => $mensajeCondicion,
                'mensajeCambioEfector' => $mensajeCambioEfector                
            ];

            // Agregar id_configuracion si el formulario se muestra
            if ($mostrarFormulario && isset($resultadoConfiguracion['idConfiguracion'])) {
                $response['id_configuracion'] = $resultadoConfiguracion['idConfiguracion'];
            }

            return $response;
            
        } catch (\Exception $e) {
            Yii::error('Error en actionFormularioConsulta: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al obtener el estado del formulario'
            ];
        }
    }

    /**
     * @no_intent_catalog
    */
    public function getForms($id_persona)
    {
        $respuesta = false;
        try {
            $client = new \yii\httpclient\Client();
            $request = str_replace('"#PERSONAID"', $id_persona, file_get_contents('../assets/jsonForms/formsPorPersonaId.json'));
            $decodedText = urlencode($request);

            $response = $client->createRequest()
                ->setMethod('GET')
                ->setUrl(Yii::$app->params['hostFormsAPI'] . '/instancias?filter=' . $decodedText)
                ->setHeaders(['Content-type' => 'application/json'])
                ->setData([])
                ->send();

                //var_dump($response->data);
            if (!$response->isOk) {
                $respuesta = false;
            } else {
                //var_dump($response->data);
                foreach ($response->data as $instancia) {

                    $respuesta[] = array(
                        'fecha' => $instancia['createdAt'],
                        'resumen' => 'form/verinstancia/' . $instancia['id'],
                        'parent_class' => 'Forms',
                        'servicio' => null,
                        'tipo' => null,
                        'parent_id' => $instancia['form']['nombre'],
                        'rr_hh' => null,
                        'efector' => null,
                        'tipo_historia' => 'Forms'
                    );
                }
            }
        } catch (\Exception $e) {
            return $respuesta;
        }

        return $respuesta;
    }

    /**
     * Busca un registro de Persona basado en su valor de clave primaria.
     * Si el modelo no se encuentra, se lanzará una excepción HTTP 404.
     * @param integer $id
     * @return Persona el modelo cargado
     * @throws NotFoundHttpException si el modelo no se encuentra
     */
    protected function findModel($id)
    {
        if (($model = Persona::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('La página solicitada no existe.');
    }

    /**
     * Obtiene la configuración de pasos para el formulario de consulta
     * @param integer|null $idConsulta ID de la consulta existente
     * @param Persona $paciente Paciente
     * @param string $parent Tipo de parent
     * @param integer $parentId ID del parent
     * @return array Configuración de pasos
     */
    private function obtenerConfiguracion($idConsulta, $paciente, $parent, $parentId)
    {
        if ($idConsulta) {
            $modelConsulta = Consulta::findOne($idConsulta);
            if ($modelConsulta) {
                $configuracion = \common\models\ConsultasConfiguracion::findOne($modelConsulta->id_configuracion);
            }
        } else {
            // Determinar configuración basada en el servicio y encounter class
            $resultadoValidacion = \common\models\ConsultasConfiguracion::validarPermisoAtencion($parent, $parentId, $paciente);
            if (!$resultadoValidacion['success']) {
                return $resultadoValidacion;
            }

            /** @var ?\common\models\ConsultasConfiguracion $configuracion */
            $configuracion = \common\models\ConsultasConfiguracion::find()
                ->where(['id_servicio' => $resultadoValidacion['idServicio']])
                ->andWhere(['encounter_class' => $resultadoValidacion['encounterClass']])
                ->andWhere('deleted_at is null')
                ->one();
        }

        if (!is_object($configuracion)) {
            return ['success' => false, 'msg' => 'Error: Servicio sin configuración'];
        }

        return ['success' => true, 'msg' => '', 'idConfiguracion' => $configuracion->id];
    }

}
