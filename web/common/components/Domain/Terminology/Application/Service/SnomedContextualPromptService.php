<?php

namespace common\components\Domain\Terminology\Application\Service;

use common\components\Platform\Core\Product\ClinicalTextIaMetadata;

/**
 * Prompts contextuales para embeddings / matching SNOMED CT.
 */
final class SnomedContextualPromptService
{
    public static function build(string $texto, string $categoria): string
    {
        return ClinicalTextIaMetadata::buildSnomedContextPrompt($texto, $categoria);
    }
}
