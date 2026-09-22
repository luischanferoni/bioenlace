<?php

namespace common\models\Clinical\Input;

use common\components\Domain\Clinical\Capture\Domain\RowContract\DerivacionRowContract;
use common\components\Domain\Clinical\Capture\Domain\Port\DerivacionRowSupportPort;
use common\components\Domain\Clinical\Capture\Infrastructure\CareRequest\YiiDerivacionRowSupportAdapter;
use common\components\Domain\Clinical\CareRequest\Domain\CodingSystems;
use common\components\Domain\Clinical\CareRequest\Domain\Model\CareRequest;
use common\components\Domain\Clinical\CareRequest\Domain\CareRequestActCoderInterface;
use common\components\Domain\Clinical\CareRequest\Application\UseCase\ResolveCareRequest;
use common\models\Clinical\ConsultaDerivaciones;
use yii\base\Model;

/**
 * Contrato de entrada de una derivación/interconsulta.
 * Completitud/resoluciones: {@see DerivacionRowContract}.
 */
final class DerivacionInput extends Model
{
    public const FIELD_SERVICIO = DerivacionRowContract::FIELD_SERVICIO;
    public const FIELD_ID_SERVICIO = DerivacionRowContract::FIELD_ID_SERVICIO;
    public const FIELD_ID_EFECTOR = DerivacionRowContract::FIELD_ID_EFECTOR;
    public const FIELD_INDICACIONES = DerivacionRowContract::FIELD_INDICACIONES;
    public const FIELD_ACTO_CODE = DerivacionRowContract::FIELD_ACTO_CODE;
    public const FIELD_ACTO_SYSTEM = DerivacionRowContract::FIELD_ACTO_SYSTEM;
    public const FIELD_ACTO_DISPLAY = DerivacionRowContract::FIELD_ACTO_DISPLAY;
    public const FIELD_MODO = DerivacionRowContract::FIELD_MODO;

    /** @var string|null */
    public $servicio;

    /** @var int|null */
    public $idServicio;

    /** @var int|null */
    public $idEfector;

    /** @var string|null */
    public $indicaciones;

    /** @var string|null */
    public $actoCode;

    /** @var string|null */
    public $actoSystem;

    /** @var string|null */
    public $actoDisplay;

    /** @var string */
    public $modo = CareRequest::MODO_INTERCONSULTA;

    /** @var list<array{code: string, system: string, display: string}> */
    private array $actoCodingCandidates = [];

    private static ?CareRequestActCoderInterface $actoCoderOverride = null;

    private static ?DerivacionRowSupportPort $supportOverride = null;

    /**
     * @return list<string>
     */
    public static function promptFieldNames(): array
    {
        return [self::FIELD_SERVICIO, self::FIELD_ACTO_DISPLAY, self::FIELD_MODO];
    }

    public static function setActoCoderForTests(?CareRequestActCoderInterface $coder): void
    {
        self::$actoCoderOverride = $coder;
    }

    public static function setSupportForTests(?DerivacionRowSupportPort $support): void
    {
        self::$supportOverride = $support;
    }

    private static function support(): DerivacionRowSupportPort
    {
        if (self::$supportOverride !== null) {
            return self::$supportOverride;
        }
        if (self::$actoCoderOverride !== null) {
            return new YiiDerivacionRowSupportAdapter(null, self::$actoCoderOverride);
        }

        return new YiiDerivacionRowSupportAdapter();
    }

    /**
     * @param array<string, mixed>|string $row
     */
    public static function fromExtractedRow($row): self
    {
        $p = DerivacionRowContract::parse($row, self::support());
        $model = new self();
        $model->servicio = $p['servicio'];
        $model->idServicio = $p['idServicio'];
        $model->idEfector = $p['idEfector'];
        $model->indicaciones = $p['indicaciones'];
        $model->actoCode = $p['actoCode'];
        $model->actoSystem = $p['actoSystem'];
        $model->actoDisplay = $p['actoDisplay'];
        $model->modo = $p['modo'];
        $model->actoCodingCandidates = $p['actoCodingCandidates'];

        return $model;
    }

    public function rules(): array
    {
        return [
            [['servicio', 'indicaciones', 'actoCode', 'actoSystem', 'actoDisplay', 'modo'], 'string'],
            [['idServicio', 'idEfector'], 'integer', 'min' => 1],
        ];
    }

