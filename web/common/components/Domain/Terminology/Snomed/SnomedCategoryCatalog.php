<?php

namespace common\components\Domain\Terminology\Snomed;

use common\components\Platform\Core\Product\SnomedCategoryMetadata;

/**
 * Catálogo de categorías SNOMED del producto (ECL, mapeo desde extracción IA).
 */
final class SnomedCategoryCatalog
{
    public static function semanticConfidenceThreshold(): float
    {
        return SnomedCategoryMetadata::semanticConfidenceThreshold();
    }

    public static function candidateLimit(): int
    {
        return SnomedCategoryMetadata::candidateLimit();
    }

    public static function resolveCategoryKey(string $extractionLabel): ?string
    {
        return SnomedCategoryMetadata::categoryKeyForExtractionLabel($extractionLabel);
    }

    public static function eclForCategory(string $categoryKey): ?string
    {
        return SnomedCategoryMetadata::eclForCategory($categoryKey);
    }
}
