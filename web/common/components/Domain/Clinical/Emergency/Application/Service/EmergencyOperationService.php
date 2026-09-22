<?php

namespace common\components\Domain\Clinical\Emergency\Application\Service;

use common\components\Domain\Clinical\Emergency\Domain\BoardState;
use common\components\Domain\Clinical\Emergency\Domain\BoardEventType;
use common\components\Domain\Clinical\Encounter\Application\Presentation\PatientHistoriaUrl;
use common\components\Domain\Organization\Pes\Application\UseCase\ActivateProfesionalHorario;
use common\models\Clinical\Encounter;
use common\models\Clinical\Emergency\EmergencyEpisode;
use Yii;

/**
 * Asignación, inicio de atención, derivación y egreso de episodios de guardia.
 */
final class EmergencyOperationService
{
    /** @var EmergencyBoardService */
    private $circuito;

    /** @var EmergencyEncounterResolver */
    private $encounterResolver;

    public function __construct(?EmergencyBoardService $circuito = null, ?EmergencyEncounterResolver $encounterResolver = null)
    {
        $this->circuito = $circuito ?? new EmergencyBoardService();
        $this->encounterResolver = $encounterResolver ?? new EmergencyEncounterResolver();
    }

    /**
     * @return array<string, mixed>
     */
    public function asignar(int $guardiaId, int $idPes, int $idEfector): array
    {
        $guardia = $this->loadGuardia($guardiaId, $idEfector);
        if ($idPes <= 0) {
            throw new \InvalidArgumentException('Se requiere id_profesional_efector_servicio.');
        }
        ActivateProfesionalHorario::assertPesPuedeAsignarEmer($idPes, $idEfector);
        $guardia->id_profesional_efector_servicio = $idPes;
        $guardia->updateAttributes(['id_profesional_efector_servicio' => $idPes]);
        $this->circuito->recordEvent($guardiaId, BoardEventType::ASIGNACION, $idPes, [
            'id_profesional_efector_servicio' => $idPes,
        ]);
        $guardia = EmergencyEpisode::find()->where(['id' => $guardiaId])->with('paciente')->one() ?? $guardia;
        (new EmergencyPushService())->notifyAssigned($guardia, $idPes);

        return $this->serializeOperacion($guardia);
    }

