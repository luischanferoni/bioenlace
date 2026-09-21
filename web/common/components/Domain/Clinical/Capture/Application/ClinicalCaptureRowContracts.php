<?php

namespace common\components\Domain\Clinical\Capture\Application;

use common\components\Domain\Clinical\Capture\Domain\Policy\EncounterCaptureCompletenessValidator;
use common\components\Domain\Clinical\Capture\Domain\Port\ClinicalCaptureRowContractRegistry;

/**
 * Wiring Application → Domain contratos + Infrastructure fallback.
 * El Domain Policy no instancia adapters.
 */
final class ClinicalCaptureRowContracts
{
    public static function registry(): ClinicalCaptureRowContractRegistry
    {
        return new CompositeClinicalCaptureRowContractRegistry();
    }

    public static function completenessValidator(
        ?ClinicalCaptureRowContractRegistry $registry = null
    ): EncounterCaptureCompletenessValidator {
        return new EncounterCaptureCompletenessValidator($registry ?? self::registry());
    }

    public static function resolutionApplier(
        ?ClinicalCaptureRowContractRegistry $registry = null
    ): ClinicalCaptureResolutionApplier {
        return new ClinicalCaptureResolutionApplier($registry ?? self::registry());
    }
}
