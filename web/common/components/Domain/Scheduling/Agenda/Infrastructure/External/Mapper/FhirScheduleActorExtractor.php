<?php

namespace common\components\Domain\Scheduling\Agenda\Infrastructure\External\Mapper;

/**
 * Extrae actores normalizados de un Schedule FHIR (+ includes en Bundle).
 */
final class FhirScheduleActorExtractor
{
    /**
     * @param array<string, mixed> $scheduleBundle Bundle con Schedule y actores incluidos
     */
    public function extractFromBundle(array $scheduleBundle): ScheduleActorSet
    {
        $schedules = FhirBundleMapper::collectResources($scheduleBundle, 'Schedule');
        $schedule = $schedules[0] ?? null;
        if ($schedule === null) {
            return new ScheduleActorSet();
        }

        $cuil = '';
        $dni = '';
        $sisa = '';
        $serviceSystem = '';
        $serviceCode = '';

        foreach ($schedule['actor'] ?? [] as $actorRef) {
            if (!is_array($actorRef)) {
                continue;
            }
            $reference = (string) ($actorRef['reference'] ?? '');
            $resource = FhirBundleMapper::resolveReference($scheduleBundle, $reference);
            if ($resource === null) {
                continue;
            }
            $type = (string) ($resource['resourceType'] ?? '');
            if ($type === 'Practitioner') {
                $cuil = FhirBundleMapper::identifierValue($resource, FhirBundleMapper::SYSTEM_CUIL);
                $dni = FhirBundleMapper::identifierValue($resource, FhirBundleMapper::SYSTEM_DNI);
            } elseif ($type === 'Location' || $type === 'Organization') {
                $sisa = FhirBundleMapper::extractSisaCode($resource);
            } elseif ($type === 'HealthcareService') {
                $code = FhirBundleMapper::primaryServiceCode($resource);
                $serviceSystem = $code['system'];
                $serviceCode = $code['code'];
            } elseif ($type === 'PractitionerRole') {
                if ($cuil === '' && isset($resource['practitioner']['reference'])) {
                    $pract = FhirBundleMapper::resolveReference($scheduleBundle, (string) $resource['practitioner']['reference']);
                    if ($pract !== null) {
                        $cuil = FhirBundleMapper::identifierValue($pract, FhirBundleMapper::SYSTEM_CUIL);
                        $dni = FhirBundleMapper::identifierValue($pract, FhirBundleMapper::SYSTEM_DNI);
                    }
                }
                if ($sisa === '' && isset($resource['organization']['reference'])) {
                    $org = FhirBundleMapper::resolveReference($scheduleBundle, (string) $resource['organization']['reference']);
                    if ($org !== null) {
                        $sisa = FhirBundleMapper::extractSisaCode($org);
                    }
                }
                foreach ($resource['specialty'] ?? [] as $spec) {
                    if (!is_array($spec)) {
                        continue;
                    }
                    foreach ($spec['coding'] ?? [] as $coding) {
                        if (!is_array($coding)) {
                            continue;
                        }
                        $c = trim((string) ($coding['code'] ?? ''));
                        if ($c !== '') {
                            $serviceSystem = trim((string) ($coding['system'] ?? ''));
                            $serviceCode = $c;
                            break 2;
                        }
                    }
                }
            }
        }

        return new ScheduleActorSet($cuil, $dni, $sisa, $serviceSystem, $serviceCode);
    }
}
