<?php

namespace common\components\Domain\Clinical\Capture\Domain\RowContract;

use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCaptureRowCompleteness;

/**
 * Domain contract for nursing vital signs.
 *
 * Los signos vitales son opcionales de a uno: una fila es válida con un solo valor.
 * `normalize()` es la fuente única de alias y de las claves que se persisten en
 * `atenciones_enfermeria.datos`.
 */
final class EnfermeriaSignosVitalesRowContract
{
    public const MODELO = 'EncounterNursingCare';

    public const FIELD_TA_SISTOLICA = 'TA sistolica';
    public const FIELD_TA_DIASTOLICA = 'TA diastolica';
    public const FIELD_FC = 'Frecuencia cardiaca';
    public const FIELD_FR = 'Frecuencia respiratoria';
    public const FIELD_SAT_O2 = 'Saturacion O2';
    public const FIELD_TEMPERATURA = 'Temperatura';
    public const FIELD_GLUCEMIA = 'Glucemia';
    public const FIELD_GLASGOW = 'Glasgow';
    public const FIELD_PESO = 'Peso';
    public const FIELD_TALLA = 'Talla';
    public const FIELD_PERIMETRO_CEFALICO = 'Perimetro cefalico';
    public const FIELD_CIRCUNFERENCIA_ABDOMINAL = 'Circunferencia abdominal';

    /** @var list<string> claves que la IA debe devolver por fila (fuente única del prompt) */
    public const PROMPT_FIELDS = [
        self::FIELD_TA_SISTOLICA,
        self::FIELD_TA_DIASTOLICA,
        self::FIELD_FC,
        self::FIELD_FR,
        self::FIELD_SAT_O2,
        self::FIELD_TEMPERATURA,
        self::FIELD_GLUCEMIA,
        self::FIELD_GLASGOW,
        self::FIELD_PESO,
        self::FIELD_TALLA,
        self::FIELD_PERIMETRO_CEFALICO,
        self::FIELD_CIRCUNFERENCIA_ABDOMINAL,
    ];

    /**
     * Antropometría: alimenta las curvas de crecimiento y la valoración nutricional.
     *
     * @var list<string>
     */
    public const ANTHROPOMETRY_FIELDS = [
        self::FIELD_PESO,
        self::FIELD_TALLA,
        self::FIELD_PERIMETRO_CEFALICO,
        self::FIELD_CIRCUNFERENCIA_ABDOMINAL,
    ];

    public const CANON_SISTOLICA = 'sistolica';
    public const CANON_DIASTOLICA = 'diastolica';
    public const CANON_FC = 'fc';
    public const CANON_FR = 'fr';
    public const CANON_SAT_O2 = 'sat_o2';
    public const CANON_TEMPERATURA = 'temperatura';
    public const CANON_GLUCEMIA = 'glucemia_capilar';
    public const CANON_GLASGOW = 'glasgow';
    public const CANON_PESO = 'peso';
    public const CANON_TALLA = 'talla';
    public const CANON_PERIMETRO_CEFALICO = 'perimetro_cefalico';
    public const CANON_CIRCUNFERENCIA_ABDOMINAL = 'circunferencia_abdominal';
    public const CANON_TENSION = 'TensionArterial1';

