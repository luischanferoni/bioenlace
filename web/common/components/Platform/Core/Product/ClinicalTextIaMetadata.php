<?php

namespace common\components\Platform\Core\Product;

use common\components\Platform\Ai\Prompt\PromptRegistry;
use Symfony\Component\Yaml\Yaml;
use Yii;
use yii\base\InvalidConfigException;

/**
 * Metadata de prompts/heurísticas IA para texto clínico ({@see ProductMetadataPaths::clinicalTextIaFiles()}).
 * La config humana se fusiona desde varios YAML (uno por responsabilidad); la variante veterinaria se
 * resuelve por clave de registro (`veterinary.capture_extraction`).
 */
final class ClinicalTextIaMetadata
{
    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    /** @var array<string, array<string, mixed>> */
    private static array $vetSection = [];

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

        $max = $maxDiagnosticos ?? self::encounterAutomaticCodingMaxDiagnosticos();
        if ($max < 1) {
            $max = self::encounterAutomaticCodingMaxDiagnosticos();
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
        $section = self::loadConfig()['encounter_automatic_coding'] ?? null;
        if (!is_array($section)
            || !array_key_exists('max_diagnosticos', $section)
            || !is_numeric($section['max_diagnosticos'])
            || (int) $section['max_diagnosticos'] < 1
        ) {
            throw new InvalidConfigException(
                'La metadata clínica no declara encounter_automatic_coding.max_diagnosticos.'
            );
        }

        return (int) $section['max_diagnosticos'];
    }

    /**
     * @param list<array<string, mixed>> $categorias
     * @param array<string, list<string>> $ambiguousAbbreviations abreviatura => expansiones posibles
     * @param string $ambito `humano` | `veterinario` (ver Servicio::AMBITO_*)
     */
    public static function buildEncounterCaptureExtractionPrompt(
        string $clinicalText,
        array $categorias,
        string $patientContext = '',
        array $ambiguousAbbreviations = [],
        string $ambito = 'humano'
    ): string {
        $section = self::captureExtractionSection($ambito);

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
        $tipos = self::resolveTiposPromptFromCategorias($categorias);

        return str_replace(
            [
                '{classification_rules}',
                '{categories_block}',
                '{structured_examples_block}',
                '{frecuencia_tipos}',
                '{duracion_tipos}',
                '{patient_context}',
                '{abbreviations_block}',
                '{clinical_text}',
            ],
            [
                $classificationRules,
                self::buildEncounterCaptureCategoriesBlock($categorias, $section),
                self::buildEncounterCaptureStructuredExamplesBlock($categorias),
                implode(', ', $tipos['frecuencia']),
                implode(', ', $tipos['duracion']),
                $patientBlock,
                self::buildAmbiguousAbbreviationsBlock($ambiguousAbbreviations, $section),
                trim($clinicalText),
            ],
            $template
        );
    }

    /**
     * @param array<string, list<string>> $ambiguousAbbreviations
     * @param array<string, mixed> $section
     */
    private static function buildAmbiguousAbbreviationsBlock(array $ambiguousAbbreviations, array $section): string
    {
        $header = trim((string) ($section['abbreviations_header'] ?? ''));
        if ($header === '' || $ambiguousAbbreviations === []) {
            return '';
        }

        $lines = [$header];
        foreach ($ambiguousAbbreviations as $abbr => $expansions) {
            $options = array_values(array_filter(array_map('trim', $expansions), static fn (string $e): bool => $e !== ''));
            if ($options !== []) {
                $lines[] = '- ' . $abbr . ': ' . implode(' | ', $options);
            }
        }

        return count($lines) > 1 ? implode("\n", $lines) : '';
    }

    /**
     * @return array<string, mixed>
     */
    private static function captureExtractionSection(string $ambito): array
    {
        if ($ambito === 'veterinario') {
            $key = 'veterinary.capture_extraction';
            if (!isset(self::$vetSection[$key])) {
                self::$vetSection[$key] = self::parseYamlFile(PromptRegistry::path($key))['encounter_capture_extraction'] ?? [];
            }
            $section = self::$vetSection[$key];
        } else {
            $section = self::loadConfig()['encounter_capture_extraction'] ?? [];
        }

        return is_array($section) ? $section : [];
    }

