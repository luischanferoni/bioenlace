<?php

namespace common\components\Domain\Clinical\Text;

use common\components\Platform\Core\Product\ClinicalTextIaMetadata;
use common\models\Terminology\Snomed\SnomedHallazgos;
use common\models\Terminology\Snomed\SnomedProblemas;
use yii\db\ActiveRecord;

/**
 * Valida si un término extraído por IA es clínicamente plausible (sin listas cerradas de palabras).
 */
final class EncounterCaptureClinicalTermValidator
{
    /**
     * @param string|array<string, mixed> $item
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

        $retainKeys = $config['retain_if_lexicon_keys'] ?? ['narrative_framing', 'subjective_complaint'];
        if (!is_array($retainKeys)) {
            $retainKeys = [];
        }

        foreach ($retainKeys as $key) {
            if (!is_string($key) || trim($key) === '') {
                continue;
            }
            $lexiconKey = trim($key);
            if (ClinicalTextIaMetadata::textMatchesClinicalLexiconPattern($clinicalText, $lexiconKey)) {
                return true;
            }
            if (ClinicalTextIaMetadata::textMatchesClinicalLexiconPattern($term, $lexiconKey)) {
                return true;
            }
        }

        if (($config['validate_terminology'] ?? true) !== false) {
            if ($this->matchesLocalTerminology($term)) {
                return true;
            }

            if ($this->wordCount($term) > 1) {
                foreach ($this->significantWords($term) as $word) {
                    if ($this->matchesLocalTerminology($word)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Término aislado candidato a diagnóstico (no síntoma subjetivo léxico).
     *
     * @param string|array<string, mixed> $item
     */
    public function isPlausibleIsolatedDiagnosisCandidate($item, string $clinicalText, array $config = []): bool
    {
        if (!$this->isPlausibleExtraction($item, $clinicalText, $config)) {
            return false;
        }

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
            ? $this->matchesLocalTerminology($term)
            : true;
    }

    private function matchesLocalTerminology(string $term): bool
    {
        $normalized = mb_strtolower(trim($term));
        if (mb_strlen($normalized) < 3) {
            return false;
        }

        foreach ([SnomedProblemas::class, SnomedHallazgos::class] as $modelClass) {
            if ($this->termExistsInSnomedTable($modelClass, $normalized)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param class-string<ActiveRecord> $modelClass
     */
    private function termExistsInSnomedTable(string $modelClass, string $normalizedTerm): bool
    {
        return $modelClass::find()
            ->where([
                'or',
                ['=', 'term', $normalizedTerm],
                ['like', 'term', $normalizedTerm . '%', false],
                ['like', 'term', '% ' . $normalizedTerm . '%', false],
            ])
            ->limit(1)
            ->exists();
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
