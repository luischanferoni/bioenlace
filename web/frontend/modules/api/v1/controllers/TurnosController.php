<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\NotFoundHttpException;
use common\models\Turno;
use common\models\Persona;
use common\models\RrhhEfector;
use common\models\ServiciosEfector;
use common\models\Agenda_rrhh;
use common\models\ConsultaDerivaciones;
use common\models\Consulta;
use frontend\components\UserRequest;

class TurnosController extends BaseController
{
    public $modelClass = 'common\models\Turno';

    /**
     * Sobrescribir behaviors para permitir acceso sin autenticación a index (se manejará manualmente)
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        
        // Excluir index de autenticación obligatoria (se manejará manualmente con user_id)
        $except = $behaviors['authenticator']['except'] ?? [];
        if (!in_array('index', $except)) {
            $except[] = 'index';
        }
        $behaviors['authenticator']['except'] = $except;
        
        return $behaviors;
    }

    /**
     * Listar turnos con filtros
     * GET /api/v1/turnos?fecha=2024-01-01&rrhh_id=123&user_id=5748 (user_id opcional para desarrollo)
     */
    public function actionIndex()
    {
        // Forzar formato JSON
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        $request = Yii::$app->request;
        $user = Yii::$app->user->identity;
        
        // Para desarrollo: permitir usar user_id directamente si no hay autenticación válida
        $userId = null;
        if (!$user) {
            $userId = $request->get('user_id');
            if ($userId) {
                // Buscar usuario directamente
                $user = \webvimark\modules\UserManagement\models\User::findOne($userId);
                if ($user) {
                    // Simular login para este usuario
                    Yii::$app->user->login($user);
                }
            }
        }
        
        // Obtener parámetros
        $fecha = $request->get('fecha', date('Y-m-d'));
        $rrhhId = $request->get('rrhh_id');
        
        // Si no se proporciona rrhh_id, usar el del usuario autenticado
        if (!$rrhhId) {
            $rrhhId = Yii::$app->user->getIdRecursoHumano();
        }
        
        if (!$rrhhId) {
            return $this->error('No se pudo determinar el recurso humano. Proporcione rrhh_id o user_id para desarrollo.', null, 400);
        }
        
        // Obtener turnos usando el método existente del modelo
        $turnos = Turno::getTurnosPorRrhhPorFecha($fecha, $rrhhId);
        
        // Formatear respuesta
        $formattedTurnos = [];
        foreach ($turnos as $turno) {
            $paciente = $turno->persona;
            $servicio = $turno->servicio ? $turno->servicio->nombre : 
                       ($turno->rrhhServicioAsignado ? $turno->rrhhServicioAsignado->servicio->nombre : 'Sin servicio');
            
            $formattedTurnos[] = [
                'id' => $turno->id_turnos,
                'id_persona' => $turno->id_persona,
                'paciente' => [
                    'id' => $paciente ? $paciente->id_persona : null,
                    'nombre_completo' => $paciente ? $paciente->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N_D) : 'Sin paciente',
                    'documento' => $paciente ? $paciente->documento : null,
                ],
                'fecha' => $turno->fecha,
                'hora' => $turno->hora,
                'servicio' => $servicio,
                'id_servicio_asignado' => $turno->id_servicio_asignado,
                'estado' => $turno->estado,
                'estado_label' => Turno::ESTADOS[$turno->estado] ?? 'Sin estado',
                'observaciones' => $turno->observaciones,
                'atendido' => $turno->atendido,
                'created_at' => $turno->created_at,
            ];
        }
        
