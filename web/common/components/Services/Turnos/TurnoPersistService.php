<?php

namespace common\components\Services\Turnos;

use Yii;
use common\models\Turno;
use common\models\Consulta;
use common\models\ProfesionalEfectorServicio;
use common\models\ProfesionalEfectorServicioAgenda;
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

        $model->hydrateLegacyIdsFromProfesionalEfectorServicioIfNeeded();

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
                $idPesCupo = (int) ($model->id_profesional_efector_servicio ?? 0);
                if ($idPesCupo <= 0 && $model->id_rrhh_servicio_asignado) {
                    $idPesCupo = (int) (ProfesionalEfectorServicio::resolveProfesionalEfectorServicioIdFromRrhhServicioId(
                        (int) $model->id_rrhh_servicio_asignado,
                        (int) $model->id_efector
                    ) ?: 0);
                }
                if ($idPesCupo > 0) {
                    $agenda = ProfesionalEfectorServicioAgenda::findActivaPorProfesionalEfectorServicio($idPesCupo);
                    if ($agenda) {
                        $cantTurnosOtorgados = Turno::cantidadDeTurnosOtorgadosPorProfesionalEfectorServicio(
                            $idPesCupo,
                            (string) $model->fecha
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
            $this->assertAgendaAceptaTeleconsulta((int) $idRrhhServicio, (int) $turno->id_efector);
        }
    }

    private function assertTeleconsultaNuevoTurno(Turno $model): void
    {
        if ($model->tipo_atencion !== Turno::TIPO_ATENCION_TELECONSULTA) {
            return;
        }
        $idRrhhServicio = $model->id_rrhh_servicio_asignado;
        if (!$idRrhhServicio && (int) ($model->id_profesional_efector_servicio ?? 0) > 0) {
            $pesT = ProfesionalEfectorServicio::findOne([
                'id' => (int) $model->id_profesional_efector_servicio,
                'deleted_at' => null,
            ]);
            $idRrhhServicio = $pesT !== null ? $pesT->resolveRrhhServicioAsignadoIdForTurnoCompat() : null;
        }
        if (!$idRrhhServicio && $model->id_rr_hh && $model->id_servicio_asignado && $model->id_efector) {
            $idRrhhServicio = ProfesionalEfectorServicio::resolverIdRrhhServicioDesdeRrhhServicioYEfector(
                (int) $model->id_rr_hh,
                (int) $model->id_servicio_asignado,
                (int) $model->id_efector
            );
        }
        if ($idRrhhServicio) {
            $this->assertAgendaAceptaTeleconsulta((int) $idRrhhServicio, (int) $model->id_efector);
        } elseif ($model->id_rrhh_servicio_asignado || $model->id_rr_hh) {
            throw new \InvalidArgumentException('No se encontró la agenda del profesional para el servicio.');
        }
    }

    private function assertAgendaAceptaTeleconsulta(int $idRrhhServicio, int $idEfector): void
    {
        $idPes = ProfesionalEfectorServicio::resolveProfesionalEfectorServicioIdFromRrhhServicioId($idRrhhServicio, $idEfector);
        $agenda = $idPes ? ProfesionalEfectorServicioAgenda::findActivaPorProfesionalEfectorServicio($idPes) : null;
        if ($agenda === null || !$agenda->acepta_consultas_online) {
            throw new \InvalidArgumentException(
                'El profesional no acepta teleconsulta para esta agenda.'
            );
        }
    }
}

