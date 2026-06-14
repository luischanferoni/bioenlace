<?php

namespace common\components\Platform\Core\Product;

/**
 * Perfiles de búsqueda SNOMED ({@see SnomedTerminologyMetadata}).
 */
final class SnomedSearchProfileMetadata
{
    public static function profileKeyForClientMethod(string $methodName): ?string
    {
        $methodName = trim($methodName);
        if ($methodName === '') {
            return null;
        }

        $map = self::searchSection()['client_methods'] ?? [];
        if (!is_array($map) || !isset($map[$methodName])) {
            return null;
        }

        $key = trim((string) $map[$methodName]);

        return $key !== '' && self::eclForProfile($key) !== null ? $key : null;
    }

    public static function eclForProfile(string $profileKey): ?string
    {
        $profileKey = trim($profileKey);
        if ($profileKey === '') {
            return null;
        }

        $profiles = self::searchSection()['profiles'] ?? [];
        if (!is_array($profiles) || !isset($profiles[$profileKey]) || !is_array($profiles[$profileKey])) {
            return null;
        }

        return SnomedTerminologyMetadata::resolveEcl($profiles[$profileKey]);
    }

    public static function limitForProfile(string $profileKey): int
    {
        $profiles = self::searchSection()['profiles'] ?? [];
        if (is_array($profiles) && isset($profiles[$profileKey]) && is_array($profiles[$profileKey])) {
            $limit = $profiles[$profileKey]['limit'] ?? null;
            if (is_numeric($limit)) {
                return max(1, (int) $limit);
            }
        }

        $default = self::searchSection()['default_limit'] ?? 10;

        return is_numeric($default) ? max(1, (int) $default) : 10;
    }

    public static function returnFormatForProfile(string $profileKey): string
    {
        $profiles = self::searchSection()['profiles'] ?? [];
        if (!is_array($profiles) || !isset($profiles[$profileKey]) || !is_array($profiles[$profileKey])) {
            return 'concepts';
        }

        $format = trim((string) ($profiles[$profileKey]['return_format'] ?? 'concepts'));

        return $format !== '' ? $format : 'concepts';
    }

    public static function resetCacheForTests(): void
    {
        SnomedTerminologyMetadata::resetCacheForTests();
    }

    /**
     * @return array<string, mixed>
     */
    private static function searchSection(): array
    {
        $section = SnomedTerminologyMetadata::config()['search'] ?? [];

        return is_array($section) ? $section : [];
    }
}
