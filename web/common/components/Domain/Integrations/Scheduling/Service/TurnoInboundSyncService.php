<?php

namespace common\components\Domain\Integrations\Scheduling\Service;

use common\components\Domain\Integrations\Scheduling\Contract\FhirSchedulingInboundConnector;
use common\components\Domain\Integrations\Scheduling\Dto\FhirAppointmentInboundDto;
use common\components\Domain\Integrations\Scheduling\FhirScheduleActorExtractor;
use common\components\Domain\Integrations\Scheduling\FhirSchedulePesResolver;
use common\components\Domain\Integrations\Scheduling\Mapper\FhirAppointmentInboundMapper;
use common\components\Domain\Integrations\Scheduling\Mapper\FhirAppointmentStatusMapper;
use common\components\Domain\Integrations\Scheduling\Util\FhirBundleHelper;
use common\components\Domain\Scheduling\Service\TurnoAdvanceOfferAgent;
use common\components\Domain\Scheduling\Service\TurnoLifecycleService;
use common\components\Domain\Scheduling\Service\TurnoSlotClaimService;
use common\models\ProfesionalEfectorServicio;
use common\models\Scheduling\Turno;
use common\models\TurnoResolucion;
use Yii;

/**
 * Persiste espejo local de Appointment FHIR en {@see Turno}.
 */
final class TurnoInboundSyncService
{
    public function __construct(
        private ?FhirAppointmentInboundMapper $mapper = null,
        private ?FhirScheduleActorExtractor $actorExtractor = null,
        private ?FhirSchedulePesResolver $pesResolver = null,
        private ?TurnoFhirCanonicalEventEmitter $eventEmitter = null,
    ) {
        $this->mapper = $mapper ?? new FhirAppointmentInboundMapper();
        $this->actorExtractor = $actorExtractor ?? new FhirScheduleActorExtractor();
        $this->pesResolver = $pesResolver ?? new FhirSchedulePesResolver();
        $this->eventEmitter = $eventEmitter ?? new TurnoFhirCanonicalEventEmitter();
    }

    /**
     * @param array<string, mixed> $appointment recurso Appointment
     * @return array{action: string, id_turnos: int|null, trust: string|null}
     */
    public function upsertFromFhirAppointment(
        array $appointment,
        string $sourceSystem,
        ?FhirSchedulingInboundConnector $connector = null
    ): array {
        $sourceSystem = trim($sourceSystem);
        $dto = $this->mapper->map($appointment, $sourceSystem);
        if ($dto->externalId === '') {
            throw new \InvalidArgumentException('Appointment sin id externo.');
        }

        $scheduleId = $dto->scheduleId;
        if ($scheduleId === '' && $connector !== null) {
            $scheduleId = $this->resolveScheduleIdFromSlots($appointment, $connector);
        }

        $trust = null;
        $idPes = null;
        if ($scheduleId !== '' && $connector !== null) {
            $scheduleBundle = $connector->readSchedule($scheduleId, [
                'Schedule:actor',
            ]);
            $actors = $this->actorExtractor->extractFromBundle($scheduleBundle);
            $resolution = $this->pesResolver->resolve($sourceSystem, $scheduleId, $actors);
            $trust = $resolution['trust'];
            $idPes = $resolution['id_profesional_efector_servicio'];
        } elseif ($scheduleId !== '') {
            $trust = FhirSchedulePesResolver::TRUST_UNRESOLVED;
        }

        $turno = Turno::find()
            ->where([
                'appointment_source_system' => $sourceSystem,
                'external_appointment_id' => $dto->externalId,
            ])
            ->one();

        $isNew = $turno === null;
        $beforeEstado = $turno !== null ? (string) $turno->estado : null;
        $beforeFhirStatus = $turno !== null ? (string) ($turno->fhir_status ?? '') : null;
        $beforeSnapshot = $turno !== null ? TurnoLifecycleService::scheduleSnapshot($turno) : null;

        if ($turno === null) {
            $turno = new Turno();
            $turno->appointment_source_system = $sourceSystem;
            $turno->external_appointment_id = $dto->externalId;
            $turno->usuario_alta = 'fhir-inbound';
            $turno->fecha_alta = date('Y-m-d H:i:s');
            $turno->confirmado = 'NO';
            $turno->referenciado = 'NO';
        }

        $tx = Turno::getDb()->beginTransaction();
        try {
            $this->applyDtoToTurno($turno, $dto, $scheduleId, $trust, $idPes);

            if (!$turno->save(false)) {
                throw new \RuntimeException('No se pudo guardar turno espejo FHIR.');
            }

            $this->eventEmitter->emit(
                $turno,
                $dto,
                $isNew,
                $beforeEstado,
                $beforeFhirStatus,
                $beforeSnapshot
            );
            $this->syncSlotClaimForInbound($turno, $beforeEstado, $isNew);
            $tx->commit();
        } catch (\Throwable $e) {
            if ($tx->isActive) {
                $tx->rollBack();
            }
            throw $e;
        }

        if ($beforeEstado !== Turno::ESTADO_CANCELADO
            && (string) $turno->estado === Turno::ESTADO_CANCELADO
        ) {
            try {
                (new TurnoAdvanceOfferAgent())->onTurnoCancelled($turno);
            } catch (\Throwable $e) {
                Yii::warning('Advance offer FHIR: ' . $e->getMessage(), 'turno-advance');
            }
        }

        return [
            'action' => $isNew ? 'created' : 'updated',
            'id_turnos' => (int) $turno->id_turnos,
            'trust' => $trust,
        ];
    }

