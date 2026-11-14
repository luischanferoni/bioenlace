<?php

namespace common\components\Domain\Clinical\Capture\Domain\RowContract;

use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCaptureRowCompleteness;

/**
 * Domain contract for a kinesiology or physiatry session.
 */
final class KinesiologiaSesionRowContract implements ClinicalPanelContract
{
    public const MODELO = 'EncounterKinesiologySession';
    public const PANEL_KIND = 'kinesiology';
    public const OBSERVATION_CATEGORY = 'kinesiology';

    public const FIELD_REGION = 'Region';
    public const FIELD_LADO = 'Lado';
    public const FIELD_MOVILIDAD = 'Rango de movilidad';
    public const FIELD_FUERZA = 'Fuerza muscular';
    public const FIELD_DOLOR = 'Dolor EVA';
    public const FIELD_EJERCICIO = 'Ejercicio indicado';
    public const FIELD_SESIONES = 'Sesiones indicadas';
    public const FIELD_EVOLUCION = 'Evolucion';

    /** @var list<string> claves que la IA debe devolver (fuente única del prompt) */
    public const PROMPT_FIELDS = [
        self::FIELD_REGION,
        self::FIELD_LADO,
        self::FIELD_MOVILIDAD,
        self::FIELD_FUERZA,
        self::FIELD_DOLOR,
        self::FIELD_EJERCICIO,
        self::FIELD_SESIONES,
        self::FIELD_EVOLUCION,
    ];

    public const SYSTEM_LOCAL = 'bioenlace-kinesiology';

    public const SIDES = ['izquierdo', 'derecho', 'bilateral'];

    /** Escala de fuerza muscular (0 a 5). */
    public const STRENGTH_MIN = 0;
    public const STRENGTH_MAX = 5;

    /** Escala visual analógica del dolor (0 a 10). */
    public const PAIN_MIN = 0;
    public const PAIN_MAX = 10;

    private const STORAGE = [
        self::FIELD_REGION => ['code' => 'region', 'system' => self::SYSTEM_LOCAL, 'type' => 'text', 'unit' => null],
        self::FIELD_LADO => ['code' => 'lado', 'system' => self::SYSTEM_LOCAL, 'type' => 'text', 'unit' => null],
        self::FIELD_MOVILIDAD => ['code' => 'rango_movilidad', 'system' => self::SYSTEM_LOCAL, 'type' => 'numeric', 'unit' => '°'],
        self::FIELD_FUERZA => ['code' => 'fuerza_muscular', 'system' => self::SYSTEM_LOCAL, 'type' => 'numeric', 'unit' => '/5'],
        self::FIELD_DOLOR => ['code' => 'dolor_eva', 'system' => self::SYSTEM_LOCAL, 'type' => 'numeric', 'unit' => '/10'],
        self::FIELD_EJERCICIO => ['code' => 'ejercicio_indicado', 'system' => self::SYSTEM_LOCAL, 'type' => 'text', 'unit' => null],
        self::FIELD_SESIONES => ['code' => 'sesiones_indicadas', 'system' => self::SYSTEM_LOCAL, 'type' => 'numeric', 'unit' => null],
        self::FIELD_EVOLUCION => ['code' => 'evolucion', 'system' => self::SYSTEM_LOCAL, 'type' => 'text', 'unit' => null],
    ];

    private const ALIASES = [
        self::FIELD_REGION => ['region', 'zona', 'segmento'],
        self::FIELD_LADO => ['lado', 'lateralidad'],
        self::FIELD_MOVILIDAD => ['rango de movilidad', 'rom', 'movilidad', 'arco de movimiento'],
        self::FIELD_FUERZA => ['fuerza muscular', 'fuerza'],
        self::FIELD_DOLOR => ['dolor eva', 'eva', 'dolor'],
        self::FIELD_EJERCICIO => ['ejercicio indicado', 'ejercicio', 'plan de ejercicios'],
        self::FIELD_SESIONES => ['sesiones indicadas', 'sesiones', 'cantidad de sesiones'],
        self::FIELD_EVOLUCION => ['evolucion', 'evolucion de la sesion'],
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
                if (!self::inRange($field, (float) $numeric)) {
                    continue;
                }
                $text = $numeric;
            }
            if ($field === self::FIELD_LADO) {
                $side = mb_strtolower($text, 'UTF-8');
                if (!in_array($side, self::SIDES, true)) {
                    continue;
                }
                $text = $side;
            }
            $out[$field] = $text;
        }

        return $out;
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
     * Un valor fuera de escala se descarta; no se recorta porque falsearía el registro.
     */
    private static function inRange(string $field, float $value): bool
    {
        if ($field === self::FIELD_FUERZA) {
            return $value >= self::STRENGTH_MIN && $value <= self::STRENGTH_MAX;
        }
        if ($field === self::FIELD_DOLOR) {
            return $value >= self::PAIN_MIN && $value <= self::PAIN_MAX;
        }

        return true;
    }

    /**
     * @param array<string, string> $values
     */
    private static function label(array $values): string
    {
        $parts = [];
        foreach ([self::FIELD_REGION, self::FIELD_LADO, self::FIELD_DOLOR] as $field) {
            if (isset($values[$field])) {
                $parts[] = $field . ': ' . $values[$field];
            }
        }

        return $parts === [] ? 'sesión de kinesiología' : implode(' · ', $parts);
    }
}
