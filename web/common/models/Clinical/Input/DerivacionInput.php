<?php

namespace common\models\Clinical\Input;

use common\components\Domain\Clinical\Access\CodingSystems;
use common\components\Domain\Clinical\Access\PedidoAtencion;
use common\components\Domain\Clinical\Access\PedidoAtencionActoCoderInterface;
use common\components\Domain\Clinical\Access\PedidoAtencionActoCodingService;
use common\components\Domain\Clinical\Access\PedidoAtencionService;
use common\models\ConsultaDerivaciones;
use common\models\Servicio;
use yii\base\Model;

/**
 * Contrato de entrada de una derivación/interconsulta (extracción IA → revisión → ServiceRequest referral).
 *
 * Completitud: PedidoAtencion (línea × acto). El efector destino suele ser el del encounter.
 * Canales alimentan este DTO; coding de Acto display es dominio ({@see PedidoAtencionActoCodingService}).
 */
final class DerivacionInput extends Model
{
    public const FIELD_SERVICIO = 'Servicio';
    public const FIELD_ID_SERVICIO = 'id_servicio';
    public const FIELD_ID_EFECTOR = 'id_efector';
    public const FIELD_INDICACIONES = 'Indicaciones';
    public const FIELD_ACTO_CODE = 'Acto code';
    public const FIELD_ACTO_SYSTEM = 'Acto system';
    public const FIELD_ACTO_DISPLAY = 'Acto';
    public const FIELD_MODO = 'Modo';

    /** @var string|null texto libre del servicio (p. ej. "clínico") */
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
    public $modo = PedidoAtencion::MODO_INTERCONSULTA;

    /** @var list<array{code: string, system: string, display: string}> */
    private array $actoCodingCandidates = [];

    private static ?PedidoAtencionActoCoderInterface $actoCoderOverride = null;

    /**
     * @return list<string>
     */
    public static function promptFieldNames(): array
    {
        return [self::FIELD_SERVICIO, self::FIELD_ACTO_DISPLAY, self::FIELD_MODO];
    }

    public static function setActoCoderForTests(?PedidoAtencionActoCoderInterface $coder): void
    {
        self::$actoCoderOverride = $coder;
    }

    /**
     * @param array<string, mixed>|string $row
     */
    public static function fromExtractedRow($row): self
    {
        $model = new self();
        if (is_string($row)) {
            $model->servicio = trim($row) !== '' ? trim($row) : null;
            $model->normalize();
            $model->enrichActoCoding();

            return $model;
        }
        if (!is_array($row)) {
            return $model;
        }

        $model->servicio = self::firstNonEmptyString($row, [
            self::FIELD_SERVICIO,
            'servicio',
            'service',
            'texto',
            'termino',
        ]);
        $model->idServicio = self::firstPositiveInt($row, [
            self::FIELD_ID_SERVICIO,
            'target_service_id',
            'idServicio',
        ]);
        $model->idEfector = self::firstPositiveInt($row, [
            self::FIELD_ID_EFECTOR,
            'target_efector_id',
            'idEfector',
        ]);
        $model->indicaciones = self::firstNonEmptyString($row, [
            self::FIELD_INDICACIONES,
            'indicaciones',
            'note',
            'nota',
        ]);
        $model->actoCode = self::firstNonEmptyString($row, [
            self::FIELD_ACTO_CODE,
            'codigo',
            'code',
            'acto_code',
        ]);
        $model->actoSystem = self::firstNonEmptyString($row, [
            self::FIELD_ACTO_SYSTEM,
            'code_system',
            'acto_system',
        ]);
        $model->actoDisplay = self::firstNonEmptyString($row, [
            self::FIELD_ACTO_DISPLAY,
            'acto',
            'acto_display',
        ]);
        if ($model->actoDisplay === null && $model->actoCode !== null) {
            $model->actoDisplay = self::firstNonEmptyString($row, ['display', 'termino']);
        }
        if ($model->servicio === null) {
            $model->servicio = self::firstNonEmptyString($row, ['display']);
        }
        $modo = self::firstNonEmptyString($row, [
            self::FIELD_MODO,
            'modo',
            'tipo',
            'referral_kind',
            'tipo_solicitud',
        ]);
        $model->modo = self::normalizeModo($modo);
        $model->normalize();
        $model->enrichActoCoding();

        return $model;
    }

    public function rules(): array
    {
        return [
            [['servicio', 'indicaciones', 'actoCode', 'actoSystem', 'actoDisplay', 'modo'], 'string'],
            [['idServicio', 'idEfector'], 'integer', 'min' => 1],
        ];
    }