        return $this->success([
            'turnos' => $formattedTurnos,
            'fecha' => $fecha,
            'total' => count($formattedTurnos),
        ]);
    }

    /**
     * Obtener detalle de un turno
     * GET /api/v1/turnos/{id}
     */
    public function actionView($id)
    {
        $turno = Turno::findOne($id);
        if (!$turno) {
            return $this->error('Turno no encontrado', null, 404);
        }
        
        $paciente = $turno->persona;
        $servicio = $turno->servicio ? $turno->servicio->nombre : 
                   ($turno->rrhhServicioAsignado ? $turno->rrhhServicioAsignado->servicio->nombre : 'Sin servicio');
        
        return $this->success([
            'id' => $turno->id_turnos,
            'id_persona' => $turno->id_persona,
            'paciente' => [
                'id' => $paciente ? $paciente->id_persona : null,
                'nombre_completo' => $paciente ? $paciente->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N_D) : 'Sin paciente',
                'documento' => $paciente ? $paciente->documento : null,
                'fecha_nacimiento' => $paciente ? $paciente->fecha_nacimiento : null,
                'edad' => $paciente ? $paciente->edad : null,
            ],
            'fecha' => $turno->fecha,
            'hora' => $turno->hora,
            'servicio' => $servicio,
            'id_servicio_asignado' => $turno->id_servicio_asignado,
            'id_rrhh_servicio_asignado' => $turno->id_rrhh_servicio_asignado,
            'estado' => $turno->estado,
            'estado_label' => Turno::ESTADOS[$turno->estado] ?? 'Sin estado',
            'estado_motivo' => $turno->estado_motivo,
            'observaciones' => $turno->observaciones,
            'atendido' => $turno->atendido,
            'id_efector' => $turno->id_efector,
            'parent_class' => $turno->parent_class,
            'parent_id' => $turno->parent_id,
            'created_at' => $turno->created_at,
            'updated_at' => $turno->updated_at,
        ]);
    }

    /**
     * Crear nuevo turno
     * POST /api/v1/turnos
     */
    public function actionCreate()
    {
        $request = Yii::$app->request;
        
        $model = new Turno();
        $model->load($request->post(), '');
        
        // Validar campos obligatorios
        if (!$model->id_persona) {
            return $this->error('El campo id_persona es obligatorio', null, 422);
        }
        
        if (!$model->id_efector) {
            $model->id_efector = Yii::$app->user->getIdEfector();
        }
        
        if (!$model->id_efector) {
            return $this->error('No se pudo determinar el efector', null, 422);
        }
        
        // Si no se proporciona id_rr_hh, usar el del usuario autenticado
        if (!$model->id_rr_hh) {
            $model->id_rr_hh = Yii::$app->user->getIdRecursoHumano();
        }
        
        // Manejar derivaciones si aplica
        if ($model->id_servicio_asignado && $model->id_persona && $model->id_efector) {
            $cps = ConsultaDerivaciones::getDerivacionesPorPersona(
                $model->id_persona, 
                $model->id_efector, 
                $model->id_servicio_asignado, 
                ConsultaDerivaciones::ESTADO_EN_ESPERA
            );
            
            if (count($cps) > 0) {
                foreach ($cps as $cp) {
                    $cp->estado = ConsultaDerivaciones::ESTADO_CON_TURNO;
                    $cp->save();
                    $parent_id = $cp->id;
                }
                $model->parent_class = Consulta::PARENT_CLASSES[Consulta::PARENT_DERIVACION];
                $model->parent_id = $parent_id;
            }
        }
        
        // Validar servicio asignado
        if (!$model->id_servicio_asignado) {
            return $this->error('El campo id_servicio_asignado es obligatorio', null, 422);
        }
        
        // Validar formas de atención
        $servicioEfector = ServiciosEfector::find()
            ->where(['id_servicio' => $model->id_servicio_asignado])
            ->andWhere(['id_efector' => $model->id_efector])
            ->one();
        
        if ($servicioEfector) {
            if ($servicioEfector->formas_atencion == ServiciosEfector::ORDEN_LLEGADA_PARA_TODOS) {
                $model->scenario = ServiciosEfector::ORDEN_LLEGADA_PARA_TODOS;
            } elseif ($servicioEfector->formas_atencion == ServiciosEfector::DELEGAR_A_CADA_RRHH) {
                $model->scenario = ServiciosEfector::DELEGAR_A_CADA_RRHH;
                
                // Validar cupo
                if ($model->id_rrhh_servicio_asignado) {
                    $agenda = Agenda_rrhh::find()
                        ->andWhere(['id_rrhh_servicio_asignado' => $model->id_rrhh_servicio_asignado])
                        ->one();
                    
                    if ($agenda) {
                        $cantTurnosOtorgados = Turno::cantidadDeTurnosOtorgados(
                            $model->id_rrhh_servicio_asignado, 
                            $model->fecha
                        );
                        
                        if ($agenda->cupo_pacientes != 0 && $agenda->cupo_pacientes <= $cantTurnosOtorgados) {
                            return $this->error(
                                'Ya se otorgaron todos los turnos correspondientes al límite establecido', 
                                null, 
                                422
                            );
                        }
                    }
                }
            }
        }
        
        // Guardar turno
        if ($model->save()) {
            return $this->success([
                'id' => $model->id_turnos,
                'fecha' => $model->fecha,
                'hora' => $model->hora,
            ], 'Turno creado exitosamente', 201);
        } else {
            return $this->error('Error al crear el turno', $model->getErrors(), 422);
        }
    }

    /**
     * Actualizar turno
     * PUT /api/v1/turnos/{id}
     */
    public function actionUpdate($id)
    {
        $turno = Turno::findOne($id);
        if (!$turno) {
            return $this->error('Turno no encontrado', null, 404);
        }
        
        $request = Yii::$app->request;
        $turno->load($request->post(), '');
        
        if ($turno->save()) {
            return $this->success([
                'id' => $turno->id_turnos,
                'estado' => $turno->estado,
            ], 'Turno actualizado exitosamente');
        } else {
            return $this->error('Error al actualizar el turno', $turno->getErrors(), 422);
        }
    }
}

