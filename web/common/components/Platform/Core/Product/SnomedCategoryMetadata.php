<?php

namespace common\components\Platform\Core\Product;

/**
 * Metadata de categorías SNOMED para codificación ({@see SnomedTerminologyMetadata}).
 */
final class SnomedCategoryMetadata
{
    public static function semanticConfidenceThreshold(): float
    {
        $section = SnomedTerminologyMetadata::config()['semantic_matching'] ?? [];
        if (!is_array($section)) {
            return 0.7;
        }

        $threshold = $section['confidence_threshold'] ?? 0.7;

        return is_numeric($threshold) ? (float) $threshold : 0.7;
    }

    public static function candidateLimit(): int
    {
        $section = SnomedTerminologyMetadata::config()['semantic_matching'] ?? [];
        if (!is_array($section)) {
            return 20;
        }

        $limit = $section['candidate_limit'] ?? 20;

        return is_numeric($limit) ? max(1, (int) $limit) : 20;
    }

    public static function eclForCategory(string $categoryKey): ?string
    {
        $categoryKey = trim($categoryKey);
        if ($categoryKey === '') {
            return null;
        }

        $categories = self::codificationSection()['categories'] ?? [];
        if (!is_array($categories) || !isset($categories[$categoryKey]) || !is_array($categories[$categoryKey])) {
            return null;
        }

        return SnomedTerminologyMetadata::resolveEcl($categories[$categoryKey]);
    }

    public static function categoryKeyForExtractionLabel(string $label): ?string
    {
        $label = trim($label);
        if ($label === '') {
            return null;
        }

        $map = self::codificationSection()['extraction_labels'] ?? [];
        if (!is_array($map) || !isset($map[$label])) {
            return null;
        }

        $key = trim((string) $map[$label]);

        return $key !== '' && self::eclForCategory($key) !== null ? $key : null;
    }

    /**
     * @return list<string>
     */
    public static function categoryKeys(): array
    {
        $categories = self::codificationSection()['categories'] ?? [];
        if (!is_array($categories)) {
            return [];
        }

        $keys = [];
        foreach (array_keys($categories) as $key) {
            if (is_string($key) && trim($key) !== '' && self::eclForCategory($key) !== null) {
                $keys[] = trim($key);
            }
        }

        return $keys;
    }

    public static function resetCacheForTests(): void
    {
        SnomedTerminologyMetadata::resetCacheForTests();
    }

    /**
     * @return array<string, mixed>
     */
    private static function codificationSection(): array
    {
        $section = SnomedTerminologyMetadata::config()['codification'] ?? [];

        return is_array($section) ? $section : [];
    }
}