    public function toPedido(): CareRequest
    {
        return new CareRequest(
            $this->idServicio,
            $this->actoCode,
            $this->actoSystem ?? ($this->actoCode !== null ? CodingSystems::SNOMED : null),
            $this->modo,
            $this->indicaciones,
            $this->idEfector,
            $this->actoDisplay
        );
    }

    /**
     * @return list<string>
     */
    public function missingFieldsForCompleteness(): array
    {
        return DerivacionRowContract::assess(
            $this->toExtractedRow(),
            'Derivaciones',
            0,
            self::support()
        )->missingFields();
    }

    /**
     * @return list<array{id: string, field: string, options: list<array{value: mixed, label: string}>, allow_custom: bool}>
     */
    public function buildIssues(string $category, int $index): array
    {
        return DerivacionRowContract::assess(
            $this->toExtractedRow(),
            $category,
            $index,
            self::support()
        )->issues();
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function applyResolutionToRow(array $row, string $field, mixed $value): array
    {
        return DerivacionRowContract::applyResolution($row, $field, $value, self::support());
    }

    /**
     * @return list<array{value: mixed, label: string}>
     */
    public static function optionsForServicio(): array
    {
        return self::support()->servicioOptions();
    }

    /**
     * @return array{
     *   id_servicio: int|null,
     *   id_efector: int|null,
     *   display: string|null,
     *   note: string|null,
     *   code: string|null,
     *   code_system: string|null,
     *   acto_display: string|null,
     *   referral_kind: string|null,
     *   complete: bool
     * }
     */
    public function resolveTargets(?int $defaultEfectorId): array
    {
        if (($this->idEfector === null || $this->idEfector <= 0) && $defaultEfectorId !== null && $defaultEfectorId > 0) {
            $this->idEfector = $defaultEfectorId;
        }

        $resolved = (new ResolveCareRequest())->resolve($this->toPedido());
        $pedido = $resolved['pedido'];

        $display = $this->servicio;
        if (($display === null || $display === '') && $pedido->hasLinea()) {
            $display = self::support()->resolveLineaNameById((int) $pedido->lineaId);
        }

        return [
            'id_servicio' => $pedido->hasLinea() ? $pedido->lineaId : null,
            'id_efector' => $this->idEfector !== null && $this->idEfector > 0 ? $this->idEfector : null,
            'display' => $display !== null && $display !== '' ? $display : ($pedido->actoDisplay ?? null),
            'note' => $this->indicaciones !== null && $this->indicaciones !== '' ? $this->indicaciones : null,
            'code' => $pedido->hasActo() ? $pedido->actoCode : null,
            'code_system' => $pedido->hasActo() ? $pedido->actoSystem : null,
            'acto_display' => $pedido->actoDisplay,
            'referral_kind' => self::referralKindForModo($pedido->modo),
            'complete' => $resolved['complete'],
        ];
    }

    public static function referralKindForModo(string $modo): string
    {
        $kind = DerivacionRowContract::referralKindForModo($modo);

        return $kind === DerivacionRowContract::REFERRAL_PRACTICA
            ? ConsultaDerivaciones::PRACTICA
            : ConsultaDerivaciones::INTERCONSULTA;
    }

    /**
     * @param array<string, mixed>|string $row
     * @return array<string, mixed>|null
     */
    public static function hydrateExtractedRowOrNull($row): ?array
    {
        return DerivacionRowContract::hydrateExtractedRowOrNull($row, self::support());
    }

    /**
     * @param array<string, mixed> $extraidos
     * @param list<array<string, mixed>> $categorias
     * @return array<string, mixed>
     */
    public static function refineDatosExtraidos(array $extraidos, array $categorias): array
    {
        return DerivacionRowContract::refineDatosExtraidos($extraidos, $categorias, self::support());
    }

    /**
     * @return array<string, mixed>
     */
    public function toExtractedRow(): array
    {
        return [
            self::FIELD_SERVICIO => $this->servicio,
            self::FIELD_ID_SERVICIO => $this->idServicio,
            self::FIELD_ID_EFECTOR => $this->idEfector,
            self::FIELD_INDICACIONES => $this->indicaciones,
            self::FIELD_ACTO_CODE => $this->actoCode,
            self::FIELD_ACTO_SYSTEM => $this->actoSystem,
            self::FIELD_ACTO_DISPLAY => $this->actoDisplay,
            self::FIELD_MODO => $this->modo,
        ];
    }
}
