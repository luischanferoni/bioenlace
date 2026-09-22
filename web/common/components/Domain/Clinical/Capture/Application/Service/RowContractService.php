<?php

namespace common\components\Domain\Clinical\Capture\Application\Service;

use common\components\Domain\Clinical\Capture\Domain\Policy\CaptureCompletenessPolicy;
use common\components\Domain\Clinical\Capture\Domain\Port\RowContractRegistry;
use common\components\Domain\Clinical\Capture\Domain\Port\DerivacionRowSupportPort;
use common\components\Domain\Clinical\Capture\Infrastructure\CareRequest\YiiDerivacionRowSupportAdapter;

/**
 * Wiring Application → Domain contratos + Infrastructure adapters.
 */
final class RowContractService
{
    public static function registry(): RowContractRegistry
    {
        return new CompositeRowContractRegistry();
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
        ?RowContractRegistry $registry = null
    ): CaptureCompletenessPolicy {
        return new CaptureCompletenessPolicy($registry ?? self::registry());
    }

    public static function resolutionApplier(
        ?RowContractRegistry $registry = null
    ): ResolutionApplier {
        return new ResolutionApplier($registry ?? self::registry());
    }
}
