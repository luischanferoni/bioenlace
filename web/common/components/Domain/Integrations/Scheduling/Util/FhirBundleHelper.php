<?php

namespace common\components\Domain\Integrations\Scheduling\Util;

/**
 * Utilidades sobre Bundles y recursos FHIR R4.
 */
final class FhirBundleHelper
{
    public const SYSTEM_CUIL = 'http://www.afip.gob.ar/cuil';
    public const SYSTEM_DNI = 'http://www.renaper.gob.ar/dni';

    /**
     * @param array<string, mixed> $bundleOrResource
     * @return list<array<string, mixed>>
     */
    public static function collectResources(array $bundleOrResource, ?string $resourceType = null): array
    {
        if (($bundleOrResource['resourceType'] ?? '') === 'Bundle') {
            $out = [];
            foreach ($bundleOrResource['entry'] ?? [] as $entry) {
                if (!is_array($entry['resource'] ?? null)) {
                    continue;
                }
                $r = $entry['resource'];
                if ($resourceType === null || ($r['resourceType'] ?? '') === $resourceType) {
                    $out[] = $r;
                }
            }

            return $out;
        }

        if ($resourceType === null || ($bundleOrResource['resourceType'] ?? '') === $resourceType) {
            return [$bundleOrResource];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $resource
     */
    public static function resourceId(array $resource): string
    {
        return trim((string) ($resource['id'] ?? ''));
    }

    /**
     * @param array<string, mixed> $resource
     */
    public static function identifierValue(array $resource, string $system): string
    {
        foreach ($resource['identifier'] ?? [] as $id) {
            if (!is_array($id)) {
                continue;
            }
            if (trim((string) ($id['system'] ?? '')) === $system) {
                return trim((string) ($id['value'] ?? ''));
            }
        }

        return '';
    }

    /**
     * Primer código SISA-like en Location u Organization.
     *
     * @param array<string, mixed> $resource
     */
    public static function extractSisaCode(array $resource): string
    {
        foreach ($resource['identifier'] ?? [] as $id) {
            if (!is_array($id)) {
                continue;
            }
            $system = strtolower((string) ($id['system'] ?? ''));
            $value = trim((string) ($id['value'] ?? ''));
            if ($value === '') {
                continue;
            }
            if (str_contains($system, 'sisa') || str_contains($system, 'refes')) {
                return $value;
            }
        }

        return self::identifierValue($resource, 'urn:oid:2.16.840.1.113883.4.337.2');
    }

    /**
     * @param array<string, mixed> $healthcareService
     * @return array{system: string, code: string}
     */
    public static function primaryServiceCode(array $healthcareService): array
    {
        foreach ($healthcareService['specialty'] ?? [] as $spec) {
            if (!is_array($spec)) {
                continue;
            }
            foreach ($spec['coding'] ?? [] as $coding) {
                if (!is_array($coding)) {
                    continue;
                }
                $code = trim((string) ($coding['code'] ?? ''));
                if ($code !== '') {
                    return [
                        'system' => trim((string) ($coding['system'] ?? '')),
                        'code' => $code,
                    ];
                }
            }
        }
        foreach ($healthcareService['type'] ?? [] as $type) {
            if (!is_array($type)) {
                continue;
            }
            foreach ($type['coding'] ?? [] as $coding) {
                if (!is_array($coding)) {
                    continue;
                }
                $code = trim((string) ($coding['code'] ?? ''));
                if ($code !== '') {
                    return [
                        'system' => trim((string) ($coding['system'] ?? '')),
                        'code' => $code,
                    ];
                }
            }
        }

        return ['system' => '', 'code' => ''];
    }

    /**
     * @param array<string, mixed> $bundle
     */
    public static function indexByTypeAndId(array $bundle): array
    {
        $index = [];
        foreach (self::collectResources($bundle) as $resource) {
            $type = (string) ($resource['resourceType'] ?? '');
            $id = self::resourceId($resource);
            if ($type !== '' && $id !== '') {
                $index[$type . '/' . $id] = $resource;
            }
        }

        return $index;
    }

  /**
   * @param array<string, mixed> $bundle
   */
    public static function resolveReference(array $bundle, string $reference): ?array
    {
        $ref = trim($reference);
        if ($ref === '') {
            return null;
        }
        if (str_starts_with($ref, 'urn:')) {
            return null;
        }
        if (str_contains($ref, '/')) {
            $parts = explode('/', $ref);
            $key = $parts[count($parts) - 2] . '/' . $parts[count($parts) - 1];
        } else {
            return null;
        }

        $index = self::indexByTypeAndId($bundle);

        return $index[$key] ?? null;
    }

    /**
     * @param array<string, mixed> $appointment
     */
    public static function extractScheduleIdFromAppointment(array $appointment): string
    {
        foreach ($appointment['slot'] ?? [] as $slotRef) {
            $ref = is_array($slotRef) ? (string) ($slotRef['reference'] ?? '') : '';
            if (preg_match('#Slot/[^/]+#', $ref)) {
                // Slot → Schedule requiere lectura adicional; se resuelve en servicio de sync.
            }
        }
        foreach ($appointment['participant'] ?? [] as $participant) {
            if (!is_array($participant)) {
                continue;
            }
            foreach ($participant['actor']['extension'] ?? [] as $ext) {
                if (!is_array($ext)) {
                    continue;
                }
                $valueRef = (string) ($ext['valueReference']['reference'] ?? '');
                if (str_starts_with($valueRef, 'Schedule/')) {
                    return substr($valueRef, strlen('Schedule/'));
                }
            }
        }

        return '';
    }
}
