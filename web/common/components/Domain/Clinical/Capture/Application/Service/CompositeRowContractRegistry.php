<?php

namespace common\components\Domain\Clinical\Capture\Application\Service;

use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCaptureRowCompleteness;
use common\components\Domain\Clinical\Capture\Domain\RowContract\BalanceHidricoRowContract;
use common\components\Domain\Clinical\Capture\Domain\RowContract\DerivacionRowContract;
use common\components\Domain\Clinical\Capture\Domain\RowContract\ReasonRowContract;
use common\components\Domain\Clinical\Capture\Domain\RowContract\ExtractedRowFields;
use common\components\Domain\Clinical\Capture\Domain\RowContract\IndicacionRowContract;
use common\components\Domain\Clinical\Capture\Domain\RowContract\MedicacionRowContract;
use common\components\Domain\Clinical\Capture\Domain\RowContract\OdontologiaItemRowContract;
use common\components\Domain\Clinical\Capture\Domain\RowContract\OftalmologiaEstudioRowContract;
use common\components\Domain\Clinical\Capture\Domain\RowContract\PracticaRowContract;
use common\components\Domain\Clinical\Capture\Domain\RowContract\RegimenRowContract;
use common\components\Domain\Clinical\Capture\Domain\Port\RowContractRegistry;
use common\components\Domain\Clinical\Capture\Domain\Port\DerivacionRowSupportPort;
use common\components\Domain\Clinical\Capture\Infrastructure\PedidoAtencion\YiiDerivacionRowSupportAdapter;
use common\components\Domain\Clinical\Capture\Infrastructure\Persistence\YiiModelRowContractRegistry;

/**
 * Registry compuesto: contratos Domain + fallback Yii/`*Input`.
 */
final class CompositeRowContractRegistry implements RowContractRegistry
{
    /**
     * @var array<string, class-string>
     */
    private const DOMAIN_BY_MODELO = [
        ReasonRowContract::MODELO => ReasonRowContract::class,
        PracticaRowContract::MODELO => PracticaRowContract::class,
        RegimenRowContract::MODELO => RegimenRowContract::class,
        OftalmologiaEstudioRowContract::MODELO => OftalmologiaEstudioRowContract::class,
        IndicacionRowContract::MODELO => IndicacionRowContract::class,
        MedicacionRowContract::MODELO => MedicacionRowContract::class,
        BalanceHidricoRowContract::MODELO => BalanceHidricoRowContract::class,
        DerivacionRowContract::MODELO => DerivacionRowContract::class,
        'ConsultaOdontologiaPracticas' => OdontologiaItemRowContract::class,
        'ConsultaOdontologiaDiagnosticos' => OdontologiaItemRowContract::class,
        'ConsultaOdontologiaEstados' => OdontologiaItemRowContract::class,
    ];

    private RowContractRegistry $fallback;

    private DerivacionRowSupportPort $derivacionSupport;

    public function __construct(
        ?RowContractRegistry $fallback = null,
        ?DerivacionRowSupportPort $derivacionSupport = null
    ) {
        $this->fallback = $fallback ?? new YiiModelRowContractRegistry();
        $this->derivacionSupport = $derivacionSupport ?? new YiiDerivacionRowSupportAdapter();
    }

    public function supports(string $modelo): bool
    {
        return $this->domainClass($modelo) !== null || $this->fallback->supports($modelo);
    }

    public function assess(string $modelo, $row, string $categoryTitle, int $index): ?ClinicalCaptureRowCompleteness
    {
        $class = $this->domainClass($modelo);
        if ($class === DerivacionRowContract::class) {
            return DerivacionRowContract::assess($row, $categoryTitle, $index, $this->derivacionSupport);
        }
        if ($class !== null) {
            return $class::assess($row, $categoryTitle, $index);
        }

        return $this->fallback->assess($modelo, $row, $categoryTitle, $index);
    }

    public function applyResolution(string $modelo, array $row, string $field, mixed $value): ?array
    {
        $class = $this->domainClass($modelo);
        if ($class === DerivacionRowContract::class) {
            return DerivacionRowContract::applyResolution($row, $field, $value, $this->derivacionSupport);
        }
        if ($class !== null) {
            return $class::applyResolution($row, $field, $value);
        }

        return $this->fallback->applyResolution($modelo, $row, $field, $value);
    }

    public function derivacionSupport(): DerivacionRowSupportPort
    {
        return $this->derivacionSupport;
    }

    /**
     * @return class-string|null
     */
    private function domainClass(string $modelo): ?string
    {
        $short = ExtractedRowFields::shortModelo($modelo);

        return self::DOMAIN_BY_MODELO[$short] ?? self::DOMAIN_BY_MODELO[$modelo] ?? null;
    }
}
