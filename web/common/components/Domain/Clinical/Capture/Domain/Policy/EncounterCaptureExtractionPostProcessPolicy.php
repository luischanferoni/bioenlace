<?php

namespace common\components\Domain\Clinical\Capture\Domain\Policy;

/**
 * Política de post-proceso de extracción clínica (capa Domain).
 *
 * Defaults y semántica aquí. Overrides operativos: Application llama
 * {@see configure()} con knobs leídos de metadata Platform (no se lee YAML aquí).
 */
final class EncounterCaptureExtractionPostProcessPolicy
{
    public const REASON_MODEL = 'EncounterReason';

    /** @var list<string> */
    public const DIAGNOSIS_MODELS = ['DiagnosticoConsulta'];

    /**
     * Léxico clínico por defecto (PCRE `/…/iu`).
     *
     * @var array<string, string>
     */
    public const DEFAULT_LEXICON = [
        'narrative_framing' => '/\b(refiere|vengo\s+por|consulta\s+por|desde\s+hace|presenta|paciente\s+con|me\s+siento|hace\s+\d+|desde\s+ayer|desde\s+hoy)\b/iu',
        'subjective_complaint' => '/\b(problema|dolor|duele|sintoma|síntoma|malestar|enfermo|fiebre|tos|nausea|náusea|vomito|vómito|mareo|hinchazon|hinchazón|presion|presión|chichon|chichón|golpe|hematoma|moreton|moretón|bulto|hinchado|inflamado|herida|sangra|sangrado|cefalea|astenia|disnea|prurito|cansancio|diarrea)\b/iu',
    ];

    /** @var list<string> */
    public const DEFAULT_BACKFILL_SPLIT_BEFORE = [
        '/\bdiagn[oó]stico\s*:/iu',
        '/\bindico\b/iu',
        '/\bindicaci[oó]n\b/iu',
        '/\breposo\b/iu',
        '/\bcontrol\b/iu',
    ];

    /** @var array<string, mixed> */
    private static array $postProcessOverrides = [];

    /** @var array<string, mixed> */
    private static array $lexiconOverrides = [];

    /** @var array<string, mixed>|null */
    private static ?array $filterCache = null;

    /** @var array<string, mixed>|null */
    private static ?array $backfillCache = null;

    /** @var array<string, mixed>|null */
    private static ?array $relocateCache = null;

    /** @var array<string, string>|null */
    private static ?array $lexiconCache = null;

    /**
     * Inyecta overrides operativos (típicamente desde metadata Platform vía Application).
     *
     * @param array<string, mixed> $postProcess sección `post_process` cruda
     * @param array<string, mixed> $lexicon overrides de léxico (clave → patrón)
     */
    public static function configure(array $postProcess = [], array $lexicon = []): void
    {
        self::$postProcessOverrides = $postProcess;
        self::$lexiconOverrides = $lexicon;
        self::resetCacheForTests();
    }

    public static function resetCacheForTests(): void
    {
        self::$filterCache = null;
        self::$backfillCache = null;
        self::$relocateCache = null;
        self::$lexiconCache = null;
    }

    /**
     * Limpia overrides y caches (tests / reinicio de metadata).
     */
    public static function resetAllForTests(): void
    {
        self::$postProcessOverrides = [];
        self::$lexiconOverrides = [];
        self::resetCacheForTests();
    }

    /**
     * Normaliza patrones PCRE. Rechaza el inline inválido `(?iu)` (en PCRE `u` no es opción inline; usar `/…/iu`).
     */
    public static function normalizePregPattern(?string $pattern): ?string
    {
        if ($pattern === null) {
            return null;
        }
        $pattern = trim($pattern);
        if ($pattern === '') {
            return null;
        }

        if ($pattern[0] === '/') {
            return $pattern;
        }

        if (preg_match('/^\(\?([a-zA-Z]+)\)(.*)$/s', $pattern, $m) === 1) {
            $flags = strtolower(str_replace('u', '', $m[1]));
            if (strpos($flags, 'i') === false) {
                $flags .= 'i';
            }
            $body = $m[2];

            return '/' . str_replace('/', '\\/', $body) . '/' . $flags . 'u';
        }

        return '/' . str_replace('/', '\\/', $pattern) . '/iu';
    }

