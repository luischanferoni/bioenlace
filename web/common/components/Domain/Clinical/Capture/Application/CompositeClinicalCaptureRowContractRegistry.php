<?php

namespace common\components\Domain\Clinical\Capture\Application;

use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCaptureRowCompleteness;
use common\components\Domain\Clinical\Capture\Domain\Policy\EncounterReasonRowContract;
use common\components\Domain\Clinical\Capture\Domain\Port\ClinicalCaptureRowContractRegistry;
use common\components\Domain\Clinical\Capture\Infrastructure\Persistence\YiiModelClinicalCaptureRowContractRegistry;

/**
 * Registry compuesto: contratos Domain (piloto EncounterReason) + fallback Yii/`*Input`.
 */
final class CompositeClinicalCaptureRowContractRegistry implements ClinicalCaptureRowContractRegistry
{
    private ClinicalCaptureRowContractRegistry $fallback;

    public function __construct(?ClinicalCaptureRowContractRegistry $fallback = null)
    {
        $this->fallback = $fallback ?? new YiiModelClinicalCaptureRowContractRegistry();
    }

    public function supports(string $modelo): bool
    {
        return EncounterReasonRowContract::matchesModelo($modelo) || $this->fallback->supports($modelo);
    }

    public function assess(string $modelo, $row, string $categoryTitle, int $index): ?ClinicalCaptureRowCompleteness
    {
        if (EncounterReasonRowContract::matchesModelo($modelo)) {
            return EncounterReasonRowContract::assess($row, $categoryTitle, $index);
        }

        return $this->fallback->assess($modelo, $row, $categoryTitle, $index);
    }

    public function applyResolution(string $modelo, array $row, string $field, mixed $value): ?array
    {
        if (EncounterReasonRowContract::matchesModelo($modelo)) {
            return EncounterReasonRowContract::applyResolution($row, $field, $value);
        }

        return $this->fallback->applyResolution($modelo, $row, $field, $value);
    }
}
