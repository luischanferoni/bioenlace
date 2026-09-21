<?php

namespace common\components\Domain\Clinical\Capture\Application;

use common\components\Domain\Clinical\Capture\Domain\Policy\EncounterCaptureCompletenessValidator;
use common\components\Domain\Clinical\Capture\Domain\Port\ClinicalCaptureRowContractRegistry;
use common\components\Domain\Clinical\Capture\Infrastructure\Persistence\YiiModelClinicalCaptureRowContractRegistry;

/**
 * Wiring Application → Infrastructure para contratos de fila de captura.
 * El Domain Policy no instancia adapters.
 */
final class ClinicalCaptureRowContracts
{
    public static function registry(): ClinicalCaptureRowContractRegistry
    {
        return new YiiModelClinicalCaptureRowContractRegistry();
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
