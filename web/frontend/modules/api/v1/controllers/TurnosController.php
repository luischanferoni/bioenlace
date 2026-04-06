<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\NotFoundHttpException;
use yii\web\BadRequestHttpException;
use common\models\Consulta;
use common\models\Turno;
use common\models\Agenda_rrhh;
use common\models\AgendaFeriados;
use common\models\RrhhEfector;
use common\models\RrhhServicio;
use common\models\ServiciosEfector;
use common\models\ConsultaDerivaciones;
use common\models\Persona;
use common\components\Services\Turnos\TurnoSlotFinder;
use common\components\Services\Turnos\TurnoSlotOfferService;
use common\components\Services\Turnos\TurnoLifecycleService;
use common\components\Services\Turnos\TurnoConfirmationService;
use common\components\Services\Turnos\PolicyModeradaException;
use common\components\Services\Turnos\BulkCancelDayService;
use common\components\Services\Turnos\SobreturnoService;
use common\models\EfectorTurnosConfig;
use yii\web\ForbiddenHttpException;
use yii\web\ConflictHttpException;
/**
 * {@see AgendaController} — GET /api/v1/agenda/dia (permiso /api/agenda/dia).
 */
class TurnosController extends BaseController
{
    public function actions()
    {
        $actions = parent::actions();
        unset($actions['index'], $actions['view'], $actions['create'], $actions['update']);
        return $actions;
    }