    /** @var array<string, string> alias normalizado (minúscula, sin tildes) → clave canónica */
    private const ALIASES = [
        'ta_sistolica' => self::CANON_SISTOLICA,
        'ta sistolica' => self::CANON_SISTOLICA,
        'sistolica' => self::CANON_SISTOLICA,
        'bp_sys' => self::CANON_SISTOLICA,
        'ta_diastolica' => self::CANON_DIASTOLICA,
        'ta diastolica' => self::CANON_DIASTOLICA,
        'diastolica' => self::CANON_DIASTOLICA,
        'bp_dia' => self::CANON_DIASTOLICA,
        'frecuencia cardiaca' => self::CANON_FC,
        'frecuencia_cardiaca' => self::CANON_FC,
        'fc' => self::CANON_FC,
        'hr' => self::CANON_FC,
        'frecuencia respiratoria' => self::CANON_FR,
        'frecuencia_respiratoria' => self::CANON_FR,
        'fr' => self::CANON_FR,
        'rr' => self::CANON_FR,
        'saturacion o2' => self::CANON_SAT_O2,
        'saturacion' => self::CANON_SAT_O2,
        'sat_o2' => self::CANON_SAT_O2,
        'spo2' => self::CANON_SAT_O2,
        'temperatura' => self::CANON_TEMPERATURA,
        'temp' => self::CANON_TEMPERATURA,
        'temp_c' => self::CANON_TEMPERATURA,
        'glucemia' => self::CANON_GLUCEMIA,
        'glucemia_capilar' => self::CANON_GLUCEMIA,
        'glucose' => self::CANON_GLUCEMIA,
        'glasgow' => self::CANON_GLASGOW,
        'peso' => self::CANON_PESO,
        'talla' => self::CANON_TALLA,
        'perimetro cefalico' => self::CANON_PERIMETRO_CEFALICO,
        'perimetro_cefalico' => self::CANON_PERIMETRO_CEFALICO,
        'pc' => self::CANON_PERIMETRO_CEFALICO,
        'circunferencia abdominal' => self::CANON_CIRCUNFERENCIA_ABDOMINAL,
        'circunferencia_abdominal' => self::CANON_CIRCUNFERENCIA_ABDOMINAL,
    ];

    public static function matchesModelo(string $modelo): bool
    {
        return ExtractedRowFields::matchesModelo($modelo, self::MODELO);
    }

    /**
     * @param array<string, mixed>|string $row
     */
    public static function assess($row, string $categoryTitle = '', int $index = 0): ClinicalCaptureRowCompleteness
    {
        $datos = is_array($row) ? self::normalize($row) : [];

        $values = $datos;
        unset($values[self::CANON_TENSION]);
        $hasValue = false;
        foreach ($values as $value) {
            if (trim((string) $value) !== '') {
                $hasValue = true;
                break;
            }
        }

        return new ClinicalCaptureRowCompleteness(
            $hasValue ? [] : self::PROMPT_FIELDS,
            self::label($datos),
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
     * Normaliza las filas extraídas a las claves que se persisten en `datos`.
     *
     * Acepta una lista de filas (se fusionan) o un mapa plano, alias en español o
     * inglés, y números con coma decimal.
     *
     * @param array<mixed> $payload
     * @return array<string, mixed>
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
            if ($value === null || $value === '') {
                continue;
            }
            $alias = self::alias((string) $key);
            if ($alias === null) {
                continue;
            }
            $out[$alias] = self::normalizeValue($value);
        }

        if (isset($out[self::CANON_SISTOLICA], $out[self::CANON_DIASTOLICA])) {
            $out[self::CANON_TENSION] = [
                self::CANON_SISTOLICA => $out[self::CANON_SISTOLICA],
                self::CANON_DIASTOLICA => $out[self::CANON_DIASTOLICA],
            ];
        }

        return $out;
    }

    /**
     * @param string|int|float $value
     * @return string|int|float
     */
    private static function normalizeValue($value)
    {
        if (is_numeric($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric(str_replace(',', '.', $value))) {
            return str_replace(',', '.', $value);
        }

        return $value;
    }

    private static function alias(string $key): ?string
    {
        $normalized = strtr(mb_strtolower(trim($key), 'UTF-8'), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        ]);

        return self::ALIASES[$normalized] ?? null;
    }

    /**
     * @param array<string, mixed> $datos
     */
    private static function label(array $datos): string
    {
        if (isset($datos[self::CANON_TENSION]) && is_array($datos[self::CANON_TENSION])) {
            $ta = $datos[self::CANON_TENSION];

            return 'TA ' . ($ta[self::CANON_SISTOLICA] ?? '') . '/' . ($ta[self::CANON_DIASTOLICA] ?? '');
        }
        foreach (self::PROMPT_FIELDS as $field) {
            $alias = self::alias($field);
            if ($alias !== null && isset($datos[$alias]) && trim((string) $datos[$alias]) !== '') {
                return $field . ' ' . $datos[$alias];
            }
        }

        return 'signos vitales';
    }
}