    /**
     * @return array<string, mixed>
     */
    public static function filterConfig(): array
    {
        if (self::$filterCache !== null) {
            return self::$filterCache;
        }

        $defaults = [
            'enabled' => true,
            'strict_category_models' => [self::REASON_MODEL],
            'terminology_guard_category_models' => [],
            'retain_if_lexicon_keys' => ['narrative_framing', 'subjective_complaint'],
            'validate_terminology' => false,
            'snowstorm_fallback' => false,
        ];
        $yaml = self::$postProcessOverrides;
        $filter = is_array($yaml['filter_non_clinical_extractions'] ?? null)
            ? $yaml['filter_non_clinical_extractions']
            : [];

        return self::$filterCache = array_merge($defaults, $filter);
    }

    /**
     * @return array<string, mixed>
     */
    public static function backfillConfig(): array
    {
        if (self::$backfillCache !== null) {
            return self::$backfillCache;
        }

        $defaults = [
            'enabled' => true,
            'require_lexicon_key' => 'subjective_complaint',
            'max_chars' => 140,
            'split_before_patterns' => self::DEFAULT_BACKFILL_SPLIT_BEFORE,
        ];
        $yaml = self::$postProcessOverrides;
        $backfill = is_array($yaml['backfill_empty_motivos'] ?? null)
            ? $yaml['backfill_empty_motivos']
            : [];

        return self::$backfillCache = array_merge($defaults, $backfill);
    }

    /**
     * @return array<string, mixed>
     */
    public static function relocateConfig(): array
    {
        if (self::$relocateCache !== null) {
            return self::$relocateCache;
        }

        $yaml = self::$postProcessOverrides;
        $defaults = [
            'enabled' => false,
            'reason_model' => (string) ($yaml['reason_model'] ?? self::REASON_MODEL),
            'diagnosis_models' => $yaml['diagnosis_models'] ?? self::DIAGNOSIS_MODELS,
            'max_words' => 5,
        ];
        if (!is_array($defaults['diagnosis_models']) || $defaults['diagnosis_models'] === []) {
            $defaults['diagnosis_models'] = self::DIAGNOSIS_MODELS;
        }
        $relocate = is_array($yaml['relocate_isolated_terms'] ?? null)
            ? $yaml['relocate_isolated_terms']
            : [];

        return self::$relocateCache = array_merge($defaults, $relocate);
    }

    public static function reasonModel(): string
    {
        $fromYaml = trim((string) (self::$postProcessOverrides['reason_model'] ?? ''));

        return $fromYaml !== '' ? $fromYaml : self::REASON_MODEL;
    }

    /**
     * @return array<string, string>
     */
    public static function lexiconPatterns(): array
    {
        if (self::$lexiconCache !== null) {
            return self::$lexiconCache;
        }

        $out = self::DEFAULT_LEXICON;
        foreach (self::$lexiconOverrides as $key => $pattern) {
            if (!is_string($key) || $key === '' || !is_string($pattern) || trim($pattern) === '') {
                continue;
            }
            $out[$key] = trim($pattern);
        }

        return self::$lexiconCache = $out;
    }

    public static function clinicalLexiconPattern(string $key): ?string
    {
        $patterns = self::lexiconPatterns();
        $pattern = $patterns[$key] ?? null;

        return is_string($pattern) && trim($pattern) !== '' ? trim($pattern) : null;
    }

    public static function textMatchesClinicalLexiconPattern(string $text, string $key): bool
    {
        $pattern = self::normalizePregPattern(self::clinicalLexiconPattern($key));
        if ($pattern === null || trim($text) === '') {
            return false;
        }

        return @preg_match($pattern, $text) === 1;
    }
}
