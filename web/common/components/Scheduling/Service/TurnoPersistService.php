<?php

namespace common\components\Scheduling\Service;

use common\components\Clinical\Service\EncounterLifecycleService;
use common\models\Turno;
use common\models\Clinical\Encounter;
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
     * @return array{
     *   id: int,
     *   fecha: mixed,
     *   hora: mixed,
     *   id_profesional_efector_servicio: int|null,
     *   servicio_detalle: array{id_servicio: int, nombre: string}|null,
     *   mensaje: string
     * }
     * @throws PolicyModeradaException reserva autogestion bloqueada
     * @throws \InvalidArgumentException validación de negocio o errores del AR
     */
    public function crear(Turno $model, TurnoCreacionContext $ctx): array
    {
        if (empty($model->tipo_atencion)) {
            $model->tipo_atencion = Turno::TIPO_ATENCION_PRESENCIAL;
        }

        $model->hydrateLegacyIdsFromProfesionalEfectorServicioIfNeeded();

        try {
            TurnoReservaSlotService::aplicarCamposReserva($model);
        } catch (\InvalidArgumentException $e) {
            throw $e;
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
                $model->parent_class = Encounter::PARENT_DERIVACION;
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
            } elseif ($servicioEfector->formas_atencion == ServiciosEfector::DELEGAR_A_CADA_PROFESIONAL) {
                $model->scenario = ServiciosEfector::DELEGAR_A_CADA_PROFESIONAL;
                $idPesCupo = (int) ($model->id_profesional_efector_servicio ?? 0);
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

        (new EncounterLifecycleService())->ensureFromTurno($model);
        try {
            (new TurnoLifecycleService())->afterTurnoCreado($model);
        } catch (\Throwable $e) {
            Yii::warning('afterTurnoCreado: ' . $e->getMessage(), 'api-turnos');
        }

        $servicioNombre = trim((string) $model->getNombreServicioParaDisplay());
        $fechaStr = (string) ($model->fecha ?? '');
        $horaStr = (string) ($model->hora ?? '');
        try {
            if ($fechaStr !== '') {
                $fechaStr = (new \DateTimeImmutable($fechaStr))->format('d/m/Y');
            }
        } catch (\Throwable $e) {
            // dejar fecha en formato ISO si no parsea
        }
        if ($horaStr !== '' && strlen($horaStr) > 5) {
            $horaStr = substr($horaStr, 0, 5);
        }
        $mensaje = $servicioNombre !== ''
            ? sprintf('Reservamos tu turno de %s el %s a las %s.', $servicioNombre, $fechaStr, $horaStr)
            : sprintf('Reservamos tu turno el %s a las %s.', $fechaStr, $horaStr);

        return [
            'id' => $model->id_turnos,
            'fecha' => $model->fecha,
            'hora' => $model->hora,
            'id_profesional_efector_servicio' => (int) ($model->id_profesional_efector_servicio ?? 0) ?: null,
            'servicio_detalle' => $model->getServicioEmbebidoParaApi(),
            'mensaje' => $mensaje,
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
        $idPes = (int) ($turno->id_profesional_efector_servicio ?? 0);
        if ($idPes > 0) {
            $this->assertAgendaAceptaTeleconsultaPorPes($idPes);
        }
    }

    private function assertTeleconsultaNuevoTurno(Turno $model): void
    {
        if ($model->tipo_atencion !== Turno::TIPO_ATENCION_TELECONSULTA) {
            return;
        }
        $idPes = (int) ($model->id_profesional_efector_servicio ?? 0);
        if ($idPes > 0) {
            $this->assertAgendaAceptaTeleconsultaPorPes($idPes);

            return;
        }
        throw new \InvalidArgumentException('Indique id_profesional_efector_servicio para teleconsulta.');
    }

    private function assertAgendaAceptaTeleconsultaPorPes(int $idPes): void
    {
        $agenda = ProfesionalEfectorServicioAgenda::findActivaPorProfesionalEfectorServicio($idPes);
        if ($agenda === null || !$agenda->acepta_consultas_online) {
            throw new \InvalidArgumentException(
                'El profesional no acepta teleconsulta para esta agenda.'
            );
        }
    }
}

