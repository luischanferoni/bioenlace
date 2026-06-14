<?php

namespace common\components\Platform\Core\Product;

use Symfony\Component\Yaml\Yaml;
use Yii;

/**
 * Metadata de categorías SNOMED ({@see ProductMetadataPaths::snomedCategoriesFile()}).
 */
final class SnomedCategoryMetadata
{
    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    public static function semanticConfidenceThreshold(): float
    {
        $section = self::loadConfig()['semantic_matching'] ?? [];
        if (!is_array($section)) {
            return 0.7;
        }

        $threshold = $section['confidence_threshold'] ?? 0.7;

        return is_numeric($threshold) ? (float) $threshold : 0.7;
    }

    public static function candidateLimit(): int
    {
        $section = self::loadConfig()['semantic_matching'] ?? [];
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

        $categories = self::loadConfig()['categories'] ?? [];
        if (!is_array($categories) || !isset($categories[$categoryKey]) || !is_array($categories[$categoryKey])) {
            return null;
        }

        $ecl = trim((string) ($categories[$categoryKey]['ecl'] ?? ''));

        return $ecl !== '' ? $ecl : null;
    }

    public static function categoryKeyForExtractionLabel(string $label): ?string
    {
        $label = trim($label);
        if ($label === '') {
            return null;
        }

        $map = self::loadConfig()['extraction_labels'] ?? [];
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
        $categories = self::loadConfig()['categories'] ?? [];
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
        self::$config = null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadConfig(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        self::$config = [
            'semantic_matching' => [],
            'categories' => [],
            'extraction_labels' => [],
        ];

        $path = ProductMetadataPaths::snomedCategoriesFile();
        if (!is_file($path)) {
            return self::$config;
        }

        try {
            $data = Yaml::parseFile($path);
        } catch (\Throwable $e) {
            Yii::warning('SnomedCategoryMetadata: YAML inválido: ' . $e->getMessage(), __METHOD__);

            return self::$config;
        }

        if (!is_array($data)) {
            return self::$config;
        }

        foreach (['semantic_matching', 'categories', 'extraction_labels'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                self::$config[$key] = $data[$key];
            }
        }

        return self::$config;
    }
}
