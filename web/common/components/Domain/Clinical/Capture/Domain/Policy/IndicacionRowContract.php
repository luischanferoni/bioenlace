<?php

namespace common\components\Domain\Clinical\Capture\Domain\Policy;

use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCaptureIssueFactory;
use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCaptureRowCompleteness;

/** Contrato Domain: indicación clínica (`ConsultaIndicaciones`). */
final class IndicacionRowContract
{
    public const MODELO = 'ConsultaIndicaciones';

    public const TYPE_COUNSELING = 'counseling';
    public const TYPE_CONDITIONAL = 'conditional';
    public const TYPE_FOLLOW_UP = 'follow_up';

    public const FIELD_INDICACION = 'Indicacion';
    public const FIELD_TIPO = 'Tipo';
    public const FIELD_PLAZO_DIAS = 'Plazo dias';

    public static function matchesModelo(string $modelo): bool
    {
        return ExtractedRowFields::matchesModelo($modelo, self::MODELO);
    }

    /**
     * @return list<string>
     */
    public static function typeValues(): array
    {
        return [self::TYPE_COUNSELING, self::TYPE_CONDITIONAL, self::TYPE_FOLLOW_UP];
    }

    /**
     * @param array<string, mixed>|string $row
     * @return array{indicacion: string, tipo: string|null, plazoDias: int|null}
     */
    public static function parse($row): array
    {
        $indicacion = '';
        $tipo = null;
        $plazoDias = null;

        if (($s = ExtractedRowFields::stringRow($row)) !== null) {
            $indicacion = $s;
        } elseif (is_array($row)) {
            $indicacion = ExtractedRowFields::firstNonEmpty($row, [
                self::FIELD_INDICACION,
                'indicacion',
                'termino',
                'texto',
                'display',
                'label',
            ]) ?? '';
            $tipo = ExtractedRowFields::firstNonEmpty($row, [
                self::FIELD_TIPO,
                'tipo',
                'type',
                'category',
                'kind',
            ]);
            $plazoDias = self::parsePlazoDias($row);
        }

        [$tipo, $plazoDias] = self::inferAndNormalizeTipo($indicacion, $tipo, $plazoDias);

        return [
            'indicacion' => $indicacion,
            'tipo' => $tipo,
            'plazoDias' => $plazoDias,
        ];
    }

    /**
     * @param array<string, mixed>|string $row
     */
    public static function assess($row, string $categoryTitle = '', int $index = 0): ClinicalCaptureRowCompleteness
    {
        $p = self::parse($row);
        $missing = self::missingFields($p);
        $label = $p['indicacion'] !== '' ? $p['indicacion'] : 'ítem';
        $issues = self::buildIssues($missing, $categoryTitle, $index);

        return new ClinicalCaptureRowCompleteness($missing, $label, $issues);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function applyResolution(array $row, string $field, mixed $value): array
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

    /**
     * @param array{indicacion: string, tipo: string|null, plazoDias: int|null} $p
     * @return list<string>
     */
    public static function missingFields(array $p): array
    {
        $missing = [];
        if ($p['indicacion'] === '') {
            $missing[] = self::FIELD_INDICACION;
        }
        $tipo = (string) ($p['tipo'] ?? '');
        if ($tipo === '' || !in_array($tipo, self::typeValues(), true)) {
            $missing[] = self::FIELD_TIPO;
        }
        if ($tipo === self::TYPE_FOLLOW_UP && ($p['plazoDias'] === null || $p['plazoDias'] < 1)) {
            $missing[] = self::FIELD_PLAZO_DIAS;
        }

        return $missing;
    }

    /**
     * @param list<string> $missing
     * @return list<array{id: string, field: string, options: list<array{value: mixed, label: string}>, allow_custom: bool}>
     */
    private static function buildIssues(array $missing, string $category, int $index): array
    {
        $issues = [];
        foreach ($missing as $field) {
            if ($field === self::FIELD_PLAZO_DIAS) {
                $issues[] = ClinicalCaptureIssueFactory::make($category, $index, $field, [
                    ['value' => 3, 'label' => '3 días'],
                    ['value' => 7, 'label' => '7 días'],
                    ['value' => 15, 'label' => '15 días'],
                    ['value' => 30, 'label' => '30 días'],
                ], false);
                continue;
            }
            if ($field === self::FIELD_TIPO) {
                $issues[] = ClinicalCaptureIssueFactory::make($category, $index, $field, [
                    ['value' => self::TYPE_COUNSELING, 'label' => 'Consejo / instrucción'],
                    ['value' => self::TYPE_CONDITIONAL, 'label' => 'Condicionado a síntomas'],
                    ['value' => self::TYPE_FOLLOW_UP, 'label' => 'Control programado'],
                ], false);
            }
        }

        return $issues;
    }

    /**
     * @return array{0: string|null, 1: int|null}
     */
    private static function inferAndNormalizeTipo(string $indicacion, ?string $tipo, ?int $plazoDias): array
    {
        if ($tipo !== null && trim($tipo) !== '') {
            if ($plazoDias !== null && $plazoDias > 0) {
                $normalized = strtolower(str_replace(['-', ' '], '_', $tipo));
                if (in_array($normalized, ['counseling', 'counselling', 'conditional', 'condicional'], true)) {
                    $tipo = self::TYPE_FOLLOW_UP;
                }
            }
        } elseif ($plazoDias !== null && $plazoDias > 0) {
            $tipo = self::TYPE_FOLLOW_UP;
        } else {
            $text = mb_strtolower(trim($indicacion), 'UTF-8');
            if ($text !== '' && preg_match('/\bsi\b.+/u', $text) === 1) {
                $tipo = self::TYPE_CONDITIONAL;
            } else {
                $tipo = self::TYPE_COUNSELING;
            }
        }

        $raw = strtolower(trim((string) $tipo));
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
        $tipo = $map[$raw] ?? ($raw !== '' ? $raw : null);

        return [$tipo, $plazoDias];
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
            $fk = ExtractedRowFields::foldKey($k);
            if ($fk !== ExtractedRowFields::foldKey(self::FIELD_PLAZO_DIAS)
                && $fk !== 'plazodias'
                && $fk !== 'delaydays') {
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
}
