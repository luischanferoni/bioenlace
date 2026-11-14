<?php

namespace common\components\Domain\Clinical\Capture\Domain\RowContract;

use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCaptureRowCompleteness;

/**
 * Domain contract for a nutrition assessment and its plan.
 *
 * El IMC no se pide: se deriva de peso y talla en {@see normalize()}.
 */
final class NutricionEvaluacionRowContract implements ClinicalPanelContract
{
    public const MODELO = 'EncounterNutritionAssessment';
    public const PANEL_KIND = 'nutrition';
    public const OBSERVATION_CATEGORY = 'nutrition';

    public const FIELD_PESO = 'Peso';
    public const FIELD_TALLA = 'Talla';
    public const FIELD_CINTURA = 'Circunferencia de cintura';
    public const FIELD_GRASA = 'Porcentaje de grasa';
    public const FIELD_OBJETIVO = 'Objetivo';
    public const FIELD_PLAN = 'Plan alimentario';
    public const FIELD_ADHERENCIA = 'Adherencia';

    /** Campo derivado: no se pide, se calcula. */
    public const FIELD_IMC = 'IMC';

    /** @var list<string> claves que la IA debe devolver (fuente única del prompt) */
    public const PROMPT_FIELDS = [
        self::FIELD_PESO,
        self::FIELD_TALLA,
        self::FIELD_CINTURA,
        self::FIELD_GRASA,
        self::FIELD_OBJETIVO,
        self::FIELD_PLAN,
        self::FIELD_ADHERENCIA,
    ];

    public const SYSTEM_LOCAL = 'bioenlace-nutrition';

    private const STORAGE = [
        self::FIELD_PESO => ['code' => 'peso', 'system' => self::SYSTEM_LOCAL, 'type' => 'numeric', 'unit' => 'kg'],
        self::FIELD_TALLA => ['code' => 'talla', 'system' => self::SYSTEM_LOCAL, 'type' => 'numeric', 'unit' => 'cm'],
        self::FIELD_IMC => ['code' => 'imc', 'system' => self::SYSTEM_LOCAL, 'type' => 'numeric', 'unit' => 'kg/m2'],
        self::FIELD_CINTURA => ['code' => 'circunferencia_cintura', 'system' => self::SYSTEM_LOCAL, 'type' => 'numeric', 'unit' => 'cm'],
        self::FIELD_GRASA => ['code' => 'porcentaje_grasa', 'system' => self::SYSTEM_LOCAL, 'type' => 'numeric', 'unit' => '%'],
        self::FIELD_OBJETIVO => ['code' => 'objetivo', 'system' => self::SYSTEM_LOCAL, 'type' => 'text', 'unit' => null],
        self::FIELD_PLAN => ['code' => 'plan_alimentario', 'system' => self::SYSTEM_LOCAL, 'type' => 'text', 'unit' => null],
        self::FIELD_ADHERENCIA => ['code' => 'adherencia', 'system' => self::SYSTEM_LOCAL, 'type' => 'text', 'unit' => null],
    ];

    private const ALIASES = [
        self::FIELD_PESO => ['peso'],
        self::FIELD_TALLA => ['talla', 'altura'],
        self::FIELD_CINTURA => ['circunferencia de cintura', 'cintura', 'perimetro abdominal'],
        self::FIELD_GRASA => ['porcentaje de grasa', 'grasa', 'masa grasa'],
        self::FIELD_OBJETIVO => ['objetivo', 'objetivo terapeutico'],
        self::FIELD_PLAN => ['plan alimentario', 'plan', 'dieta', 'indicaciones dieteticas'],
        self::FIELD_ADHERENCIA => ['adherencia', 'adherencia al plan'],
    ];

    public static function panelKind(): string
    {
        return self::PANEL_KIND;
    }

    public static function observationCategory(): string
    {
        return self::OBSERVATION_CATEGORY;
    }

    /**
     * @return list<string>
     */
    public static function promptFields(): array
    {
        return self::PROMPT_FIELDS;
    }

    /**
     * @return array{code: string, system: string, type: string, unit: ?string}|null
     */
    public static function storageFor(string $field): ?array
    {
        return self::STORAGE[$field] ?? null;
    }

    public static function matchesModelo(string $modelo): bool
    {
        return ExtractedRowFields::matchesModelo($modelo, self::MODELO);
    }

    /**
     * @param array<string, mixed>|string $row
     */
    public static function assess($row, string $categoryTitle = '', int $index = 0): ClinicalCaptureRowCompleteness
    {
        $values = is_array($row) ? self::normalize($row) : [];

        return new ClinicalCaptureRowCompleteness(
            $values === [] ? self::PROMPT_FIELDS : [],
            self::label($values),
            []
        );
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function applyResolution(array $row, string $field, mixed $value): array
    {
        $row[$field] = $value;

        return $row;
    }

    /**
     * @param array<mixed> $payload
     * @return array<string, string>
     */
    public static function normalize(array $payload): array
    {
        if (isset($payload[0]) && is_array($payload[0])) {
            $merged = [];
            foreach ($payload as $row) {
                if (is_array($row)) {
                    $merged = array_merge($merged, $row);
                }
            }
            $payload = $merged;
        }

        $out = [];
        foreach ($payload as $key => $value) {
            if ($value === null || $value === '' || is_array($value)) {
                continue;
            }
            $field = self::fieldFor((string) $key);
            if ($field === null) {
                continue;
            }
            $text = trim((string) $value);
            if ($text === '') {
                continue;
            }
            $storage = self::storageFor($field);
            if ($storage !== null && $storage['type'] === 'numeric') {
                $numeric = str_replace(',', '.', $text);
                if (!is_numeric($numeric)) {
                    continue;
                }
                $text = $numeric;
            }
            $out[$field] = $text;
        }

        // El IMC nunca se toma del payload: se deriva de peso y talla en metros.
        unset($out[self::FIELD_IMC]);
        $imc = self::imc($out[self::FIELD_PESO] ?? null, $out[self::FIELD_TALLA] ?? null);
        if ($imc !== null) {
            $out[self::FIELD_IMC] = $imc;
        }

        return $out;
    }

    /**
     * IMC con un decimal, o `null` si falta alguno de los datos o la talla es inválida.
     *
     * @param mixed $peso
     * @param mixed $tallaCm
     */
    public static function imc($peso, $tallaCm): ?string
    {
        if (!is_numeric($peso) || !is_numeric($tallaCm)) {
            return null;
        }
        $tallaM = (float) $tallaCm / 100;
        if ($tallaM <= 0) {
            return null;
        }

        return number_format((float) $peso / ($tallaM * $tallaM), 1, '.', '');
    }

    public static function fieldFor(string $key): ?string
    {
        $normalized = strtr(mb_strtolower(trim($key), 'UTF-8'), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        ]);
        foreach (self::ALIASES as $field => $aliases) {
            if (in_array($normalized, $aliases, true)) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @param array<string, string> $values
     */
    private static function label(array $values): string
    {
        if (isset($values[self::FIELD_IMC])) {
            return 'IMC ' . $values[self::FIELD_IMC];
        }
        foreach (self::PROMPT_FIELDS as $field) {
            if (isset($values[$field])) {
                return $field . ': ' . $values[$field];
            }
        }

        return 'evaluación nutricional';
    }
}
