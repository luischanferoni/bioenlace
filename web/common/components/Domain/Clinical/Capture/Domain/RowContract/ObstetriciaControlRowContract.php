<?php

namespace common\components\Domain\Clinical\Capture\Domain\RowContract;

use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCaptureRowCompleteness;

/**
 * Domain contract for an obstetric prenatal control.
 *
 * Fuente única de las claves que pide el prompt y de cómo se guarda cada una como
 * observación: código, sistema, unidad y tipo de valor. Un campo sin código
 * institucional usa el sistema local `bioenlace-obstetrics`.
 */
final class ObstetriciaControlRowContract implements ClinicalPanelContract
{
    public const PANEL_KIND = 'obstetrics';
    public const OBSERVATION_CATEGORY = 'obstetrics';
    public const MODELO = 'EncounterObstetricControl';

    public const FIELD_FUM = 'FUM';
    public const FIELD_EDAD_GESTACIONAL = 'Edad gestacional';
    public const FIELD_GRAVIDEZ = 'Gravidez';
    public const FIELD_PARIDAD = 'Paridad';
    public const FIELD_ABORTOS = 'Abortos';
    public const FIELD_ALTURA_UTERINA = 'Altura uterina';
    public const FIELD_LATIDO_FETAL = 'Latido fetal';
    public const FIELD_PRESENTACION = 'Presentacion';
    public const FIELD_EDEMA = 'Edema';
    public const FIELD_PROTEINURIA = 'Proteinuria';
    public const FIELD_MOVIMIENTOS_FETALES = 'Movimientos fetales';

    /** @var list<string> claves que la IA debe devolver por fila (fuente única del prompt) */
    public const PROMPT_FIELDS = [
        self::FIELD_FUM,
        self::FIELD_EDAD_GESTACIONAL,
        self::FIELD_GRAVIDEZ,
        self::FIELD_PARIDAD,
        self::FIELD_ABORTOS,
        self::FIELD_ALTURA_UTERINA,
        self::FIELD_LATIDO_FETAL,
        self::FIELD_PRESENTACION,
        self::FIELD_EDEMA,
        self::FIELD_PROTEINURIA,
        self::FIELD_MOVIMIENTOS_FETALES,
    ];

    public const SYSTEM_SNOMED = 'http://snomed.info/sct';
    public const SYSTEM_LOCAL = 'bioenlace-obstetrics';

    public const KIND_NUMERIC = 'numeric';
    public const KIND_TEXT = 'text';
    public const KIND_DATE = 'date';

    /**
     * Cómo se guarda cada campo.
     *
     * `code` es el código de la observación; `unit` sólo aplica a numéricos.
     *
     * @var array<string, array{code: string, system: string, type: string, unit: ?string}>
     */
    private const STORAGE = [
        self::FIELD_FUM => [
            'code' => '21840007',
            'system' => self::SYSTEM_SNOMED,
            'type' => self::KIND_DATE,
            'unit' => null,
        ],
        self::FIELD_EDAD_GESTACIONAL => [
            'code' => '57036006',
            'system' => self::SYSTEM_SNOMED,
            'type' => self::KIND_NUMERIC,
            'unit' => 'sem',
        ],
        self::FIELD_GRAVIDEZ => [
            'code' => 'gravidez',
            'system' => self::SYSTEM_LOCAL,
            'type' => self::KIND_NUMERIC,
            'unit' => null,
        ],
        self::FIELD_PARIDAD => [
            'code' => 'paridad',
            'system' => self::SYSTEM_LOCAL,
            'type' => self::KIND_NUMERIC,
            'unit' => null,
        ],
        self::FIELD_ABORTOS => [
            'code' => 'abortos',
            'system' => self::SYSTEM_LOCAL,
            'type' => self::KIND_NUMERIC,
            'unit' => null,
        ],
        self::FIELD_ALTURA_UTERINA => [
            'code' => 'altura_uterina',
            'system' => self::SYSTEM_LOCAL,
            'type' => self::KIND_NUMERIC,
            'unit' => 'cm',
        ],
        self::FIELD_LATIDO_FETAL => [
            'code' => 'latido_fetal',
            'system' => self::SYSTEM_LOCAL,
            'type' => self::KIND_NUMERIC,
            'unit' => 'lpm',
        ],
        self::FIELD_PRESENTACION => [
            'code' => 'presentacion_fetal',
            'system' => self::SYSTEM_LOCAL,
            'type' => self::KIND_TEXT,
            'unit' => null,
        ],
        self::FIELD_EDEMA => [
            'code' => 'edema',
            'system' => self::SYSTEM_LOCAL,
            'type' => self::KIND_TEXT,
            'unit' => null,
        ],
        self::FIELD_PROTEINURIA => [
            'code' => 'proteinuria',
            'system' => self::SYSTEM_LOCAL,
            'type' => self::KIND_TEXT,
            'unit' => null,
        ],
        self::FIELD_MOVIMIENTOS_FETALES => [
            'code' => 'movimientos_fetales',
            'system' => self::SYSTEM_LOCAL,
            'type' => self::KIND_TEXT,
            'unit' => null,
        ],
    ];

    /** @var array<string, list<string>> alias por campo (minúscula, sin tildes al comparar) */
    private const ALIASES = [
        self::FIELD_FUM => ['fum', 'fecha ultima menstruacion', 'ultima menstruacion', 'fur'],
        self::FIELD_EDAD_GESTACIONAL => ['edad gestacional', 'eg', 'semanas de gestacion', 'gestacion'],
        self::FIELD_GRAVIDEZ => ['gravidez', 'gestas', 'numero de gestaciones'],
        self::FIELD_PARIDAD => ['paridad', 'partos'],
        self::FIELD_ABORTOS => ['abortos', 'aborto'],
        self::FIELD_ALTURA_UTERINA => ['altura uterina', 'au'],
        self::FIELD_LATIDO_FETAL => ['latido fetal', 'fcf', 'frecuencia cardiaca fetal', 'lcf'],
        self::FIELD_PRESENTACION => ['presentacion', 'presentacion fetal'],
        self::FIELD_EDEMA => ['edema', 'edemas'],
        self::FIELD_PROTEINURIA => ['proteinuria'],
        self::FIELD_MOVIMIENTOS_FETALES => ['movimientos fetales', 'mfg', 'mov fetal'],
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

    public static function matchesModelo(string $modelo): bool
    {
        return ExtractedRowFields::matchesModelo($modelo, self::MODELO);
    }

    /**
     * @return array{code: string, system: string, type: string, unit: ?string}|null
     */
    public static function storageFor(string $field): ?array
    {
        return self::STORAGE[$field] ?? null;
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
     * Valores normalizados con la clave del prompt como clave.
     *
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
            $text = trim(str_replace(',', '.', (string) $value));
            if ($text === '') {
                continue;
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
     * @param array<string, string> $values
     */
    private static function label(array $values): string
    {
        $parts = [];
        foreach (self::PROMPT_FIELDS as $field) {
            if (!isset($values[$field])) {
                continue;
            }
            $parts[] = $field . ' ' . $values[$field];
            if (count($parts) >= 2) {
                break;
            }
        }

        return $parts === [] ? 'control prenatal' : implode(' · ', $parts);
    }
}
