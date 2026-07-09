<?php

namespace common\components\Domain\Scheduling\Service;

use common\components\Domain\Clinical\Service\EncounterLifecycleService;
use common\models\Scheduling\Turno;
use common\models\Clinical\Encounter;
use common\models\ProfesionalEfectorServicio;
use common\models\ProfesionalEfectorServicioAgenda;
use common\models\ServiciosEfector;
use common\components\Domain\Clinical\Service\ReferralRequestService;
use common\models\ConsultaDerivaciones;
use common\models\EfectorTurnosConfig;
use common\models\Servicio;
use common\models\TurnoResolucion;
use common\components\Domain\Organization\Service\Servicios\ServiciosEfectorAutogestionListadoService;
use Yii;

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

        $existing = $this->findReservaIdempotente($model);
        if ($existing !== null) {
            return $this->formatCrearResult($existing);
        }

        try {
            TurnoReservaSlotService::aplicarCamposReserva($model);
        } catch (\InvalidArgumentException $e) {
            if (str_contains($e->getMessage(), 'ya no está disponible')) {
                $existing = $this->findReservaIdempotente($model);
                if ($existing !== null) {
                    return $this->formatCrearResult($existing);
                }
            }
            throw $e;
        }

        $this->assertTeleconsultaNuevoTurno($model);

        if (
            !$model->id_efector
            && $ctx->idEfectorSesion
            && (int) ($model->id_profesional_efector_servicio ?? 0) <= 0
        ) {
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

        if ($ctx->esReservaParaSiMismo($model) && trim((string) ($model->triage_raiz ?? '')) !== '') {
            $this->applyReservaTriageToModel($model);
        }

        if ($ctx->esReservaParaSiMismo($model) && (int) ($model->id_servicio_asignado ?? 0) > 0) {
            (new ReservaTriageServicioSugeridoService())->assertPacientePuedeReservarServicio($model);
            $this->assertModalidadEspecialistaDerivacion($model);
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
                    ReferralRequestService::markBooked($cp);
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
            }
        }

        if (!$model->save()) {
            throw new \InvalidArgumentException(implode(', ', $model->getErrorSummary(true)));
        }

        try {
            (new EncounterLifecycleService())->ensureFromTurno($model);
        } catch (\Throwable $e) {
            Yii::warning('ensureFromTurno: ' . $e->getMessage(), 'api-turnos');
        }
        try {
            (new TurnoLifecycleService())->afterTurnoCreado($model);
        } catch (\Throwable $e) {
            Yii::warning('afterTurnoCreado: ' . $e->getMessage(), 'api-turnos');
        }

        return $this->formatCrearResult($model);
    }

    /**
     * @return array{
     *   id: int,
     *   fecha: mixed,
     *   hora: mixed,
     *   id_profesional_efector_servicio: int|null,
     *   servicio_detalle: array{id_servicio: int, nombre: string}|null,
     *   mensaje: string
     * }
     */
    private function formatCrearResult(Turno $model): array
    {
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

    private function findReservaIdempotente(Turno $model): ?Turno
    {
        $idPersona = (int) ($model->id_persona ?? 0);
        $idPes = (int) ($model->id_profesional_efector_servicio ?? 0);
        $fecha = trim((string) ($model->fecha ?? ''));
        $hora = trim((string) ($model->hora ?? ''));
        if ($idPersona <= 0 || $idPes <= 0 || $fecha === '' || $hora === '') {
            return null;
        }

        $horaNorm = substr(TurnoResolucion::normalizarHora($hora), 0, 5);
        if ($horaNorm === '') {
            return null;
        }

        $existing = Turno::find()
            ->andWhere([
                'id_persona' => $idPersona,
                'id_profesional_efector_servicio' => $idPes,
                'fecha' => $fecha,
                'estado' => Turno::ESTADO_PENDIENTE,
            ])
            ->andWhere(['like', 'hora', $horaNorm . '%', false])
            ->orderBy(['id_turnos' => SORT_DESC])
            ->one();

        return $existing instanceof Turno ? $existing : null;
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

    /**
     * Persiste triage declarativo de reserva (catálogo YAML) en columnas del turno.
     */
    private function applyReservaTriageToModel(Turno $model): void
    {
        $selections = [
            'triage_raiz' => $model->triage_raiz,
            'triage_alarmas' => $model->triage_alarmas,
            'triage_zona' => $model->triage_zona,
            'triage_nota' => $model->triage_nota,
        ];
        $catalog = new ReservaTurnoTriageCatalogService();
        $catalog->assertCanPersistBooking($selections);
        $compiled = $catalog->compileSelections($selections);
        $model->reserva_triage_code = $compiled['reserva_triage_code'];
        $model->urgency_band = $compiled['urgency_band'];
        $model->reserva_triage_meta_json = json_encode(
            $compiled['reserva_triage_meta_json'],
            JSON_UNESCAPED_UNICODE
        );
        if (empty($model->tipo_atencion) && $compiled['suggests_tipo_atencion'] !== null) {
            $model->tipo_atencion = $compiled['suggests_tipo_atencion'];
        }
    }

    private function assertModalidadEspecialistaDerivacion(Turno $model): void
    {
        $idServicio = (int) ($model->id_servicio_asignado ?? 0);
        if ($idServicio <= 0) {
            return;
        }

        if (!ReservaTriageAccesoConfig::especialistaSoloTeleconsultaConDerivacion()) {
            return;
        }

        $servicio = Servicio::findOne($idServicio);
        if ($servicio === null) {
            return;
        }

        if ($servicio->permiteReservaAutogestionPaciente()) {
            return;
        }

        if ((string) ($model->tipo_atencion ?? Turno::TIPO_ATENCION_PRESENCIAL) === Turno::TIPO_ATENCION_PRESENCIAL) {
            return;
        }
    }
}

