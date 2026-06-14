<?php

namespace common\components\Domain\Terminology\Snomed;

use common\components\Platform\Core\Product\ClinicalTextIaMetadata;

/**
 * Prompts contextuales para embeddings / matching SNOMED CT.
 */
final class SnomedContextualPromptBuilder
{
    public static function build(string $texto, string $categoria): string
    {
        return ClinicalTextIaMetadata::buildSnomedContextPrompt($texto, $categoria);
    }
}
