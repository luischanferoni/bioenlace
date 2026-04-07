<?php

namespace common\components\Services\Turnos;

use Yii;
use common\models\Turno;
use common\models\Consulta;
use common\models\Agenda_rrhh;
use common\models\RrhhServicio;
use common\models\ServiciosEfector;
use common\models\ConsultaDerivaciones;
use common\models\EfectorTurnosConfig;

/**
 * Persistencia y reglas comunes de turnos (API y otros callers). Sin HTTP.
 */
class TurnoPersistService
{
    /**
     * Alta: defaults, teleconsulta, derivaciones, escenario por ServiciosEfector, save, consulta collateral.
     *
     * @return array{id: int, fecha: mixed, hora: mixed, id_consulta: int|null}
     * @throws PolicyModeradaException reserva autogestion bloqueada
     * @throws \InvalidArgumentException validación de negocio o errores del AR
     */
    public function crear(Turno $model, TurnoCreacionContext $ctx): array
    {
        if (empty($model->tipo_atencion)) {
            $model->tipo_atencion = Turno::TIPO_ATENCION_PRESENCIAL;
        }

        $this->assertTeleconsultaNuevoTurno($model);

        if (!$model->id_efector && $ctx->idEfectorSesion) {
            $model->id_efector = $ctx->idEfectorSesion;
        }

        if ($ctx->esReservaParaSiMismo($model) && $model->id_efector) {
            $pol = new TurnoCancellationPolicyService();
            if ($pol->autogestionBloqueada((int) $model->id_persona, (int) $model->id_efector)) {
                throw new PolicyModeradaException(
                    'Reserva por app no disponible por política de cancelaciones: acercate al efector o llamá.'
                );
            }
        }

        if (!$model->id_rr_hh && $ctx->esReservaParaSiMismo($model) && $ctx->idRrhhSesion) {
            $model->id_rr_hh = $ctx->idRrhhSesion;
        }

        if ($model->id_servicio_asignado && $model->id_persona && $model->id_efector) {
            $cps = ConsultaDerivaciones::getDerivacionesPorPersona(
                $model->id_persona,
                $model->id_efector,
                $model->id_servicio_asignado,
                ConsultaDerivaciones::ESTADO_EN_ESPERA
            );
            if (count($cps) > 0) {
                $parent_id = null;
                foreach ($cps as $cp) {
                    $cp->estado = ConsultaDerivaciones::ESTADO_CON_TURNO;
                    $cp->save();
                    $parent_id = $cp->id;
                }
                $model->parent_class = Consulta::PARENT_CLASSES[Consulta::PARENT_DERIVACION];
                $model->parent_id = $parent_id;
            }
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
                        $cantTurnosOtorgados = Turno::cantidadDeTurnosOtorgados(
                            $model->id_rrhh_servicio_asignado,
                            $model->fecha
                        );
                        if ($agenda->cupo_pacientes != 0 && $agenda->cupo_pacientes <= $cantTurnosOtorgados) {
                            throw new \InvalidArgumentException(
                                'Ya se otorgaron todos los turnos correspondientes al límite establecido'
                            );
                        }
                    }
                }
            }
        }

        if (!$model->save()) {
            throw new \InvalidArgumentException(implode(', ', $model->getErrorSummary(true)));
        }

        $idConsulta = null;
        $consulta = Consulta::createFromTurno($model);
        if ($consulta) {
            $idConsulta = (int) $consulta->id_consulta;
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
     * Tras load() en un turno existente: validar pasaje a teleconsulta (misma regla de agenda que en alta).
     *
     * @param string $tipoAnterior valor de tipo_atencion antes del load
     * @throws \InvalidArgumentException
     */
    public function validateUpdateTeleconsultaTransition(Turno $turno, $tipoAnterior): void
    {
        if ($turno->tipo_atencion === $tipoAnterior
            || $turno->tipo_atencion !== Turno::TIPO_ATENCION_TELECONSULTA) {
            return;
        }
        $cfg = EfectorTurnosConfig::getOrCreateForEfector((int) $turno->id_efector);
        if (!$cfg->permitir_cambio_modalidad) {
            throw new \InvalidArgumentException('Cambio de modalidad no permitido en este efector');
        }
        $idRrhhServicio = $turno->id_rrhh_servicio_asignado;
        if ($idRrhhServicio) {
            $this->assertAgendaAceptaTeleconsulta((int) $idRrhhServicio);
        }
    }

    private function assertTeleconsultaNuevoTurno(Turno $model): void
    {
        if ($model->tipo_atencion !== Turno::TIPO_ATENCION_TELECONSULTA) {
            return;
        }
        $idRrhhServicio = $model->id_rrhh_servicio_asignado;
        if (!$idRrhhServicio && $model->id_rr_hh && $model->id_servicio_asignado) {
            $rs = RrhhServicio::find()
                ->andWhere(['id_rr_hh' => $model->id_rr_hh, 'id_servicio' => $model->id_servicio_asignado])
                ->select('id')
                ->one();
            $idRrhhServicio = $rs ? $rs->id : null;
        }
        if ($idRrhhServicio) {
            $this->assertAgendaAceptaTeleconsulta((int) $idRrhhServicio);
        } elseif ($model->id_rrhh_servicio_asignado || $model->id_rr_hh) {
            throw new \InvalidArgumentException('No se encontró la agenda del profesional para el servicio.');
        }
    }

    private function assertAgendaAceptaTeleconsulta(int $idRrhhServicio): void
    {
        $aceptaOnline = Agenda_rrhh::find()
            ->andWhere(['id_rrhh_servicio_asignado' => $idRrhhServicio])
            ->andWhere(['acepta_consultas_online' => true])
            ->exists();
        if (!$aceptaOnline) {
            throw new \InvalidArgumentException(
                'El profesional no acepta teleconsulta para esta agenda.'
            );
        }
    }
}
