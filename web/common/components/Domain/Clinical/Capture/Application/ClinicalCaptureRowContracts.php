<?php

namespace common\components\Domain\Clinical\Capture\Application;

use common\components\Domain\Clinical\Capture\Domain\Policy\EncounterCaptureCompletenessValidator;
use common\components\Domain\Clinical\Capture\Domain\Port\ClinicalCaptureRowContractRegistry;
use common\components\Domain\Clinical\Capture\Domain\Port\DerivacionRowSupportPort;
use common\components\Domain\Clinical\Capture\Infrastructure\PedidoAtencion\YiiDerivacionRowSupportAdapter;

/**
 * Wiring Application → Domain contratos + Infrastructure adapters.
 */
final class ClinicalCaptureRowContracts
{
    public static function registry(): ClinicalCaptureRowContractRegistry
    {
        return new CompositeClinicalCaptureRowContractRegistry();
    }

    public static function derivacionSupport(): DerivacionRowSupportPort
    {
        return new YiiDerivacionRowSupportAdapter();
    }

    /**
     * @param array<string, mixed> $extraidos
     * @param list<array<string, mixed>> $categorias
     * @return array<string, mixed>
     */
    public static function refineDerivaciones(array $extraidos, array $categorias): array
    {
        return \common\components\Domain\Clinical\Capture\Domain\RowContract\DerivacionRowContract::refineDatosExtraidos(
            $extraidos,
            $categorias,
            self::derivacionSupport()
        );
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
