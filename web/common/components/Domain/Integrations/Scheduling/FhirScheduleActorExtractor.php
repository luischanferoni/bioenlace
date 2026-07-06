<?php

namespace common\components\Domain\Integrations\Scheduling;

use common\components\Domain\Integrations\Scheduling\Util\FhirBundleHelper;

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
        $schedules = FhirBundleHelper::collectResources($scheduleBundle, 'Schedule');
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
            $resource = FhirBundleHelper::resolveReference($scheduleBundle, $reference);
            if ($resource === null) {
                continue;
            }
            $type = (string) ($resource['resourceType'] ?? '');
            if ($type === 'Practitioner') {
                $cuil = FhirBundleHelper::identifierValue($resource, FhirBundleHelper::SYSTEM_CUIL);
                $dni = FhirBundleHelper::identifierValue($resource, FhirBundleHelper::SYSTEM_DNI);
            } elseif ($type === 'Location' || $type === 'Organization') {
                $sisa = FhirBundleHelper::extractSisaCode($resource);
            } elseif ($type === 'HealthcareService') {
                $code = FhirBundleHelper::primaryServiceCode($resource);
                $serviceSystem = $code['system'];
                $serviceCode = $code['code'];
            } elseif ($type === 'PractitionerRole') {
                if ($cuil === '' && isset($resource['practitioner']['reference'])) {
                    $pract = FhirBundleHelper::resolveReference($scheduleBundle, (string) $resource['practitioner']['reference']);
                    if ($pract !== null) {
                        $cuil = FhirBundleHelper::identifierValue($pract, FhirBundleHelper::SYSTEM_CUIL);
                        $dni = FhirBundleHelper::identifierValue($pract, FhirBundleHelper::SYSTEM_DNI);
                    }
                }
                if ($sisa === '' && isset($resource['organization']['reference'])) {
                    $org = FhirBundleHelper::resolveReference($scheduleBundle, (string) $resource['organization']['reference']);
                    if ($org !== null) {
                        $sisa = FhirBundleHelper::extractSisaCode($org);
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