    /**
     * Turnos donde el usuario es paciente. GET /api/v1/turnos/listar-como-paciente (fecha_desde, fecha_hasta opcionales).
     *
     * @action_name Mis turnos y citas (paciente)
     * @entity Turnos
     * @tags turnos, paciente, citas, calendario, autogestión
     * @keywords mis citas como paciente, calendario de turnos, próximos turnos, ver mis turnos pendientes
     */
    public function actionListarComoPaciente()
    {
        $idPersona = Yii::$app->user->getIdPersona();

        $request = Yii::$app->request;
        $fechaDesde = $request->get('fecha_desde', date('Y-m-d'));
        $fechaHasta = $request->get('fecha_hasta', date('Y-m-d', strtotime('+3 months')));

        $turnos = Turno::find()
            ->where(['id_persona' => $idPersona])
            ->andWhere(['>=', 'fecha', $fechaDesde])
            ->andWhere(['<=', 'fecha', $fechaHasta])
            ->andWhere(['estado' => Turno::ESTADO_PENDIENTE])
            ->orderBy(['fecha' => SORT_ASC, 'hora' => SORT_ASC])
            ->all();

        $formattedTurnos = [];
        foreach ($turnos as $turno) {
            $servicio = $turno->servicio ? $turno->servicio->nombre :
                ($turno->rrhhServicioAsignado ? $turno->rrhhServicioAsignado->servicio->nombre : 'Sin servicio');
            $consulta = Consulta::findOne(['id_turnos' => $turno->id_turnos]);
            $profesional = $turno->rrhh && $turno->rrhh->persona
                ? $turno->rrhh->persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N_D)
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
                'created_at' => $turno->created_at,
            ];
        }

        return [
            'success' => true,
            'message' => 'OK',
            'data' => [
                'turnos' => $formattedTurnos,
                'total' => count($formattedTurnos),
            ],
        ];
    }

    /**
     * Respuesta JSON agenda del día (profesional). Usado por {@see AgendaController::actionDia}.
     *
     * @return array<string, mixed>
     */
    public static function agendaDiaResponse(): array
    {
        $request = Yii::$app->request;
        $fecha = $request->get('fecha', date('Y-m-d'));
        $rrhhId = $request->get('rrhh_id');
        if (!$rrhhId) {
            $rrhhId = Yii::$app->user->getIdRecursoHumano();
        }
        if (!$rrhhId) {
            throw new BadRequestHttpException('No se pudo determinar el recurso humano. Proporcione rrhh_id o user_id para desarrollo.');
        }
        $rrhh = RrhhEfector::findOne($rrhhId);
        if (!$rrhh) {
            throw new NotFoundHttpException('Recurso humano no encontrado.');
        }
        try {
            return PacientesController::agendaAmbulatorioJson($fecha, (int) $rrhhId, true);
        } catch (\Throwable $e) {
            Yii::error('TurnosController::agendaDiaResponse: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), 'api-turnos');
            throw new \yii\web\ServerErrorHttpException('Error al obtener turnos: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Detalle de un turno por id. GET /api/v1/turnos/{id}. RBAC: /api/turnos/ver-turno
     */
    public function actionVerTurno($id)
    {
        $turno = Turno::findOne($id);
        if (!$turno) {
            throw new NotFoundHttpException('Turno no encontrado');
        }
        $paciente = $turno->persona;
        $servicio = $turno->servicio ? $turno->servicio->nombre :
            ($turno->rrhhServicioAsignado ? $turno->rrhhServicioAsignado->servicio->nombre : 'Sin servicio');
        return [
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
            'estado_label' => $turno->estado ? (Turno::ESTADOS[$turno->estado] ?? 'Sin estado') : 'Sin estado',
            'estado_motivo' => $turno->estado_motivo,
            'atendido' => $turno->atendido,
            'id_efector' => $turno->id_efector,
            'parent_class' => $turno->parent_class,
            'parent_id' => $turno->parent_id,
            'created_at' => $turno->created_at,
            'updated_at' => $turno->updated_at,
        ];
    }

    /**
     * Alta de turno en autogestión (“como paciente”): el beneficiario es siempre la persona del usuario autenticado.
     *
     * @action_name Reservar turno
     * @entity Turnos
     * @tags turnos, paciente, reserva, cita, autogestión
     * @keywords reservar turno, agendar cita, sacar turno, pedir turno
     * HTTP: POST /api/v1/turnos. RBAC: /api/turnos/crear-como-paciente. Ignora id_persona en el cuerpo.
     */
    public function actionCrearComoPaciente()
    {
        $request = Yii::$app->request;
        $model = new Turno();
        $model->load($request->post(), '');
        $model->id_persona = (int) Yii::$app->user->getIdPersona();

        return $this->persistTurnoCreacion($model);
    }

    /**
     * Alta de turno en gestión operativa: el beneficiario es el paciente indicado en el cuerpo (id_persona obligatorio).
     *
     * HTTP: POST /api/v1/turnos/para-paciente. RBAC: /api/turnos/crear-para-paciente (no asignar al rol paciente).
     */
    public function actionCrearParaPaciente()
    {
        $request = Yii::$app->request;
        $model = new Turno();
        $model->load($request->post(), '');
        if (!$model->id_persona) {
            throw new BadRequestHttpException('El campo id_persona es obligatorio');
        }

        return $this->persistTurnoCreacion($model);
    }

    /**
     * Persistencia y reglas comunes tras armar el modelo (id_persona ya resuelto).
     * @return array{id: int, fecha: mixed, hora: mixed, id_consulta: int|null}
     */
    protected function persistTurnoCreacion(Turno $model): array
    {
        if (empty($model->tipo_atencion)) {
            $model->tipo_atencion = Turno::TIPO_ATENCION_PRESENCIAL;
        }
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
                    throw new BadRequestHttpException('El profesional seleccionado no acepta consultas por chat. Elegí atención presencial u otro profesional.');
                }
            } elseif ($model->id_rrhh_servicio_asignado || $model->id_rr_hh) {
                throw new BadRequestHttpException('No se encontró la agenda del profesional para el servicio.');
            }
        }
        if (!$model->id_efector) {
            $model->id_efector = Yii::$app->user->getIdEfector();
        }
        if (!$model->id_efector) {
            throw new BadRequestHttpException('No se pudo determinar el efector');
        }
        $authPersona = Yii::$app->user->getIdPersona();
        if ($authPersona && (int) $model->id_persona === (int) $authPersona) {
            $pol = new \common\components\Services\Turnos\TurnoCancellationPolicyService();
            if ($pol->autogestionBloqueada((int) $model->id_persona, (int) $model->id_efector)) {
                throw new ConflictHttpException('Reserva por app no disponible por política de cancelaciones: acercate al efector o llamá.');
            }
        }
        if (!$model->id_rr_hh) {
            $model->id_rr_hh = Yii::$app->user->getIdRecursoHumano();
        }
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
        if (!$model->id_servicio_asignado) {
            throw new BadRequestHttpException('El campo id_servicio_asignado es obligatorio');
        }
        $servicioEfector = ServiciosEfector::find()
            ->where(['id_servicio' => $model->id_servicio_asignado])
            ->andWhere(['id_efector' => $model->id_efector])
            ->one();
        if ($servicioEfector) {
            if ($servicioEfector->formas_atencion == ServiciosEfector::ORDEN_LLEGADA_PARA_TODOS) {
                $model->scenario = ServiciosEfector::ORDEN_LLEGADA_PARA_TODOS;
            } elseif ($servicioEfector->formas_atencion == ServiciosEfector::DELEGAR_A_CADA_RRHH) {
                $model->scenario = ServiciosEfector::DELEGAR_A_CADA_RRHH;
                if ($model->id_rrhh_servicio_asignado) {
                    $agenda = Agenda_rrhh::find()
                        ->andWhere(['id_rrhh_servicio_asignado' => $model->id_rrhh_servicio_asignado])
                        ->one();
                    if ($agenda) {
                        $cantTurnosOtorgados = Turno::cantidadDeTurnosOtorgados($model->id_rrhh_servicio_asignado, $model->fecha);
                        if ($agenda->cupo_pacientes != 0 && $agenda->cupo_pacientes <= $cantTurnosOtorgados) {
                            throw new BadRequestHttpException('Ya se otorgaron todos los turnos correspondientes al límite establecido');
                        }
                    }
                }
            }
        }
        if (!$model->save()) {
            throw new BadRequestHttpException(implode(', ', $model->getErrorSummary(true)));
        }
        $idConsulta = null;
        $consulta = Consulta::createFromTurno($model);
        if ($consulta) {
            $idConsulta = (int)$consulta->id_consulta;
        }
        try {
            (new TurnoLifecycleService())->afterTurnoCreado($model);
        } catch (\Throwable $e) {
            Yii::warning('afterTurnoCreado: ' . $e->getMessage(), 'api-turnos');
        }
        return [
            'id' => $model->id_turnos,
            'fecha' => $model->fecha,
            'hora' => $model->hora,
            'id_consulta' => $idConsulta,
        ];
    }

    /**
     * Actualizar turno existente. PUT/PATCH /api/v1/turnos/{id}. RBAC: /api/turnos/actualizar-turno
     */
    public function actionActualizarTurno($id)
    {
        $turno = Turno::findOne($id);
        if (!$turno) {
            throw new NotFoundHttpException('Turno no encontrado');
        }
        $oldTipo = $turno->tipo_atencion;
        $turno->load(Yii::$app->request->post(), '');
        if ($turno->tipo_atencion !== $oldTipo && $turno->tipo_atencion === Turno::TIPO_ATENCION_TELECONSULTA) {
            $cfg = EfectorTurnosConfig::getOrCreateForEfector((int) $turno->id_efector);
            if (!$cfg->permitir_cambio_modalidad) {
                throw new BadRequestHttpException('Cambio de modalidad no permitido en este efector');
            }
            $idRrhhServicio = $turno->id_rrhh_servicio_asignado;
            if ($idRrhhServicio) {
                $aceptaOnline = Agenda_rrhh::find()
                    ->andWhere(['id_rrhh_servicio_asignado' => $idRrhhServicio])
                    ->andWhere(['acepta_consultas_online' => true])
                    ->exists();
                if (!$aceptaOnline) {
                    throw new BadRequestHttpException('El profesional no acepta teleconsulta para esta agenda');
                }
            }
        }
        if (!$turno->save()) {
            throw new BadRequestHttpException(implode(', ', $turno->getFirstErrors()));
        }
        return [
            'id' => $turno->id_turnos,
            'estado' => $turno->estado,
            'tipo_atencion' => $turno->tipo_atencion,
        ];
    }

    /**
     * Política de autogestión (cancelaciones) para el paciente autenticado y efector actual.
     * GET /api/v1/turnos/politica-como-paciente. RBAC: /api/turnos/politica-como-paciente
     */
    public function actionPoliticaComoPaciente()
    {
        $idEfector = Yii::$app->user->getIdEfector();
        if (!$idEfector) {
            throw new BadRequestHttpException('No se pudo determinar el efector; configure sesión operativa (sesion-operativa/establecer).');
        }
        $idPersona = (int) Yii::$app->user->getIdPersona();
        $svc = new \common\components\Services\Turnos\TurnoCancellationPolicyService();
        return array_merge(['success' => true], $svc->evaluarAutogestion($idPersona, $idEfector));
    }

    /**
     * Cancelar turno propio (autogestión). POST …/turnos/{id}/cancelar. Body: estado_motivo, canal.
     * RBAC: /api/turnos/cancelar-como-paciente
     */
    public function actionCancelarComoPaciente($id)
    {
        $turno = Turno::findActive()->andWhere(['id_turnos' => $id])->one();
        if (!$turno) {
            throw new NotFoundHttpException('Turno no encontrado');
        }
        $idPersona = Yii::$app->user->getIdPersona();
        if ((int) $turno->id_persona !== (int) $idPersona) {
            throw new ForbiddenHttpException('No autorizado');
        }
        $req = Yii::$app->request;
        $motivo = $req->post('estado_motivo', Turno::ESTADO_MOTIVO_CANCELADO_PACIENTE);
        $canal = $req->post('canal', 'app');
        if (!in_array($motivo, [Turno::ESTADO_MOTIVO_CANCELADO_PACIENTE, Turno::ESTADO_MOTIVO_CANCELADO_MEDICO], true)) {
            $motivo = Turno::ESTADO_MOTIVO_CANCELADO_PACIENTE;
        }
        $life = new TurnoLifecycleService();
        try {
            $life->cancelar($turno, $motivo, $canal, Yii::$app->user->id ?? null);
        } catch (PolicyModeradaException $e) {
            Yii::$app->response->statusCode = 409;
            return [
                'success' => false,
                'code' => 'CANCEL_POLICY_MODERADA',
                'message' => $e->getMessage(),
            ];
        }
        return ['success' => true, 'message' => 'Turno cancelado'];
    }

    /**
     * Cancelar turno en gestión operativa (staff). POST /api/v1/turnos/{id}/cancelar-operativo.
     * Body: estado_motivo (opcional), canal (opcional).
     * RBAC: /api/turnos/cancelar-operativo
     */
    public function actionCancelarOperativo($id)
    {
        $turno = Turno::findActive()->andWhere(['id_turnos' => $id])->one();
        if (!$turno) {
            throw new NotFoundHttpException('Turno no encontrado');
        }
        $idEfector = Yii::$app->user->getIdEfector();
        if ($idEfector && (int) $turno->id_efector !== (int) $idEfector) {
            throw new ForbiddenHttpException('No autorizado');
        }

        $req = Yii::$app->request;
        $motivo = $req->post('estado_motivo');
        if ($motivo === null) {
            $motivo = $req->post('Turno')['estado_motivo'] ?? null;
        }
        if (!$motivo) {
            $motivo = Turno::ESTADO_MOTIVO_CANCELADO_MEDICO;
        }
        $canal = $req->post('canal', 'web');

        (new TurnoLifecycleService())->cancelar($turno, (string) $motivo, (string) $canal, Yii::$app->user->id ?? null);
        return ['success' => true, 'message' => 'Turno cancelado'];
    }

    /**
     * Slots alternativos para reprogramar turno propio. GET …/turnos/{id}/slots-alternativos.
     * RBAC: /api/turnos/slots-alternativos-como-paciente
     */
    public function actionSlotsAlternativosComoPaciente($id)
    {
        $turno = Turno::findActive()->andWhere(['id_turnos' => $id])->one();
        if (!$turno) {
            throw new NotFoundHttpException('Turno no encontrado');
        }
        $idPersona = Yii::$app->user->getIdPersona();
        if ((int) $turno->id_persona !== (int) $idPersona) {
            throw new ForbiddenHttpException('No autorizado');
        }
        $req = Yii::$app->request;
        $limit = (int) $req->get('limit', 15);
        $mismoProf = $req->get('mismo_profesional', '1') === '1' || $req->get('mismo_profesional') === true;
        $criteria = [
            'id_servicio' => (int) $turno->id_servicio_asignado,
            'id_efector' => (int) $turno->id_efector,
            'fecha_desde' => date('Y-m-d'),
            'max_dias' => (int) $req->get('max_dias', 45),
        ];
        if ($mismoProf && (int) $turno->id_rrhh_servicio_asignado > 0) {
            $criteria['id_rrhh_servicio_asignado'] = (int) $turno->id_rrhh_servicio_asignado;
        }
        $slots = TurnoSlotFinder::findAvailableSlots($criteria, max(1, $limit));
        return ['success' => true, 'slots' => $slots];
    }

    /**
     * Confirmar asistencia al turno propio. POST …/turnos/{id}/confirmar-asistencia (body token opcional).
     * RBAC: /api/turnos/confirmar-asistencia-como-paciente
     */
    public function actionConfirmarAsistenciaComoPaciente($id)
    {
        $turno = Turno::findActive()->andWhere(['id_turnos' => $id])->one();
        if (!$turno) {
            throw new NotFoundHttpException('Turno no encontrado');
        }
        $idPersona = Yii::$app->user->getIdPersona();
        if ((int) $turno->id_persona !== (int) $idPersona) {
            throw new ForbiddenHttpException('No autorizado');
        }
        $token = Yii::$app->request->post('token');
        if ($token && $turno->confirmacion_token && !hash_equals((string) $turno->confirmacion_token, (string) $token)) {
            throw new BadRequestHttpException('Token inválido');
        }
        (new TurnoConfirmationService())->confirmarAsistencia($turno, Yii::$app->user->id ?? null);
        return ['success' => true, 'message' => 'Asistencia confirmada'];
    }

    /**
     * Reprogramar turno propio. POST …/turnos/{id}/reprogramar. RBAC: /api/turnos/reprogramar-como-paciente
     */
    public function actionReprogramarComoPaciente($id)
    {
        $turno = Turno::findActive()->andWhere(['id_turnos' => $id])->one();
        if (!$turno) {
            throw new NotFoundHttpException('Turno no encontrado');
        }
        $idPersona = Yii::$app->user->getIdPersona();
        if ((int) $turno->id_persona !== (int) $idPersona) {
            throw new ForbiddenHttpException('No autorizado');
        }
        $policy = new \common\components\Services\Turnos\TurnoCancellationPolicyService();
        if ($policy->autogestionBloqueada($idPersona, (int) $turno->id_efector)) {
            Yii::$app->response->statusCode = 409;
            return [
                'success' => false,
                'code' => 'REPROGRAM_POLICY_MODERADA',
                'message' => 'Reprogramación por app no disponible: acercate al efector o llamá.',
            ];
        }
        $req = Yii::$app->request;
        $fecha = $req->post('fecha');
        $hora = $req->post('hora');
        $idRrsa = (int) $req->post('id_rrhh_servicio_asignado', $turno->id_rrhh_servicio_asignado);
        if (!$fecha || !$hora) {
            throw new BadRequestHttpException('fecha y hora requeridos');
        }
        if (Turno::estaOcupadoSlot($idRrsa, $fecha, $hora)) {
            throw new BadRequestHttpException('El horario ya no está disponible');
        }
        $turno->fecha = $fecha;
        $turno->hora = $hora;
        $turno->id_rrhh_servicio_asignado = $idRrsa;
        $rr = RrhhServicio::findOne($idRrsa);
        if ($rr) {
            $turno->id_rr_hh = $rr->id_rr_hh;
        }
        if (!$turno->save()) {
            throw new BadRequestHttpException(implode(', ', $turno->getFirstErrors()));
        }
        \common\models\TurnoNotificacionProgramada::cancelarPendientesPorTurno($turno->id_turnos);
        try {
            $conf = new TurnoConfirmationService();
            $conf->ensureConfirmacionToken($turno);
            $conf->programarNotificaciones($turno);
        } catch (\Throwable $e) {
            Yii::warning('reprogramar notif: ' . $e->getMessage(), 'api-turnos');
        }
        return ['success' => true, 'id' => $turno->id_turnos, 'fecha' => $turno->fecha, 'hora' => $turno->hora];
    }

    /**
     * Marcar "no se presentó" en gestión operativa (staff). POST /api/v1/turnos/{id}/no-se-presento.
     * RBAC: /api/turnos/no-se-presento
     */
    public function actionNoSePresento($id)
    {
        $turno = Turno::findOne((int) $id);
        if (!$turno) {
            throw new NotFoundHttpException('Turno no encontrado');
        }

        $idRrhhServicio = Yii::$app->user->getIdRrhhServicio();
        $idServicio = Yii::$app->user->getServicioActual();

        if (
            $idRrhhServicio
            && (int) $turno->id_rrhh_servicio_asignado === (int) $idRrhhServicio
        ) {
            Turno::NoSePresento($turno->id_turnos);
            return ['success' => true, 'message' => 'El paciente no se presentó'];
        }

        if (
            (int) $turno->id_rrhh_servicio_asignado === 0
            && $idServicio
            && (int) $turno->id_servicio_asignado === (int) $idServicio
        ) {
            Turno::NoSePresento($turno->id_turnos);
            return ['success' => true, 'message' => 'El paciente no se presentó'];
        }

        throw new ForbiddenHttpException('No autorizado');
    }

    /**
     * Cancelación masiva del día en el efector (AdminEfector). POST …/cancelar-dia-efector.
     * RBAC: /api/turnos/cancelar-dia-efector
     */
    public function actionCancelarDiaEfector()
    {
        if (!\common\models\User::hasRole('AdminEfector')) {
            throw new ForbiddenHttpException('Solo administrador de efector');
        }
        $fecha = Yii::$app->request->post('fecha');
        if (!$fecha) {
            throw new BadRequestHttpException('fecha requerida');
        }
        $idEfector = Yii::$app->user->getIdEfector();
        $idRrhh = Yii::$app->request->post('id_rr_hh');
        $idRrhh = $idRrhh !== null && $idRrhh !== '' ? (int) $idRrhh : null;
        $n = (new BulkCancelDayService())->cancelarDia($idEfector, $fecha, $idRrhh, Yii::$app->user->id);
        return ['success' => true, 'cancelados' => $n];
    }

    /**
     * Sobreturno urgente en gestión operativa (staff). POST /api/v1/turnos/crear-sobreturno.
     * Body: id_persona, fecha, hora, id_rrhh_servicio_asignado, id_servicio_asignado, (id_efector opcional).
     * RBAC: /api/turnos/crear-sobreturno
     */
    public function actionCrearSobreturno()
    {
        $req = Yii::$app->request;

        $model = new Turno();
        $model->load($req->post(), '');

        if (!$model->id_persona) {
            throw new BadRequestHttpException('El campo id_persona es obligatorio');
        }
        if (!$model->id_efector) {
            $model->id_efector = Yii::$app->user->getIdEfector();
        }
        if (!$model->id_efector) {
            throw new BadRequestHttpException('No se pudo determinar el efector');
        }
        if (!$model->id_rr_hh) {
            $model->id_rr_hh = Yii::$app->user->getIdRecursoHumano();
        }
        if (!$model->id_servicio) {
            $model->id_servicio = Yii::$app->user->getServicioActual();
        }

        if (!$model->id_servicio_asignado) {
            throw new BadRequestHttpException('El campo id_servicio_asignado es obligatorio');
        }
        if (!$model->fecha || !$model->hora) {
            throw new BadRequestHttpException('fecha y hora son obligatorios');
        }

        $model->es_sobreturno = true;

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

        $servicioEfector = ServiciosEfector::find()
            ->where(['id_servicio' => $model->id_servicio_asignado])
            ->andWhere(['id_efector' => $model->id_efector])
            ->one();
        if ($servicioEfector) {
            if ($servicioEfector->formas_atencion == ServiciosEfector::ORDEN_LLEGADA_PARA_TODOS) {
                $model->scenario = ServiciosEfector::ORDEN_LLEGADA_PARA_TODOS;
            } elseif ($servicioEfector->formas_atencion == ServiciosEfector::DELEGAR_A_CADA_RRHH) {
                $model->scenario = ServiciosEfector::DELEGAR_A_CADA_RRHH;
                // Sobreturno: no se aplica límite de cupo (turno urgente).
            }
        }

        if (!$model->save()) {
            throw new BadRequestHttpException(implode(', ', $model->getFirstErrors()));
        }
        Consulta::createFromTurno($model);
        try {
            (new SobreturnoService())->notificarRetrasoPorSobreturno($model);
            (new TurnoLifecycleService())->afterTurnoCreado($model);
        } catch (\Throwable $e) {
            Yii::warning('sobreturno post: ' . $e->getMessage(), 'api-turnos');
        }
        return ['success' => true, 'id_turno' => $model->id_turnos];
    }

    /**
     * Próximos slots libres agrupados por día y por franja (mañana / tarde). Autogestión paciente.
     *
     * GET|POST …/turnos/slots-disponibles-como-paciente. Query/body:
     * - id_servicio (obligatorio), id_efector (opcional, sesión), id_rr_hh opcional, id_rrhh_servicio_asignado opcional,
     * - limite y franja_tarde_desde (opcionales; defaults en params `turnosPaciente`),
     * - restricciones (JSON array, mismo formato que {@see TurnoSlotFinder::findAvailableSlots}).
     *
     * Nota: la búsqueda siempre inicia desde la fecha actual y usa max_dias de configuración;
     * `fecha_desde` y `max_dias` del cliente se ignoran para mantener comportamiento consistente.
     *
     * RBAC: /api/turnos/slots-disponibles-como-paciente. Firma JSON: `views/json/turnos/slots-disponibles-como-paciente.example.json`.
     * {@see TurnoSlotOfferService}
     */
    public function actionSlotsDisponiblesComoPaciente()
    {
        $req = Yii::$app->request;
        $idServicio = $req->get('id_servicio') ?: $req->post('id_servicio');
        if (!$idServicio) {
            throw new BadRequestHttpException('id_servicio es obligatorio');
        }
        $idEfector = $req->get('id_efector') ?: $req->post('id_efector');
        if (!$idEfector) {
            $idEfector = Yii::$app->user->getIdEfector();
        }
        if (!$idEfector) {
            throw new BadRequestHttpException('No se pudo determinar id_efector');
        }

        $criteria = [
            'id_servicio' => (int) $idServicio,
            'id_efector' => (int) $idEfector,
            'fecha_desde' => date('Y-m-d'),
        ];

        $idRrsa = (int) ($req->get('id_rrhh_servicio_asignado') ?: $req->post('id_rrhh_servicio_asignado') ?: 0);
        $idRrhh = $req->get('id_rr_hh') ?: $req->post('id_rr_hh');
        if ($idRrsa <= 0 && $idRrhh) {
            $resolved = RrhhServicio::obtenerIdRrhhServicio((int) $idRrhh, (int) $idServicio);
            if ($resolved) {
                $idRrsa = (int) $resolved;
            }
        }
        if ($idRrsa > 0) {
            $criteria['id_rrhh_servicio_asignado'] = $idRrsa;
        }

        $restr = $req->get('restricciones') ?: $req->post('restricciones');
        if (is_string($restr) && $restr !== '') {
            $decoded = json_decode($restr, true);
            if (is_array($decoded)) {
                $criteria['restricciones'] = $decoded;
            }
        } elseif (is_array($restr)) {
            $criteria['restricciones'] = $restr;
        }

        $defaults = TurnoSlotOfferService::leerDefaultsTurnosPaciente();
        $p = Yii::$app->params['turnosPaciente'] ?? [];
        $maxCliente = max(1, (int) ($p['slots_oferta_max_cliente'] ?? 60));

        $limiteRaw = $req->get('limite') ?: $req->post('limite');
        $limite = $limiteRaw !== null && $limiteRaw !== '' ? (int) $limiteRaw : $defaults['limite'];
        $limite = max(1, min($maxCliente, $limite));

        $maxDias = (int) $defaults['max_dias'];
        $maxDias = max(1, min(90, $maxDias));

        $franjaRaw = $req->get('franja_tarde_desde') ?: $req->post('franja_tarde_desde');
        $franja = $franjaRaw !== null && $franjaRaw !== '' ? (string) $franjaRaw : $defaults['franja_tarde_desde'];
        if (!preg_match('/^\d{2}:\d{2}$/', $franja)) {
            $franja = $defaults['franja_tarde_desde'];
        }

        try {
            $grouped = TurnoSlotOfferService::buildGrouped($criteria, $limite, $maxDias, $franja);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        } catch (\RuntimeException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        return array_merge(['success' => true], $grouped, [
            'criterios' => [
                'id_servicio' => (int) $idServicio,
                'id_efector' => (int) $idEfector,
                'id_rrhh_servicio_asignado' => $criteria['id_rrhh_servicio_asignado'] ?? null,
                'fecha_desde' => $criteria['fecha_desde'],
            ],
        ]);
    }

    /**
     * Ocupación de agenda por día (horarios tomados / eventos calendario). GET|POST …/turnos/eventos.
     * RBAC: /api/turnos/consultar-ocupacion-dia
     */
    public function actionConsultarOcupacionDia()
    {
        $request = Yii::$app->request;
        $dia = $request->get('dia') ?: $request->post('dia') ?: date('Y-m-d');
        $id_rrhh_servicio_asignado = (int)($request->get('id_rrhh_servicio_asignado') ?: $request->post('id_rrhh_servicio_asignado') ?: 0);
        $id_servicio = $request->get('id_servicio') ?: $request->post('id_servicio');
        $id_rr_hh = $request->get('id_rr_hh') ?: $request->post('id_rr_hh');

        if ($id_rrhh_servicio_asignado === 0 && $id_rr_hh && $id_servicio) {
            $resolved = RrhhServicio::obtenerIdRrhhServicio($id_rr_hh, $id_servicio);
            if ($resolved) {
                $id_rrhh_servicio_asignado = (int)$resolved;
            }
        }

        $id_efector = Yii::$app->user->getIdEfector();

        $formatoSlots = ($request->get('formato') ?: $request->post('formato')) === 'slots';

        $turnosQuery = Turno::findActive();
        if ($id_rrhh_servicio_asignado) {
            $turnosQuery->andWhere(['id_rrhh_servicio_asignado' => $id_rrhh_servicio_asignado]);
        } else {
            $turnosQuery->andWhere(['id_efector' => $id_efector])
                ->andWhere(['id_servicio_asignado' => $id_servicio]);
        }

        $turnos = $turnosQuery->andWhere(['fecha' => $dia])
            ->andWhere(['estado' => Turno::ESTADOS_PARA_DESHABILITAR])
            ->orderBy('hora')
            ->all();

        $feriado = AgendaFeriados::getFeriadosPorFecha($dia);
        $mensajeFeriado = '';

        if ($feriado != null) {
            $mensajeFeriado = '<h5 class="ps-5"><u><strong>No se pueden asignar turnos para un dia feriado.</strong></u></h5>';
        }

        $horasTomadas = [];
        foreach ($turnos as $turno) {
            $horasTomadas[] = $turno->hora;
        }

        if ($formatoSlots) {
            return [
                'dia' => $dia,
                'id_rrhh_servicio_asignado' => $id_rrhh_servicio_asignado,
                'id_servicio' => $id_servicio,
                'horas_tomadas' => $horasTomadas,
                'mensaje_feriado' => $mensajeFeriado,
            ];
        }

        $eventos = [];
        foreach ($turnos as $turno) {
            $eventos[] = [
                'title' => 'Ocupado',
                'start' => $turno->fecha . 'T' . $turno->hora,
                'allDay' => false,
            ];
        }

        return [
            'eventos' => $eventos,
            'mensaje_feriado' => $mensajeFeriado,
        ];
    }
}
