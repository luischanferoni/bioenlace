<?php

namespace common\components\Domain\Clinical\Capture\Application\Extraction;

use common\components\Domain\Clinical\Capture\Domain\Policy\EncounterCaptureExtractionPostProcessPolicy;
use common\components\Platform\Core\Product\ClinicalTextIaMetadata;

/**
 * Inyecta knobs de metadata Platform en la policy de Domain (sin acoplar Domain a Platform).
 */
final class EncounterCapturePostProcessKnobs
{
    public static function applyFromPlatformMetadata(): void
    {
        EncounterCaptureExtractionPostProcessPolicy::configure(
            ClinicalTextIaMetadata::rawEncounterCapturePostProcess(),
            ClinicalTextIaMetadata::rawClinicalLexicon()
        );
    }
}
