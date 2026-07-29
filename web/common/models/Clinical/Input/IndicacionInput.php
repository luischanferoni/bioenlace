<?php

namespace common\models\Clinical\Input;

use yii\base\Model;

/**
 * Contrato de entrada de una indicación clínica (extracción IA → revisión → ServiceRequest).
 * Integridad: plazo obligatorio solo si el tipo es follow-up.
 */
final class IndicacionInput extends Model
{
    public const TYPE_COUNSELING = 'counseling';
    public const TYPE_CONDITIONAL = 'conditional';
    public const TYPE_FOLLOW_UP = 'follow_up';

    public const FIELD_INDICACION = 'Indicacion';
    public const FIELD_TIPO = 'Tipo';
    public const FIELD_PLAZO_DIAS = 'Plazo dias';

    /** @var string|null */
    public $indicacion;

    /** @var string|null counseling|conditional|follow_up */
    public $tipo;

    /** @var int|null */
    public $plazoDias;

    /**
     * Campos del esquema de extracción IA (no todos son hard-required en completeness).
     *
     * @return list<string>
     */
    public static function promptFieldNames(): array
    {
        return [
            self::FIELD_INDICACION,
            self::FIELD_TIPO,
            self::FIELD_PLAZO_DIAS,
        ];
    }

    /**
     * @return list<string>
     */
    public static function typeValues(): array
    {
        return [
            self::TYPE_COUNSELING,
            self::TYPE_CONDITIONAL,
            self::TYPE_FOLLOW_UP,
        ];
    }

    /**
     * @param array<string, mixed>|string $row
     */
    public static function fromExtractedRow($row): self
    {
        $model = new self();
        if (is_string($row)) {
            $model->indicacion = trim($row);
            $model->inferMissingTipo();
            $model->normalizeTipo();

            return $model;
        }
        if (!is_array($row)) {
            return $model;
        }

        $model->indicacion = self::firstNonEmptyString($row, [
            self::FIELD_INDICACION,
            'indicacion',
            'termino',
            'texto',
            'display',
            'label',
        ]);
        $model->tipo = self::firstNonEmptyString($row, [
            self::FIELD_TIPO,
            'tipo',
            'type',
            'category',
            'kind',
        ]);
        $model->plazoDias = self::parsePlazoDias($row);
        $model->inferMissingTipo();
        $model->normalizeTipo();

        return $model;
    }

    public function rules(): array
    {
        return [
            [['indicacion'], 'trim'],
            [['indicacion'], 'required', 'message' => 'Falta el texto de la indicación.'],
            [['tipo'], 'required', 'message' => 'Falta el tipo de indicación.'],
            [['tipo'], 'in', 'range' => self::typeValues()],
            [['plazoDias'], 'integer', 'min' => 1],
            [
                ['plazoDias'],
                'required',
                'when' => static fn (self $m) => $m->tipo === self::TYPE_FOLLOW_UP,
                'message' => 'Falta el plazo en días para el control.',
            ],
        ];
    }

    /**
     * Nombres NL de campos faltantes (compatibles con mensajes de completeness).
     *
     * @return list<string>
     */
    public function missingFieldsForCompleteness(): array
    {
        if ($this->validate()) {
            return [];
        }
        $missing = [];
        if ($this->hasErrors('indicacion')) {
            $missing[] = self::FIELD_INDICACION;
        }
        if ($this->hasErrors('tipo')) {
            $missing[] = self::FIELD_TIPO;
        }
        if ($this->hasErrors('plazoDias')) {
            $missing[] = self::FIELD_PLAZO_DIAS;
        }

        return $missing;
    }