    /**
     * Si algún modelo de categoría declara tiposPromptExtraccion(), se inyectan en el prompt.
     *
     * @param list<array<string, mixed>> $categorias
     * @return array{frecuencia: list<string>, duracion: list<string>}
     */
    private static function resolveTiposPromptFromCategorias(array $categorias): array
    {
        foreach ($categorias as $categoria) {
            if (!is_array($categoria)) {
                continue;
            }
            $modelo = trim((string) ($categoria['modelo'] ?? ''));
            if ($modelo === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $modelo)) {
                continue;
            }
            $class = '\\common\\models\\' . $modelo;
            if (!class_exists($class) || !method_exists($class, 'tiposPromptExtraccion')) {
                continue;
            }
            $tipos = $class::tiposPromptExtraccion();
            if (!is_array($tipos)) {
                continue;
            }
            $frecuencia = $tipos['frecuencia'] ?? [];
            $duracion = $tipos['duracion'] ?? [];

            return [
                'frecuencia' => is_array($frecuencia) ? array_values(array_map('strval', $frecuencia)) : [],
                'duracion' => is_array($duracion) ? array_values(array_map('strval', $duracion)) : [],
            ];
        }

        return ['frecuencia' => [], 'duracion' => []];
    }

    /**
     * @param list<array<string, mixed>> $categorias
     */
    public static function buildEncounterCaptureStructuredExamplesBlock(array $categorias): string
    {
        $lines = [];
        foreach ($categorias as $categoria) {
            if (!is_array($categoria)) {
                continue;
            }
            $titulo = trim((string) ($categoria['titulo'] ?? ''));
            if ($titulo === '' || $titulo === 'Error') {
                continue;
            }
            $campos = $categoria['campos_requeridos'] ?? [];
            if (!is_array($campos) || $campos === []) {
                continue;
            }
            $ejemplo = [];
            foreach ($campos as $campo) {
                if (!is_string($campo) || $campo === '') {
                    continue;
                }
                $ejemplo[$campo] = '…';
            }
            if ($ejemplo === []) {
                continue;
            }
            $lines[] = '- Ejemplo "' . $titulo . '": '
                . json_encode([$ejemplo], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $lines === [] ? '' : implode("\n", $lines);
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
            $flags = [];
            if (($categoria['requerido'] ?? false) === true) {
                $flags[] = 'requerido';
            }
            if (($categoria['sugerido'] ?? false) === true) {
                $flags[] = 'sugerido';
            }
            if ($flags !== []) {
                $line .= ' [' . implode(', ', $flags) . ']';
            }
            if ($hint !== '') {
                $line .= ': ' . $hint;
            }
            $planHints = $categoria['plan_hints'] ?? [];
            if (is_array($planHints) && $planHints !== []) {
                $line .= ' (plan de cuidado: ' . implode('; ', array_map('strval', $planHints)) . ')';
            }
            $line .= $subdatos;
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    /**
     * Overrides crudos de post_process desde YAML (sin defaults de dominio).
     *
     * @return array<string, mixed>
     */
    public static function rawEncounterCapturePostProcess(): array
    {
        $section = self::loadConfig()['encounter_capture_extraction'] ?? [];
        if (!is_array($section)) {
            return [];
        }
        $postProcess = $section['post_process'] ?? [];

        return is_array($postProcess) ? $postProcess : [];
    }

    /**
     * Overrides crudos de léxico desde YAML (sin defaults de dominio).
     *
     * @return array<string, mixed>
     */
    public static function rawClinicalLexicon(): array
    {
        $lexicon = self::loadConfig()['clinical_lexicon'] ?? [];

        return is_array($lexicon) ? $lexicon : [];
    }

    /**
     * Normaliza patrones PCRE del metadata.
     * Rechaza el inline inválido `(?iu)` (en PCRE `u` no es opción inline; usar `/…/iu`).
     */
    public static function normalizePregPattern(?string $pattern): ?string
    {
        return \common\components\Domain\Clinical\Capture\Domain\Policy\ExtractionPostProcessPolicy::normalizePregPattern($pattern);
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
        $section = self::loadConfig()['llm_correction_confidence'] ?? null;
        if (!is_array($section)
            || !array_key_exists('context_term_boost', $section)
            || !is_numeric($section['context_term_boost'])
        ) {
            throw new InvalidConfigException(
                'La metadata clínica no declara llm_correction_confidence.context_term_boost.'
            );
        }

        return (float) $section['context_term_boost'];
    }

    public static function resetCacheForTests(): void
    {
        self::$config = null;
        self::$vetSection = [];
        \common\components\Domain\Clinical\Capture\Domain\Policy\ExtractionPostProcessPolicy::resetAllForTests();
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
        foreach (ProductMetadataPaths::clinicalTextIaFiles() as $path) {
            self::$config = array_replace_recursive(self::$config, self::parseYamlFile($path));
        }

        return self::$config;
    }

    /**
     * @return array<string, mixed>
     */
    private static function parseYamlFile(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        try {
            $data = Yaml::parseFile($path);
        } catch (\Throwable $e) {
            throw new InvalidConfigException(
                'Metadata clínica inválida (' . $path . '): ' . $e->getMessage(),
                0,
                $e
            );
        }

        return is_array($data) ? $data : [];
    }

}
