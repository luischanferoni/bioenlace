<?php

namespace common\components\Domain\Clinical\Access;

/**
 * URIs de terminología admitidos para línea/acto (sin code system local).
 */
final class CodingSystems
{
    public const SNOMED = 'http://snomed.info/sct';
    public const LOINC = 'http://loinc.org';
    public const FHIR_SERVICE_TYPE = 'http://terminology.hl7.org/CodeSystem/service-type';
    public const FHIR_SERVICE_CATEGORY = 'http://terminology.hl7.org/CodeSystem/service-category';

    /**
     * @return list<string>
     */
    public static function defaults(): array
    {
        return [
            self::SNOMED,
            self::LOINC,
            self::FHIR_SERVICE_TYPE,
            self::FHIR_SERVICE_CATEGORY,
        ];
    }

    public static function isAllowed(string $system, ?array $allowed = null): bool
    {
        $system = trim($system);
        if ($system === '') {
            return false;
        }
        $list = $allowed ?? self::defaults();

        return in_array($system, $list, true);
    }
}