    /**
     * Issues resolubles para el cliente (opciones sin seleccionar).
     *
     * @return list<array{id: string, field: string, options: list<array{value: mixed, label: string}>, allow_custom: bool}>
     */
    public function buildIssues(string $category, int $index): array
    {
        $issues = [];
        foreach ($this->missingFieldsForCompleteness() as $field) {
            if ($field === self::FIELD_PLAZO_DIAS) {
                $issues[] = \common\components\Domain\Clinical\Capture\ClinicalCaptureIssueFactory::make(
                    $category,
                    $index,
                    $field,
                    [
                        ['value' => 3, 'label' => '3 días'],
                        ['value' => 7, 'label' => '7 días'],
                        ['value' => 15, 'label' => '15 días'],
                        ['value' => 30, 'label' => '30 días'],
                    ],
                    false
                );
                continue;
            }
            if ($field === self::FIELD_TIPO) {
                $issues[] = \common\components\Domain\Clinical\Capture\ClinicalCaptureIssueFactory::make(
                    $category,
                    $index,
                    $field,
                    [
                        ['value' => self::TYPE_COUNSELING, 'label' => 'Consejo / instrucción'],
                        ['value' => self::TYPE_CONDITIONAL, 'label' => 'Condicionado a síntomas'],
                        ['value' => self::TYPE_FOLLOW_UP, 'label' => 'Control programado'],
                    ],
                    false
                );
                continue;
            }
            // Texto narrativo u otros: sin input libre; editar nota y reanalizar.
        }

        return $issues;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function applyResolutionToRow(array $row, string $field, mixed $value): array
    {
        $row[$field] = $value;
        if ($field === self::FIELD_PLAZO_DIAS) {
            if (is_numeric($value)) {
                $row[$field] = (int) $value;
            } elseif (is_string($value) && preg_match('/(\d+)/', $value, $m)) {
                $row[$field] = (int) $m[1];
            }
            if (empty($row[self::FIELD_TIPO])) {
                $row[self::FIELD_TIPO] = self::TYPE_FOLLOW_UP;
            }
        }

        return $row;
    }

    public function applyResolution(string $field, mixed $value): void
    {
        if ($field === self::FIELD_INDICACION) {
            $this->indicacion = is_string($value) ? trim($value) : (string) $value;
            return;
        }
        if ($field === self::FIELD_TIPO) {
            $this->tipo = is_string($value) ? trim($value) : (string) $value;
            $this->normalizeTipo();
            return;
        }
        if ($field === self::FIELD_PLAZO_DIAS) {
            if (is_numeric($value)) {
                $this->plazoDias = (int) $value;
            } elseif (is_string($value) && preg_match('/(\d+)/', $value, $m)) {
                $this->plazoDias = (int) $m[1];
            }
            if ($this->tipo === null || $this->tipo === '') {
                $this->tipo = self::TYPE_FOLLOW_UP;
            }
        }
    }

    public function categoryForServiceRequest(): string
    {
        return $this->tipo === self::TYPE_FOLLOW_UP ? 'follow-up' : 'counseling';
    }

    /**
     * @return array<string, mixed>
     */
    public function toExtractedRow(): array
    {
        $row = [
            self::FIELD_INDICACION => (string) ($this->indicacion ?? ''),
            self::FIELD_TIPO => (string) ($this->tipo ?? self::TYPE_COUNSELING),
        ];
        if ($this->plazoDias !== null) {
            $row[self::FIELD_PLAZO_DIAS] = $this->plazoDias;
        } else {
            $row[self::FIELD_PLAZO_DIAS] = null;
        }

        return $row;
    }

    private function normalizeTipo(): void
    {
        $raw = strtolower(trim((string) ($this->tipo ?? '')));
        $raw = str_replace(['-', ' '], '_', $raw);
        $map = [
            'counseling' => self::TYPE_COUNSELING,
            'counselling' => self::TYPE_COUNSELING,
            'consejo' => self::TYPE_COUNSELING,
            'instruccion' => self::TYPE_COUNSELING,
            'instrucción' => self::TYPE_COUNSELING,
            'conditional' => self::TYPE_CONDITIONAL,
            'condicional' => self::TYPE_CONDITIONAL,
            'follow_up' => self::TYPE_FOLLOW_UP,
            'followup' => self::TYPE_FOLLOW_UP,
            'control' => self::TYPE_FOLLOW_UP,
            'reconsulta' => self::TYPE_FOLLOW_UP,
        ];
        $this->tipo = $map[$raw] ?? ($raw !== '' ? $raw : null);
    }

    /**
     * Inferencia determinística cuando la IA no manda Tipo (o manda vacío).
     */
    private function inferMissingTipo(): void
    {
        if (trim((string) ($this->tipo ?? '')) !== '') {
            if ($this->plazoDias !== null && $this->plazoDias > 0) {
                $normalized = strtolower(str_replace(['-', ' '], '_', (string) $this->tipo));
                if (in_array($normalized, ['counseling', 'counselling', 'conditional', 'condicional'], true)) {
                    // Plazo explícito manda sobre un counseling/conditional inconsistente.
                    $this->tipo = self::TYPE_FOLLOW_UP;
                }
            }

            return;
        }

        if ($this->plazoDias !== null && $this->plazoDias > 0) {
            $this->tipo = self::TYPE_FOLLOW_UP;

            return;
        }

        $text = mb_strtolower(trim((string) ($this->indicacion ?? '')), 'UTF-8');
        if ($text !== '' && preg_match('/\bsi\b.+/u', $text) === 1) {
            $this->tipo = self::TYPE_CONDITIONAL;

            return;
        }

        $this->tipo = self::TYPE_COUNSELING;
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
        $want = [];
        foreach ($keys as $key) {
            $want[self::foldKey($key)] = true;
        }
        foreach ($row as $k => $v) {
            if (!is_string($k) || !isset($want[self::foldKey($k)])) {
                continue;
            }
            $s = trim((string) $v);
            if ($s !== '') {
                return $s;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function parsePlazoDias(array $row): ?int
    {
        $candidates = [
            self::FIELD_PLAZO_DIAS,
            'plazo_dias',
            'plazoDias',
            'delay_days',
            'dias',
        ];
        foreach ($candidates as $key) {
            if (!array_key_exists($key, $row)) {
                continue;
            }
            if (preg_match('/(\d+)/', (string) $row[$key], $m) === 1) {
                $n = (int) $m[1];
                if ($n > 0) {
                    return $n;
                }
            }
        }
        foreach ($row as $k => $v) {
            if (!is_string($k)) {
                continue;
            }
            if (self::foldKey($k) !== self::foldKey(self::FIELD_PLAZO_DIAS)
                && self::foldKey($k) !== 'plazodias'
                && self::foldKey($k) !== 'delaydays') {
                continue;
            }
            if (preg_match('/(\d+)/', (string) $v, $m) === 1) {
                $n = (int) $m[1];
                if ($n > 0) {
                    return $n;
                }
            }
        }

        return null;
    }

    private static function foldKey(string $key): string
    {
        $folded = strtr(mb_strtolower(trim($key), 'UTF-8'), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);

        return preg_replace('/\s+/', '', $folded) ?? $folded;
    }
}
