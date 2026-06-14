<?php

namespace common\components\Domain\Terminology\Snomed;

use common\components\Platform\Core\Product\SnomedSearchProfileMetadata;

/**
 * Catálogo de perfiles de búsqueda SNOMED del producto.
 */
final class SnomedSearchProfileCatalog
{
    public static function profileKeyForClientMethod(string $methodName): ?string
    {
        return SnomedSearchProfileMetadata::profileKeyForClientMethod($methodName);
    }

    public static function eclForProfile(string $profileKey): ?string
    {
        return SnomedSearchProfileMetadata::eclForProfile($profileKey);
    }

    public static function limitForProfile(string $profileKey): int
    {
        return SnomedSearchProfileMetadata::limitForProfile($profileKey);
    }

    public static function returnFormatForProfile(string $profileKey): string
    {
        return SnomedSearchProfileMetadata::returnFormatForProfile($profileKey);
    }
}