    public function toPedido(): PedidoAtencion
    {
        $this->normalize();

        return new PedidoAtencion(
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
        $this->enrichActoCoding();
        $resolved = (new PedidoAtencionService())->resolve($this->toPedido());
        $missing = [];
        foreach ($resolved['missing'] as $slot) {
            if ($slot === 'linea') {
                $missing[] = self::FIELD_SERVICIO;
            }
            if ($slot === 'acto') {
                $missing[] = self::FIELD_ACTO_DISPLAY;
            }
        }

        return $missing;
    }

    /**
     * Issues resolubles: chips de línea y/o acto (sin texto libre).
     *
     * @return list<array{id: string, field: string, options: list<array{value: mixed, label: string}>, allow_custom: bool}>
     */
    public function buildIssues(string $category, int $index): array
    {
        $this->enrichActoCoding();
        $resolved = (new PedidoAtencionService())->resolve($this->toPedido());
        $issues = [];

        if (in_array('linea', $resolved['missing'], true)) {
            $options = $resolved['candidates']['lineas'] !== []
                ? array_map(
                    static fn (array $l) => ['value' => $l['id'], 'label' => $l['label']],
                    $resolved['candidates']['lineas']
                )
                : self::optionsForServicio();
            if ($options !== []) {
                $issues[] = \common\components\Domain\Clinical\Capture\ClinicalCaptureIssueFactory::make(
                    $category,
                    $index,
                    self::FIELD_SERVICIO,
                    $options,
                    false
                );
            }
        }

        if (in_array('acto', $resolved['missing'], true)) {
            $actoCandidates = $resolved['candidates']['actos'];
            if ($actoCandidates === [] && $this->actoCodingCandidates !== []) {
                $actoCandidates = $this->actoCodingCandidates;
            }
            $options = array_map(
                static fn (array $a) => [
                    'value' => $a['system'] . '|' . $a['code'],
                    'label' => $a['display'] !== '' ? $a['display'] : $a['code'],
                ],
                $actoCandidates
            );
            if ($options !== []) {
                $issues[] = \common\components\Domain\Clinical\Capture\ClinicalCaptureIssueFactory::make(
                    $category,
                    $index,
                    self::FIELD_ACTO_DISPLAY,
                    $options,
                    false
                );
            }
        }

        return $issues;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function applyResolutionToRow(array $row, string $field, mixed $value): array
    {
        if ($field === self::FIELD_SERVICIO || $field === self::FIELD_ID_SERVICIO) {
            if (is_numeric($value)) {
                $id = (int) $value;
                $row[self::FIELD_ID_SERVICIO] = $id;
                $servicio = Servicio::findOne(['id_servicio' => $id]);
                if ($servicio !== null) {
                    $row[self::FIELD_SERVICIO] = (string) $servicio->nombre;
                }
            } else {
                $row[self::FIELD_SERVICIO] = is_string($value) ? trim($value) : (string) $value;
                $resolved = Servicio::findByName((string) $row[self::FIELD_SERVICIO]);
                if ($resolved !== null && $resolved > 0) {
                    $row[self::FIELD_ID_SERVICIO] = $resolved;
                }
            }
        }

        if ($field === self::FIELD_ACTO_DISPLAY || $field === self::FIELD_ACTO_CODE) {
            $raw = is_string($value) ? trim($value) : (string) $value;
            if (str_contains($raw, '|')) {
                [$system, $code] = explode('|', $raw, 2);
                $row[self::FIELD_ACTO_SYSTEM] = trim($system);
                $row[self::FIELD_ACTO_CODE] = trim($code);
                $row['code_system'] = trim($system);
                $row['codigo'] = trim($code);
            } elseif (is_numeric($value)) {
                // no-op: ids de acto no se usan como value de chip
            } else {
                $row[self::FIELD_ACTO_DISPLAY] = $raw;
                $row[self::FIELD_ACTO_CODE] = $raw;
                $row['codigo'] = $raw;
                if (empty($row[self::FIELD_ACTO_SYSTEM]) && empty($row['code_system'])) {
                    $row[self::FIELD_ACTO_SYSTEM] = CodingSystems::SNOMED;
                    $row['code_system'] = CodingSystems::SNOMED;
                }
            }
        }

        if ($field === self::FIELD_MODO) {
            $row[self::FIELD_MODO] = is_string($value) ? trim($value) : (string) $value;
            $row['modo'] = $row[self::FIELD_MODO];
        }

        return $row;
    }

    /**
     * @return list<array{value: mixed, label: string}>
     */
    public static function optionsForServicio(): array
    {
        $out = [];
        foreach (Servicio::getServiciosConTurnos() as $s) {
            if (!$s instanceof Servicio || !$s->esOfertaAsistencial()) {
                continue;
            }
            $id = (int) ($s->id_servicio ?? 0);
            $nombre = trim((string) ($s->nombre ?? ''));
            if ($id <= 0 || $nombre === '') {
                continue;
            }
            $out[] = ['value' => $id, 'label' => $nombre];
        }

        return $out;
    }

    /**
     * Resuelve destino + acto tras PedidoAtencionService.
     *
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
        $this->normalize();
        $this->enrichActoCoding();
        if (($this->idEfector === null || $this->idEfector <= 0) && $defaultEfectorId !== null && $defaultEfectorId > 0) {
            $this->idEfector = $defaultEfectorId;
        }

        $resolved = (new PedidoAtencionService())->resolve($this->toPedido());
        $pedido = $resolved['pedido'];

        $display = $this->servicio;
        if (($display === null || $display === '') && $pedido->hasLinea()) {
            $s = Servicio::findOne(['id_servicio' => $pedido->lineaId]);
            $display = $s !== null ? (string) $s->nombre : null;
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
        $modo = strtolower(trim($modo));
        if (in_array($modo, [PedidoAtencion::MODO_PRACTICA, PedidoAtencion::MODO_ESTUDIO], true)) {
            return ConsultaDerivaciones::PRACTICA;
        }

        return ConsultaDerivaciones::INTERCONSULTA;
    }

    private function enrichActoCoding(): void
    {
        if ($this->actoCode !== null && trim((string) $this->actoCode) !== '') {
            $this->actoCodingCandidates = [];

            return;
        }
        $display = trim((string) ($this->actoDisplay ?? ''));
        if ($display === '') {
            $this->actoCodingCandidates = [];

            return;
        }

        $coder = self::$actoCoderOverride ?? PedidoAtencionActoCodingService::defaultService();
        $result = $coder->code($display, $this->modo);
        if ($result['resolved'] !== null) {
            $this->actoCode = $result['resolved']['code'];
            $this->actoSystem = $result['resolved']['system'];
            $this->actoDisplay = $result['resolved']['display'];
            $this->actoCodingCandidates = [];

            return;
        }
        $this->actoCodingCandidates = $result['candidates'];
    }

    private function normalize(): void
    {
        if (($this->idServicio === null || $this->idServicio <= 0) && $this->servicio !== null && $this->servicio !== '') {
            $resolved = Servicio::findByName($this->servicio);
            if ($resolved !== null && $resolved > 0) {
                $this->idServicio = $resolved;
            }
        }
        if ($this->idServicio !== null && $this->idServicio <= 0) {
            $this->idServicio = null;
        }
        if ($this->idEfector !== null && $this->idEfector <= 0) {
            $this->idEfector = null;
        }
        if ($this->actoCode !== null && trim($this->actoCode) === '') {
            $this->actoCode = null;
        }
        if ($this->actoSystem !== null && trim($this->actoSystem) === '') {
            $this->actoSystem = null;
        }
        if ($this->actoCode !== null && $this->actoSystem === null) {
            $this->actoSystem = CodingSystems::SNOMED;
        }
        $this->modo = self::normalizeModo($this->modo);
    }

    private static function normalizeModo(?string $modo): string
    {
        $raw = strtolower(trim((string) $modo));
        if ($raw === '') {
            return PedidoAtencion::MODO_INTERCONSULTA;
        }
        if (in_array($raw, PedidoAtencion::modos(), true)) {
            return $raw;
        }
        if (in_array($raw, ['practica', 'práctica', ConsultaDerivaciones::PRACTICA], true)
            || str_contains($raw, 'practic')
            || str_contains($raw, 'estudio')
            || str_contains($raw, 'imaging')
        ) {
            return PedidoAtencion::MODO_PRACTICA;
        }
        if (str_contains($raw, 'consult') || $raw === ConsultaDerivaciones::INTERCONSULTA) {
            return PedidoAtencion::MODO_INTERCONSULTA;
        }

        return PedidoAtencion::MODO_INTERCONSULTA;
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string> $keys
     */
    private static function firstNonEmptyString(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $row)) {
                continue;
            }
            $v = trim((string) $row[$key]);
            if ($v !== '') {
                return $v;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string> $keys
     */
    private static function firstPositiveInt(array $row, array $keys): ?int
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $row)) {
                continue;
            }
            if (is_numeric($row[$key]) && (int) $row[$key] > 0) {
                return (int) $row[$key];
            }
        }

        return null;
    }
}
