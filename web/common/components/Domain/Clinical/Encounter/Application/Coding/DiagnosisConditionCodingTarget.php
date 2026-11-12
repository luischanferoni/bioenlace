<?php

namespace common\components\Domain\Clinical\Encounter\Application\Coding;

use common\components\Domain\Clinical\Encounter\Application\UseCase\ApplyEncounterAutomaticCoding;
use common\models\Clinical\Encounter;

/**
 * Diagnósticos (CIE-10 + SNOMED) en una sola llamada IA.
 *
 * Recibe los términos extraídos en el guardado; no depende del request.
 */
final class DiagnosisConditionCodingTarget implements EncounterCodingTargetInterface
{
    public function key(): string
    {
        return 'diagnosis';
    }

    public function code(Encounter $encounter, array $context = []): int
    {
        $terms = $context['diagnosis_terms'] ?? [];
        if (!is_array($terms) || $terms === []) {
            return 0;
        }

        return ApplyEncounterAutomaticCoding::codeAndPersistForEncounterTerms($encounter, $terms);
    }
}
