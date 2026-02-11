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
        
        // Excluir index y mis-turnos de autenticación obligatoria (index se maneja con user_id; mis-turnos requiere auth)
        $except = $behaviors['authenticator']['except'] ?? [];
        if (!in_array('index', $except)) {
            $except[] = 'index';
        }
        if (!in_array('eventos', $except)) {
            $except[] = 'eventos';
        }
        $behaviors['authenticator']['except'] = $except;
        
        return $behaviors;
    }

    /**
     * Deshabilitar las acciones por defecto de ActiveController para usar nuestros métodos personalizados
     */
    public function actions()
    {
        $actions = parent::actions();
        
        // Deshabilitar las acciones por defecto que tenemos personalizadas
        // Esto asegura que se usen nuestros métodos actionIndex(), actionView(), actionCreate(), actionUpdate()
        unset($actions['index'], $actions['view'], $actions['create'], $actions['update']);
        
        return $actions;
    }

    /**
     * Slots disponibles para un profesional/fecha. Delega al controlador web turnos/eventos.
     * GET /api/v1/turnos/eventos?id_rr_hh=...&id_servicio=...&dia=...&formato=slots
     */
    public function actionEventos()
    {
        $controller = new \frontend\controllers\TurnosController('turnos', Yii::$app);
        return $controller->runAction('eventos');
    }

    /**
     * Mis turnos (paciente): listar turnos del usuario autenticado con tipo_atencion e id_consulta para chat.
     * GET /api/v1/turnos/mis-turnos?fecha_desde=2024-01-01&fecha_hasta=2024-12-31
     */
    public function actionMisTurnos()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $err = $this->requerirAutenticacion();
        if ($err !== null) {
            return $err;
        }
        $auth = $this->verificarAutenticacion();
        $userId = $auth['userId'];
        $persona = Persona::findOne(['id_user' => $userId]);
        if (!$persona) {
            return $this->error('No se encontró persona asociada al usuario', null, 403);
        }

        $fechaDesde = Yii::$app->request->get('fecha_desde', date('Y-m-d'));
        $fechaHasta = Yii::$app->request->get('fecha_hasta', date('Y-m-d', strtotime('+3 months')));

        $turnos = Turno::find()
            ->where(['id_persona' => $persona->id_persona])
            ->andWhere(['>=', 'fecha', $fechaDesde])
            ->andWhere(['<=', 'fecha', $fechaHasta])
            ->andWhere(['estado' => Turno::ESTADO_PENDIENTE])
            ->orderBy(['fecha' => SORT_ASC, 'hora' => SORT_ASC])
            ->all();

        $formattedTurnos = [];
        foreach ($turnos as $turno) {
            $paciente = $turno->persona;
            $servicio = $turno->servicio ? $turno->servicio->nombre :
                ($turno->rrhhServicioAsignado ? $turno->rrhhServicioAsignado->servicio->nombre : 'Sin servicio');
            $consulta = Consulta::findOne(['id_turnos' => $turno->id_turnos]);
            $profesional = $turno->rrhh && $turno->rrhh->idPersona
                ? $turno->rrhh->idPersona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N_D)
                : null;

            $formattedTurnos[] = [
                'id' => $turno->id_turnos,
                'id_persona' => $turno->id_persona,
                'fecha' => $turno->fecha,
                'hora' => $turno->hora,
                'servicio' => $servicio,
                'id_servicio_asignado' => $turno->id_servicio_asignado,
                'estado' => $turno->estado,
                'estado_label' => Turno::ESTADOS[$turno->estado] ?? 'Sin estado',
                'tipo_atencion' => isset($turno->tipo_atencion) ? $turno->tipo_atencion : Turno::TIPO_ATENCION_PRESENCIAL,
                'id_consulta' => $consulta ? $consulta->id_consulta : null,
                'profesional' => $profesional,
                'observaciones' => $turno->observaciones,
                'created_at' => $turno->created_at,
            ];
        }

        return $this->success([
            'turnos' => $formattedTurnos,
            'total' => count($formattedTurnos),
        ]);
    }

    /**
     * Listar turnos con filtros
     * GET /api/v1/turnos?fecha=2024-01-01&rrhh_id=123&user_id=5748 (user_id opcional para desarrollo)
     */
    public function actionIndex()
    {   // Forzar formato JSON
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
            
            $consulta = Consulta::findOne(['id_turnos' => $turno->id_turnos]);
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
                'tipo_atencion' => isset($turno->tipo_atencion) ? $turno->tipo_atencion : Turno::TIPO_ATENCION_PRESENCIAL,
                'id_consulta' => $consulta ? $consulta->id_consulta : null,
                'observaciones' => $turno->observaciones,
                'atendido' => $turno->atendido,
                'created_at' => $turno->created_at,
            ];
        }
        
        // Agregar turno de prueba para el paciente con id=920779 (solo si es hoy)
        $esHoy = ($fecha == date('Y-m-d'));
        if ($esHoy) {
            $pacientePrueba = Persona::findOne(920779);
            if ($pacientePrueba) {
                // Obtener el primer servicio disponible del efector o usar uno por defecto
                $servicioPrueba = null;
                $idServicioAsignado = null;
                
                if ($rrhhId) {
                    $rrhhEfector = RrhhEfector::findOne($rrhhId);
                    if ($rrhhEfector && $rrhhEfector->id_efector) {
                        $servicioEfector = ServiciosEfector::find()
                            ->where(['id_efector' => $rrhhEfector->id_efector])
                            ->one();
                        if ($servicioEfector) {
                            $servicioPrueba = $servicioEfector->servicio ? $servicioEfector->servicio->nombre : 'Consulta General';
                            $idServicioAsignado = $servicioEfector->id_servicio;
                        }
                    }
                }
                
                if (!$servicioPrueba) {
                    $servicioPrueba = 'Consulta General';
                }
                
                // Crear turno simulado
                $turnoPrueba = [
                    'id' => 999999, // ID simulado para distinguirlo
                    'id_persona' => 920779,
                    'paciente' => [
                        'id' => $pacientePrueba->id_persona,
                        'nombre_completo' => $pacientePrueba->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N_D),
                        'documento' => $pacientePrueba->documento,
                    ],
                    'fecha' => $fecha,
                    'hora' => '10:00', // Hora de prueba
                    'servicio' => $servicioPrueba,
                    'id_servicio_asignado' => $idServicioAsignado,
                    'estado' => Turno::ESTADO_PENDIENTE,
                    'estado_label' => Turno::ESTADOS[Turno::ESTADO_PENDIENTE] ?? 'Pendiente',
                    'observaciones' => 'Turno de prueba',
                    'atendido' => null,
                    'created_at' => date('Y-m-d H:i:s'),
                ];
                
                // Agregar al inicio de la lista para que aparezca primero
                array_unshift($formattedTurnos, $turnoPrueba);
            }
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
        if (empty($model->tipo_atencion)) {
            $model->tipo_atencion = Turno::TIPO_ATENCION_PRESENCIAL;
        }
        // Si es teleconsulta, validar que la agenda del profesional (Agenda_rrhh) acepte consultas online
        if ($model->tipo_atencion === Turno::TIPO_ATENCION_TELECONSULTA) {
            $idRrhhServicio = $model->id_rrhh_servicio_asignado;
            if (!$idRrhhServicio && $model->id_rr_hh && $model->id_servicio_asignado) {
                $rs = \common\models\RrhhServicio::find()
                    ->andWhere(['id_rr_hh' => $model->id_rr_hh, 'id_servicio' => $model->id_servicio_asignado])
                    ->select('id')->one();
                $idRrhhServicio = $rs ? $rs->id : null;
            }
            if ($idRrhhServicio) {
                $aceptaOnline = Agenda_rrhh::find()
                    ->andWhere(['id_rrhh_servicio_asignado' => $idRrhhServicio])
                    ->andWhere(['acepta_consultas_online' => true])
                    ->exists();
                if (!$aceptaOnline) {
                    return $this->error('El profesional seleccionado no acepta consultas por chat. Elegí atención presencial u otro profesional.', null, 422);
                }
            } elseif ($model->id_rrhh_servicio_asignado || $model->id_rr_hh) {
                return $this->error('No se encontró la agenda del profesional para el servicio.', null, 422);
            }
        }

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

