<?php

namespace common\components\Domain\Clinical\Text;

use common\components\Platform\Core\Product\ClinicalTextIaMetadata;

/**
 * Heurísticas de confianza para corrección ortográfica clínica vía LLM.
 */
final class MedicalLlmConfidenceService
{
    public static function contextBoost(string $contexto): float
    {
        $boost = ClinicalTextIaMetadata::llmConfidenceContextTermBoost();
        if ($boost <= 0.0 || $contexto === '') {
            return 0.0;
        }

        foreach (ClinicalTextIaMetadata::llmConfidenceContextTerms() as $termino) {
            if (stripos($contexto, $termino) !== false) {
                return $boost;
            }
        }

        return 0.0;
    }
}
