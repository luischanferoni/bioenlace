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
/**
 * API Turnos: lógica de turnos expuesta como endpoints REST-ish.
 *
 * La lógica se implementa directamente aquí, sin usar el controlador del frontend.
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
     * Lista los turnos del usuario autenticado (paciente). GET fecha_desde, fecha_hasta opcionales. Respuesta JSON.
     */
    public function actionMisTurnos()
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
     * Listar turnos por rrhh/fecha (lógica para API). Retorna array para JSON.
     */
    public function actionIndex()
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
            Yii::error('TurnosController::actionIndexApi: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), 'api-turnos');
            throw new \yii\web\ServerErrorHttpException('Error al obtener turnos: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Detalle de un turno por id (lógica para API).
     */
    public function actionView($id)
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
     * Crear turno (lógica para API). POST. Retorna array o lanza excepción.
     */
    public function actionCreate()
    {
        $request = Yii::$app->request;
        $model = new Turno();
        $model->load($request->post(), '');
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
            throw new BadRequestHttpException(implode(', ', $model->getFirstErrors()));
        }
        $idConsulta = null;
        $consulta = Consulta::createFromTurno($model);
        if ($consulta) {
            $idConsulta = (int)$consulta->id_consulta;
        }
        return [
            'id' => $model->id_turnos,
            'fecha' => $model->fecha,
            'hora' => $model->hora,
            'id_consulta' => $idConsulta,
        ];
    }

    /**
     * Actualizar turno (lógica para API).
     */
    public function actionUpdate($id)
    {
        $turno = Turno::findOne($id);
        if (!$turno) {
            throw new NotFoundHttpException('Turno no encontrado');
        }
        $turno->load(Yii::$app->request->post(), '');
        if (!$turno->save()) {
            throw new BadRequestHttpException(implode(', ', $turno->getFirstErrors()));
        }
        return [
            'id' => $turno->id_turnos,
            'estado' => $turno->estado,
        ];
    }

    /**
     * Próximo turno disponible por servicio (lógica para API).
     */
    public function actionProximoDisponible()
    {
        $request = Yii::$app->request;
        $idServicio = $request->get('id_servicio') ?: $request->post('id_servicio');
        if (!$idServicio) {
            throw new BadRequestHttpException('El parámetro id_servicio es obligatorio');
        }
        $criteria = [
            'id_servicio' => (int)$idServicio,
            'id_efector' => $request->get('id_efector') ?: $request->post('id_efector'),
            'fecha_desde' => $request->get('fecha_desde') ?: $request->post('fecha_desde'),
            'max_dias' => $request->get('max_dias') ?: $request->post('max_dias'),
        ];
        $restricciones = $request->get('restricciones') ?: $request->post('restricciones');
        if (!empty($restricciones) && is_array($restricciones)) {
            $criteria['restricciones'] = $restricciones;
        }
        try {
            $slot = TurnoSlotFinder::findFirstAvailable($criteria);
        } catch (\Throwable $e) {
            Yii::warning('TurnoSlotFinder::findFirstAvailable error: ' . $e->getMessage(), 'api-turnos');
            throw new \yii\web\ServerErrorHttpException('No se pudo calcular el próximo turno disponible');
        }
        if ($slot === null) {
            return [
                'disponible' => false,
                'slot' => null,
                'message' => 'No hay turnos disponibles en el rango de búsqueda.',
            ];
        }
        return [
            'disponible' => true,
            'slot' => $slot,
            'message' => sprintf('Próximo turno: %s a las %s', $slot['fecha'], $slot['hora']),
        ];
    }

    /**
     * Este método carga las horas disponibles por día.
     */
    public function actionEventos()
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
