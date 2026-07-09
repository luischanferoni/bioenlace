<?php

namespace common\components\Domain\Clinical\Text;

use common\components\Platform\Core\Product\ClinicalTextIaMetadata;

/**
 * Valida si un término extraído por IA es clínicamente plausible.
 */
final class EncounterCaptureClinicalTermValidator
{
    private EncounterCaptureTerminologyLookup $terminologyLookup;

    public function __construct(?EncounterCaptureTerminologyLookup $terminologyLookup = null)
    {
        $this->terminologyLookup = $terminologyLookup ?? new EncounterCaptureTerminologyLookup();
    }

    /**
     * @param string|array<string, mixed> $item
     * @param array<string, mixed> $config
     */
    public function isPlausibleExtraction($item, string $clinicalText, array $config = []): bool
    {
        if (is_array($item) && $this->itemHasClinicalCode($item)) {
            return true;
        }

        $term = $this->itemLabel($item);
        if ($term === '') {
            return false;
        }

        if ($this->matchesRetainLexicon($term, $clinicalText, $config)) {
            return true;
        }

        if (($config['validate_terminology'] ?? true) !== false) {
            if ($this->terminologyLookup->matchesClinicalTerm($term, $config)) {
                return true;
            }

            if ($this->wordCount($term) > 1) {
                foreach ($this->significantWords($term) as $word) {
                    if ($this->terminologyLookup->matchesClinicalTerm($word, $config)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Ítem en categoría diagnóstico: se conserva si hay respaldo terminológico o contexto clínico.
     *
     * @param string|array<string, mixed> $item
     * @param array<string, mixed> $config
     */
    public function isPlausibleDiagnosisExtraction($item, string $clinicalText, array $config = []): bool
    {
        if (is_array($item) && $this->itemHasClinicalCode($item)) {
            return true;
        }

        if ($this->matchesRetainLexicon($this->itemLabel($item), $clinicalText, $config)) {
            return true;
        }

        $term = $this->itemLabel($item);
        if ($term === '') {
            return false;
        }

        if (($config['validate_terminology'] ?? true) === false) {
            return true;
        }

        if ($this->terminologyLookup->matchesClinicalTerm($term, $config)) {
            return true;
        }

        if (
            ($config['trust_ia_diagnosis_when_terminology_unavailable'] ?? true) === true
            && $this->terminologyLookup->wasTerminologyServiceUnavailable()
        ) {
            return true;
        }

        return false;
    }

    /**
     * Término aislado candidato a diagnóstico (reubicación motivo → diagnóstico).
     *
     * @param string|array<string, mixed> $item
     * @param array<string, mixed> $config
     */
    public function isPlausibleIsolatedDiagnosisCandidate($item, string $clinicalText, array $config = []): bool
    {
        $term = $this->itemLabel($item);
        if ($term === '') {
            return false;
        }

        if (ClinicalTextIaMetadata::textMatchesClinicalLexiconPattern($term, 'subjective_complaint')) {
            return false;
        }

        if (ClinicalTextIaMetadata::textMatchesClinicalLexiconPattern($clinicalText, 'narrative_framing')) {
            return false;
        }

        return ($config['validate_terminology'] ?? true) !== false
            && $this->terminologyLookup->matchesClinicalTerm($term, $config);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function matchesRetainLexicon(string $term, string $clinicalText, array $config): bool
    {
        $retainKeys = $config['retain_if_lexicon_keys'] ?? ['narrative_framing', 'subjective_complaint'];
        if (!is_array($retainKeys)) {
            return false;
        }

        foreach ($retainKeys as $key) {
            if (!is_string($key) || trim($key) === '') {
                continue;
            }
            $lexiconKey = trim($key);
            if (ClinicalTextIaMetadata::textMatchesClinicalLexiconPattern($clinicalText, $lexiconKey)) {
                return true;
            }
            if ($term !== '' && ClinicalTextIaMetadata::textMatchesClinicalLexiconPattern($term, $lexiconKey)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function itemHasClinicalCode(array $item): bool
    {
        foreach (['codigo', 'codigo_cie10', 'cie10', 'codigo_snomed', 'conceptId'] as $key) {
            $value = trim((string) ($item[$key] ?? ''));
            if ($value !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string|array<string, mixed> $item
     */
    private function itemLabel($item): string
    {
        if (is_string($item)) {
            return trim($item);
        }
        if (!is_array($item)) {
            return '';
        }
        foreach (['termino', 'descripcion', 'texto', 'nombre'] as $key) {
            $value = trim((string) ($item[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @return list<string>
     */
    private function significantWords(string $text): array
    {
        $words = preg_split('/\s+/u', mb_strtolower(trim($text)), -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($words)) {
            return [];
        }

        $out = [];
        foreach ($words as $word) {
            $word = trim($word, ".,;:!?\"'");
            if (mb_strlen($word) >= 4) {
                $out[] = $word;
            }
        }

        return $out;
    }

    private function wordCount(string $text): int
    {
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        return is_array($words) ? count($words) : 0;
    }
}
