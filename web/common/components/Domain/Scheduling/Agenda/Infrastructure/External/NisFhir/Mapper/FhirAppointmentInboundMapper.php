<?php

namespace common\components\Domain\Scheduling\Agenda\Infrastructure\External\NisFhir\Mapper;

use common\components\Domain\Scheduling\Agenda\Infrastructure\External\NisFhir\Mapper\FhirAppointmentInbound;
use common\components\Domain\Scheduling\Agenda\Infrastructure\External\NisFhir\Mapper\FhirBundleMapper;
use common\components\Domain\Person\Identity\Application\Service\PersonCuilService;

final class FhirAppointmentInboundMapper
{
    public function map(array $appointment, string $sourceSystem, ?string $scheduleId = null): FhirAppointmentInbound
    {
        $externalId = FhirBundleMapper::resourceId($appointment);
        $status = strtolower(trim((string) ($appointment['status'] ?? 'unknown')));
        $start = (string) ($appointment['start'] ?? '');
        $end = (string) ($appointment['end'] ?? '');

        $scheduleId = $scheduleId ?? FhirBundleMapper::extractScheduleIdFromAppointment($appointment);

        $patientCuil = '';
        $patientDni = '';
        $idPersona = null;

        foreach ($appointment['participant'] ?? [] as $participant) {
            if (!is_array($participant)) {
                continue;
            }
            $types = $participant['type'] ?? [];
            $isPatient = false;
            foreach ($types as $type) {
                foreach ($type['coding'] ?? [] as $coding) {
                    if (in_array(strtolower((string) ($coding['code'] ?? '')), ['patient', 'pat'], true)) {
                        $isPatient = true;
                        break 2;
                    }
                }
            }
            if (!$isPatient && ($participant['actor']['display'] ?? '') === '') {
                // Sin tipo explícito: primer participante con Patient en referencia
                $ref = (string) ($participant['actor']['reference'] ?? '');
                $isPatient = str_starts_with($ref, 'Patient/');
            }
            if (!$isPatient) {
                continue;
            }

            $patientResource = null;
            if (isset($participant['actor']['resource']) && is_array($participant['actor']['resource'])) {
                $patientResource = $participant['actor']['resource'];
            }
            if ($patientResource !== null) {
                $patientCuil = FhirBundleMapper::identifierValue($patientResource, FhirBundleMapper::SYSTEM_CUIL);
                $patientDni = FhirBundleMapper::identifierValue($patientResource, FhirBundleMapper::SYSTEM_DNI);
            }
            break;
        }

        if ($patientCuil !== '') {
            $persona = PersonCuilService::findUniquePersonaByCuil($patientCuil);
            if ($persona !== null) {
                $idPersona = (int) $persona->id_persona;
            }
        }

        $meta = is_array($appointment['meta'] ?? null) ? $appointment['meta'] : [];
        $versionId = trim((string) ($meta['versionId'] ?? ''));
        $lastUpdated = trim((string) ($meta['lastUpdated'] ?? ''));

        return new FhirAppointmentInbound(
            $externalId,
            $sourceSystem,
            $status,
            $start !== '' ? $start : null,
            $end !== '' ? $end : null,
            $scheduleId,
            $idPersona,
            $patientCuil,
            $patientDni,
            $versionId,
            $lastUpdated !== '' ? $lastUpdated : null,
        );
    }
}