    /**
     * Marca en atención y devuelve URL de captura clínica (consulta se crea al abrir historia).
     * Idempotente: si el episodio ya está en atención, no vuelve a registrar el evento.
     *
     * @return array<string, mixed>
     */
    public function iniciarAtencion(int $guardiaId, int $idEfector): array
    {
        $guardia = $this->loadGuardia($guardiaId, $idEfector);
        $estado = $this->circuito->effectiveEstado($guardia);
        if ($estado === BoardState::ESPERA_TRIAGE) {
            throw new \InvalidArgumentException('Registre el triage antes de iniciar la atención.');
        }
        if ($estado === BoardState::FINALIZADO || $estado === BoardState::DERIVADO) {
            throw new \InvalidArgumentException('No se puede atender un episodio cerrado o derivado.');
        }

        $yaEnAtencion = $estado === BoardState::EN_ATENCION
            || $estado === BoardState::ATENDIDO;

        if (!$yaEnAtencion) {
            $pesId = EmergencyEfectorAccess::resolvePesId(null);
            // Atender implica tomar el caso: asignar PES de sesión si aún no hay profesional.
            if (
                $pesId !== null
                && $pesId > 0
                && (int) ($guardia->id_profesional_efector_servicio ?? 0) <= 0
            ) {
                ActivateProfesionalHorario::assertPesPuedeAsignarEmer($pesId, $idEfector);
                $guardia->id_profesional_efector_servicio = $pesId;
                $guardia->updateAttributes(['id_profesional_efector_servicio' => $pesId]);
                $this->circuito->recordEvent($guardiaId, BoardEventType::ASIGNACION, $pesId, [
                    'id_profesional_efector_servicio' => $pesId,
                    'via' => 'iniciar_atencion',
                ]);
                (new EmergencyPushService())->notifyAssigned($guardia, $pesId);
            }

            $guardia->circuito_estado = BoardState::EN_ATENCION;
            $guardia->estado = EmergencyEpisode::ESTADO_ATENDIDA;
            $guardia->updateAttributes([
                'circuito_estado' => $guardia->circuito_estado,
                'estado' => $guardia->estado,
            ]);

            $this->circuito->recordEvent(
                $guardiaId,
                BoardEventType::INICIO_ATENCION,
                EmergencyEfectorAccess::resolvePesId(null)
            );
        }

        $encounter = $this->encounterResolver->findLatestForGuardia((int) $guardia->id);
        $capturaUrl = PatientHistoriaUrl::captura(
            (int) $guardia->id_persona,
            Encounter::PARENT_GUARDIA,
            (int) $guardia->id
        );

        $out = $this->serializeOperacion($guardia);
        $out['captura_url'] = $capturaUrl;
        $out['encounter_id'] = $encounter !== null ? (int) $encounter->id : null;
        $out['inicio_atencion_registrado'] = !$yaEnAtencion;

        return $out;
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function derivar(int $guardiaId, array $body, int $idEfector): array
    {
        $guardia = $this->loadGuardia($guardiaId, $idEfector);
        $idEfectorDerivacion = (int) ($body['id_efector_derivacion'] ?? 0);
        if ($idEfectorDerivacion <= 0) {
            throw new \InvalidArgumentException('Se requiere id_efector_derivacion.');
        }
        $guardia->id_efector_derivacion = $idEfectorDerivacion;
        $guardia->condiciones_derivacion = isset($body['condiciones_derivacion'])
            ? (string) $body['condiciones_derivacion']
            : null;
        if (!empty($body['solicitar_internacion'])) {
            $idInternacionEfector = (int) ($body['notificar_internacion_id_efector'] ?? $idEfectorDerivacion);
            if ($idInternacionEfector > 0) {
                $guardia->notificar_internacion_id_efector = $idInternacionEfector;
            }
        } elseif (isset($body['notificar_internacion_id_efector'])) {
            $guardia->notificar_internacion_id_efector = (int) $body['notificar_internacion_id_efector'] ?: null;
        }
        $guardia->circuito_estado = BoardState::DERIVADO;
        $guardia->updateAttributes([
            'id_efector_derivacion' => $guardia->id_efector_derivacion,
            'condiciones_derivacion' => $guardia->condiciones_derivacion,
            'notificar_internacion_id_efector' => $guardia->notificar_internacion_id_efector,
            'circuito_estado' => $guardia->circuito_estado,
        ]);
        $this->circuito->recordEvent($guardiaId, BoardEventType::DERIVACION, null, [
            'id_efector_derivacion' => $idEfectorDerivacion,
        ]);

        return $this->serializeOperacion($guardia);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function finalizar(int $guardiaId, array $body, int $idEfector): array
    {
        $guardia = $this->loadGuardia($guardiaId, $idEfector);
        $fechaFin = isset($body['fecha_fin']) ? (string) $body['fecha_fin'] : date('d/m/Y');
        $horaFin = isset($body['hora_fin']) ? (string) $body['hora_fin'] : date('H:i');

        $guardia->scenario = EmergencyEpisode::EGRESO_PACIENTE;
        $guardia->fecha_fin = $fechaFin;
        $guardia->hora_fin = $horaFin;
        if (!$guardia->validate()) {
            throw new \InvalidArgumentException(
                'Egreso inválido: ' . json_encode($guardia->errors, JSON_UNESCAPED_UNICODE)
            );
        }
        if (!$guardia->save(false)) {
            throw new \RuntimeException('No se pudo finalizar la guardia.');
        }

        $guardia->circuito_estado = BoardState::FINALIZADO;
        $guardia->updateAttributes(['circuito_estado' => BoardState::FINALIZADO]);
        $this->circuito->recordEvent($guardiaId, BoardEventType::EGRESO, null);

        return $this->serializeOperacion($guardia);
    }

    private function loadGuardia(int $guardiaId, int $idEfector): Guardia
    {
        $guardia = EmergencyEpisode::findOne($guardiaId);
        if ($guardia === null) {
            throw new \InvalidArgumentException('Guardia no encontrada.');
        }
        EmergencyEfectorAccess::assertGuardiaEnEfector($guardia, $idEfector);

        return $guardia;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOperacion(EmergencyEpisode $guardia): array
    {
        return [
            'id' => (int) $guardia->id,
            'id_persona' => (int) $guardia->id_persona,
            'estado' => $guardia->estado,
            'circuito_estado' => $this->circuito->effectiveEstado($guardia),
            'circuito_estado_label' => BoardState::label($this->circuito->effectiveEstado($guardia)),
            'id_profesional_efector_servicio' => $guardia->id_profesional_efector_servicio,
        ];
    }
}
