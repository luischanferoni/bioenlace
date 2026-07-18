<?php

namespace common\components\Domain\Integrations\Scheduling\Service;

use common\components\Domain\Integrations\Scheduling\Dto\FhirAppointmentInboundDto;
use common\components\Domain\Scheduling\Service\BehaviorProfile\TurnoCanonicalEventCommand;
use common\components\Domain\Scheduling\Service\BehaviorProfile\TurnoCanonicalEventService;
use common\components\Domain\Scheduling\Service\TurnoLifecycleService;
use common\models\Scheduling\Turno;
use common\models\TurnoEventoAudit;

/**
 * Emite eventos canónicos a partir de transiciones FHIR inbound.
 */
final class TurnoFhirCanonicalEventEmitter
{
    public function __construct(
        private ?TurnoCanonicalEventService $events = null,
    ) {
        $this->events = $events ?? new TurnoCanonicalEventService();
    }

    /**
     * @param array<string, mixed>|null $beforeSnapshot
     */
    public function emit(
        Turno $turno,
        FhirAppointmentInboundDto $dto,
        bool $isNew,
        ?string $beforeEstado,
        ?string $beforeFhirStatus,
        ?array $beforeSnapshot
    ): void {
        $idPersona = (int) ($turno->id_persona ?? 0);
        if ($idPersona <= 0 || (int) $turno->id_turnos <= 0) {
            return;
        }

        $occurredAt = $this->normalizeOccurredAt($dto->lastUpdated);
        $versionKey = $dto->versionId !== ''
            ? $dto->versionId
            : ($dto->lastUpdated ?: hash('sha256', $dto->fhirStatus . '|' . ($dto->startAt ?? '')));
        $baseMeta = [
            'source_system' => $dto->sourceSystem,
            'external_appointment_id' => $dto->externalId,
            'fhir_status' => $dto->fhirStatus,
            'fhir_version_id' => $dto->versionId,
        ];

        if ($isNew) {
            $this->record(
                $turno,
                TurnoEventoAudit::EVENT_APPOINTMENT_CREATED,
                $versionKey,
                $occurredAt,
                $baseMeta,
                TurnoEventoAudit::TIPO_CREATE
            );
        }

        $afterSnapshot = TurnoLifecycleService::scheduleSnapshot($turno);
        if (!$isNew && $beforeSnapshot !== null && $beforeSnapshot !== $afterSnapshot) {
            $this->record(
                $turno,
                TurnoEventoAudit::EVENT_APPOINTMENT_RESCHEDULED,
                $versionKey . ':reschedule',
                $occurredAt,
                array_merge($baseMeta, ['before' => $beforeSnapshot, 'after' => $afterSnapshot])
            );
        }

        $status = strtolower($dto->fhirStatus);
        if (in_array($status, ['arrived', 'checked-in'], true)
            && $beforeEstado !== Turno::ESTADO_EN_ATENCION
        ) {
            $this->record(
                $turno,
                TurnoEventoAudit::EVENT_ATTENTION_STARTED,
                $versionKey . ':attention',
                $occurredAt,
                $baseMeta
            );
        }
        if ($status === 'fulfilled' && $beforeEstado !== Turno::ESTADO_ATENDIDO) {
            $this->record(
                $turno,
                TurnoEventoAudit::EVENT_ATTENDED,
                $versionKey . ':attended',
                $occurredAt,
                $baseMeta
            );
        }
        if ($status === 'noshow' && $beforeEstado !== Turno::ESTADO_SIN_ATENDER) {
            $this->record(
                $turno,
                TurnoEventoAudit::EVENT_NO_SHOW_RECORDED,
                $versionKey . ':noshow',
                $occurredAt,
                $baseMeta,
                TurnoEventoAudit::TIPO_NO_SHOW
            );
        }
        if (in_array($status, ['cancelled', 'entered-in-error'], true)
            && $beforeEstado !== Turno::ESTADO_CANCELADO
        ) {
            $this->record(
                $turno,
                TurnoEventoAudit::EVENT_APPOINTMENT_CANCELLED,
                $versionKey . ':cancel',
                $occurredAt,
                array_merge($baseMeta, ['before_fhir_status' => $beforeFhirStatus]),
                TurnoEventoAudit::TIPO_CANCEL_MED
            );
        }
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function record(
        Turno $turno,
        string $eventCode,
        string $versionKey,
        ?string $occurredAt,
        array $meta,
        ?string $legacyTipo = null
    ): void {
        $this->events->record(TurnoCanonicalEventCommand::create(
            (int) $turno->id_turnos,
            (int) $turno->id_persona,
            $eventCode,
            TurnoEventoAudit::ACTOR_EXTERNO,
            'fhir:' . (string) ($meta['source_system'] ?? '') . ':'
                . (string) ($meta['external_appointment_id'] ?? '') . ':'
                . $eventCode . ':' . $versionKey,
            TurnoEventoAudit::QUALITY_NATIVE,
            null,
            'fhir',
            'fhir_inbound',
            isset($meta['fhir_status']) ? (string) $meta['fhir_status'] : null,
            $occurredAt,
            $meta,
            $legacyTipo
        ));
    }

    private function normalizeOccurredAt(?string $lastUpdated): ?string
    {
        if ($lastUpdated === null || trim($lastUpdated) === '') {
            return null;
        }
        $ts = strtotime($lastUpdated);

        return $ts !== false ? date('Y-m-d H:i:s', $ts) : null;
    }
}
