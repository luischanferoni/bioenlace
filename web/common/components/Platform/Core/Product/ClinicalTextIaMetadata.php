<?php

namespace common\components\Platform\Core\Product;

use Symfony\Component\Yaml\Yaml;
use Yii;

/**
 * Metadata de prompts/heurísticas IA para texto clínico ({@see ProductMetadataPaths::clinicalTextIaFile()}).
 */
final class ClinicalTextIaMetadata
{
    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    public static function buildSnomedContextPrompt(string $texto, string $categoria): string
    {
        $section = self::loadConfig()['snomed_context'] ?? [];
        if (!is_array($section)) {
            $section = [];
        }

        $categories = $section['categories'] ?? [];
        $contexto = is_array($categories) && isset($categories[$categoria]) && is_string($categories[$categoria])
            ? $categories[$categoria]
            : (string) ($section['default_category_label'] ?? 'conceptos médicos');

        $template = (string) ($section['prompt_template'] ?? '');
        if ($template === '') {
            return $texto;
        }

        return str_replace(
            ['{texto}', '{contexto}'],
            [$texto, $contexto],
            $template
        );
    }

    /**
     * @return list<string>
     */
    public static function llmConfidenceContextTerms(): array
    {
        $section = self::loadConfig()['llm_correction_confidence'] ?? [];
        if (!is_array($section)) {
            return [];
        }

        $terms = [];
        foreach ($section['context_terms'] ?? [] as $term) {
            if (is_string($term) && trim($term) !== '') {
                $terms[] = trim($term);
            }
        }

        return $terms;
    }

    public static function llmConfidenceContextTermBoost(): float
    {
        $section = self::loadConfig()['llm_correction_confidence'] ?? [];
        if (!is_array($section)) {
            return 0.0;
        }

        $boost = $section['context_term_boost'] ?? 0.0;

        return is_numeric($boost) ? (float) $boost : 0.0;
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

        self::$config = [];

        $path = ProductMetadataPaths::clinicalTextIaFile();
        if (!is_file($path)) {
            return self::$config;
        }

        try {
            $data = Yaml::parseFile($path);
        } catch (\Throwable $e) {
            Yii::warning('ClinicalTextIaMetadata: YAML inválido: ' . $e->getMessage(), __METHOD__);

            return self::$config;
        }

        if (is_array($data)) {
            self::$config = $data;
        }

        return self::$config;
    }
}
