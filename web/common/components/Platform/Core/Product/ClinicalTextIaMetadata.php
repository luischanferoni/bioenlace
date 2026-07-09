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
    public static function buildEncounterAutomaticCodingPrompt(
        string $clinicalText,
        string $patientContext = '',
        string $diagnosisHints = '',
        ?int $maxDiagnosticos = null
    ): string {
        $section = self::loadConfig()['encounter_automatic_coding'] ?? [];
        if (!is_array($section)) {
            $section = [];
        }

        $template = (string) ($section['prompt_template'] ?? '');
        if ($template === '') {
            return $clinicalText;
        }

        $max = $maxDiagnosticos ?? (int) ($section['max_diagnosticos'] ?? 8);
        if ($max < 1) {
            $max = 8;
        }

        $hintsBlock = trim($diagnosisHints) !== ''
            ? "Términos diagnósticos ya extraídos (sin código aún):\n" . trim($diagnosisHints)
            : '';

        return str_replace(
            ['{clinical_text}', '{patient_context}', '{diagnosis_hints}', '{max_diagnosticos}'],
            [trim($clinicalText), trim($patientContext), $hintsBlock, (string) $max],
            $template
        );
    }

    public static function encounterAutomaticCodingMaxDiagnosticos(): int
    {
        $section = self::loadConfig()['encounter_automatic_coding'] ?? [];
        if (!is_array($section)) {
            return 8;
        }

        $max = (int) ($section['max_diagnosticos'] ?? 8);

        return $max > 0 ? $max : 8;
    }

    /**
     * @param list<array<string, mixed>> $categorias
     */
    public static function buildEncounterCaptureExtractionPrompt(
        string $clinicalText,
        array $categorias,
        string $patientContext = ''
    ): string {
        $section = self::loadConfig()['encounter_capture_extraction'] ?? [];
        if (!is_array($section)) {
            $section = [];
        }

        $template = (string) ($section['prompt_template'] ?? '');
        if ($template === '') {
            return $clinicalText;
        }

        $rulesTemplate = (string) ($section['classification_rules_template'] ?? '');
        if ($rulesTemplate === '') {
            $rulesTemplate = (string) ($section['classification_rules'] ?? '');
        }
        $classificationRules = trim(str_replace(
            '{category_semantics}',
            self::buildEncounterCaptureCategorySemantics($categorias, $section),
            $rulesTemplate
        ));
        $patientBlock = trim($patientContext) !== '' ? trim($patientContext) : '';

        return str_replace(
            ['{classification_rules}', '{categories_block}', '{patient_context}', '{clinical_text}'],
            [
                $classificationRules,
                self::buildEncounterCaptureCategoriesBlock($categorias, $section),
                $patientBlock,
                trim($clinicalText),
            ],
            $template
        );
    }

    /**
     * @param list<array<string, mixed>> $categorias
     * @param array<string, mixed> $section
     */
    public static function buildEncounterCaptureCategorySemantics(array $categorias, array $section): string
    {
        $hints = $section['category_hints'] ?? [];
        if (!is_array($hints)) {
            $hints = [];
        }

        $lines = [];
        foreach ($categorias as $categoria) {
            if (!is_array($categoria)) {
                continue;
            }
            $titulo = trim((string) ($categoria['titulo'] ?? ''));
            if ($titulo === '' || $titulo === 'Error') {
                continue;
            }
            $modelo = (string) ($categoria['modelo'] ?? '');
            $hint = isset($hints[$modelo]) && is_string($hints[$modelo]) ? trim($hints[$modelo]) : '';
            if ($hint === '') {
                continue;
            }
            $lines[] = '- "' . $titulo . '": ' . $hint;
        }

        return implode("\n", $lines);
    }

    /**
     * @param list<array<string, mixed>> $categorias
     * @param array<string, mixed> $section
     */
    public static function buildEncounterCaptureCategoriesBlock(array $categorias, array $section): string
    {
        $hints = $section['category_hints'] ?? [];
        if (!is_array($hints)) {
            $hints = [];
        }

        $lines = [];
        foreach ($categorias as $categoria) {
            if (!is_array($categoria)) {
                continue;
            }
            $titulo = trim((string) ($categoria['titulo'] ?? ''));
            if ($titulo === '' || $titulo === 'Error') {
                continue;
            }

            $modelo = (string) ($categoria['modelo'] ?? '');
            $hint = is_array($hints) && isset($hints[$modelo]) && is_string($hints[$modelo])
                ? trim($hints[$modelo])
                : '';

            $campos = $categoria['campos_requeridos'] ?? [];
            $subdatos = '';
            if (is_array($campos) && $campos !== []) {
                $subdatos = ' (subdatos: ' . implode(', ', $campos) . ')';
            }

            $line = '- "' . $titulo . '"';
            if ($hint !== '') {
                $line .= ': ' . $hint;
            }
            $line .= $subdatos;
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<string, mixed>
     */
    public static function encounterCaptureRelocateConfig(): array
    {
        $postProcess = self::encounterCapturePostProcessConfig();
        $relocate = $postProcess['relocate_isolated_terms'] ?? [];
        if (!is_array($relocate)) {
            $relocate = [];
        }

        $defaults = [
            'enabled' => false,
            'motivo_model' => (string) ($postProcess['motivo_model'] ?? 'ConsultaMotivos'),
            'diagnosis_models' => $postProcess['diagnosis_models'] ?? ['DiagnosticoConsulta'],
            'max_words' => 5,
        ];

        return array_merge($defaults, $relocate);
    }

    /**
     * @return array<string, mixed>
     */
    public static function encounterCaptureFilterConfig(): array
    {
        $postProcess = self::encounterCapturePostProcessConfig();
        $filter = $postProcess['filter_non_clinical_extractions'] ?? [];
        if (!is_array($filter)) {
            $filter = [];
        }

        $defaults = [
            'enabled' => true,
            'category_models' => ['ConsultaMotivos', 'DiagnosticoConsulta'],
            'retain_if_lexicon_keys' => ['narrative_framing', 'subjective_complaint'],
            'validate_terminology' => true,
        ];

        return array_merge($defaults, $filter);
    }

    public static function clinicalLexiconPattern(string $key): ?string
    {
        $lexicon = self::loadConfig()['clinical_lexicon'] ?? [];
        if (!is_array($lexicon)) {
            return null;
        }

        $pattern = $lexicon[$key] ?? null;

        return is_string($pattern) && trim($pattern) !== '' ? trim($pattern) : null;
    }

    public static function textMatchesClinicalLexiconPattern(string $text, string $key): bool
    {
        $pattern = self::clinicalLexiconPattern($key);
        if ($pattern === null || trim($text) === '') {
            return false;
        }

        $result = @preg_match($pattern, $text);

        return $result === 1;
    }

    /**
     * @return array<string, mixed>
     */
    private static function encounterCapturePostProcessConfig(): array
    {
        $section = self::loadConfig()['encounter_capture_extraction'] ?? [];
        if (!is_array($section)) {
            return [];
        }

        $postProcess = $section['post_process'] ?? [];

        return is_array($postProcess) ? $postProcess : [];
    }

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