    private function syncSlotClaimForInbound(Turno $turno, ?string $beforeEstado, bool $isNew): void
    {
        $idTurno = (int) $turno->id_turnos;
        if ((string) $turno->estado === Turno::ESTADO_CANCELADO
            || (string) $turno->estado === Turno::ESTADO_ATENDIDO
            || (string) $turno->estado === Turno::ESTADO_SIN_ATENDER
        ) {
            TurnoSlotClaimService::releaseForTurno($idTurno);
            return;
        }
        if ((string) $turno->estado !== Turno::ESTADO_PENDIENTE
            && (string) $turno->estado !== Turno::ESTADO_EN_RESOLUCION
        ) {
            return;
        }
        $idPes = (int) ($turno->id_profesional_efector_servicio ?? 0);
        $fecha = trim((string) ($turno->fecha ?? ''));
        $hora = substr(TurnoResolucion::normalizarHora((string) ($turno->hora ?? '')), 0, 5);
        if ($idPes <= 0 || $fecha === '' || $hora === '') {
            return;
        }
        if ($isNew || $beforeEstado === Turno::ESTADO_CANCELADO) {
            TurnoSlotClaimService::tryClaim($idPes, $fecha, $hora, $idTurno);
            return;
        }
        TurnoSlotClaimService::moveClaim($idTurno, $idPes, $fecha, $hora);
    }

    private function applyDtoToTurno(
        Turno $turno,
        FhirAppointmentInboundDto $dto,
        string $scheduleId,
        ?string $trust,
        ?int $idPes
    ): void {
        $mapped = FhirAppointmentStatusMapper::mapToTurnoEstado($dto->fhirStatus);
        $turno->estado = $mapped['estado'];
        $turno->fhir_status = $mapped['fhir_status'];
        $turno->external_schedule_id = $scheduleId !== '' ? $scheduleId : null;
        $turno->pes_resolution_trust = $trust;
        $turno->usuario_mod = 'fhir-inbound';
        $turno->fecha_mod = date('Y-m-d H:i:s');

        if ($dto->startAt !== null && $dto->startAt !== '') {
            $ts = strtotime($dto->startAt);
            if ($ts !== false) {
                $turno->fecha = date('Y-m-d', $ts);
                $turno->hora = date('H:i', $ts);
            }
        }

        if ($dto->idPersona !== null && $dto->idPersona > 0) {
            $turno->id_persona = $dto->idPersona;
        }

        if ($idPes !== null && $idPes > 0 && in_array($trust, [
            FhirSchedulePesResolver::TRUST_VERIFIED,
            FhirSchedulePesResolver::TRUST_PROVISIONAL,
        ], true)) {
            $turno->id_profesional_efector_servicio = $idPes;
            $pes = ProfesionalEfectorServicio::findOne($idPes);
            if ($pes !== null) {
                $turno->id_efector = (int) $pes->id_efector;
                $turno->id_servicio = (int) $pes->id_servicio;
                $turno->id_servicio_asignado = (int) $pes->id_servicio;
            }
        } else {
            $turno->id_profesional_efector_servicio = null;
        }
    }

    /**
     * @param array<string, mixed> $appointment
     */
    private function resolveScheduleIdFromSlots(array $appointment, FhirSchedulingInboundConnector $connector): string
    {
        foreach ($appointment['slot'] ?? [] as $slotRef) {
            $ref = is_array($slotRef) ? (string) ($slotRef['reference'] ?? '') : '';
            if (!preg_match('#Slot/(.+)$#', $ref, $m)) {
                continue;
            }
            try {
                $slotResource = $connector->readResource('Slot', $m[1]);
                $scheduleRef = (string) ($slotResource['schedule']['reference'] ?? '');
                if (preg_match('#Schedule/(.+)$#', $scheduleRef, $sm)) {
                    return $sm[1];
                }
            } catch (\Throwable $e) {
                Yii::warning('Slot ' . $m[1] . ': ' . $e->getMessage(), 'fhir-scheduling-inbound');
            }
        }

        return FhirBundleHelper::extractScheduleIdFromAppointment($appointment);
    }
}
