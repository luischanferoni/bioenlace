<?php

namespace common\components\Domain\Integrations\Scheduling\Mapper;

use common\components\Domain\Integrations\Scheduling\Dto\FhirAppointmentInboundDto;
use common\components\Domain\Integrations\Scheduling\Util\FhirBundleHelper;
use common\components\Domain\Person\Service\PersonCuilService;

final class FhirAppointmentInboundMapper
{
    public function map(array $appointment, string $sourceSystem, ?string $scheduleId = null): FhirAppointmentInboundDto
    {
        $externalId = FhirBundleHelper::resourceId($appointment);
        $status = strtolower(trim((string) ($appointment['status'] ?? 'unknown')));
        $start = (string) ($appointment['start'] ?? '');
        $end = (string) ($appointment['end'] ?? '');

        $scheduleId = $scheduleId ?? FhirBundleHelper::extractScheduleIdFromAppointment($appointment);

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
                $patientCuil = FhirBundleHelper::identifierValue($patientResource, FhirBundleHelper::SYSTEM_CUIL);
                $patientDni = FhirBundleHelper::identifierValue($patientResource, FhirBundleHelper::SYSTEM_DNI);
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

        return new FhirAppointmentInboundDto(
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
