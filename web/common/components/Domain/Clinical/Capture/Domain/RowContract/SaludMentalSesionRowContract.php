<?php

namespace common\components\Domain\Clinical\Capture\Domain\RowContract;

use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCaptureRowCompleteness;
use common\components\Domain\Clinical\Specialty\Domain\Catalog\MentalHealthInstrumentCatalog;

/**
 * Domain contract for a mental-health session (or a psychology session).
 *
 * Fuente única de las claves del prompt y de cómo se guarda cada una. Los puntajes de
 * instrumentos se declaran desde {@see MentalHealthInstrumentCatalog}: agregar una
 * escala no requiere tocar este contrato.
 */
final class SaludMentalSesionRowContract implements ClinicalPanelContract
{
    public const PANEL_KIND = 'mental-health';
    public const OBSERVATION_CATEGORY = 'mental-health';
    public const MODELO = 'EncounterMentalHealthSession';

    public const FIELD_MOTIVO = 'Motivo de sesion';
    public const FIELD_OBJETIVOS = 'Objetivos';
    public const FIELD_EVOLUCION = 'Evolucion';
    public const FIELD_ADHERENCIA = 'Adherencia';
    public const FIELD_SUENO = 'Sueno';
    public const FIELD_SUSTANCIAS = 'Consumo de sustancias';
    public const FIELD_RIESGO_SUICIDA = 'Riesgo suicida';
    public const FIELD_ESTADO_MENTAL = 'Estado mental';

    /** @var list<string> campos clínicos cualitativos de la sesión */
    public const SESSION_FIELDS = [
        self::FIELD_MOTIVO,
        self::FIELD_ESTADO_MENTAL,
        self::FIELD_RIESGO_SUICIDA,
        self::FIELD_OBJETIVOS,
        self::FIELD_EVOLUCION,
        self::FIELD_ADHERENCIA,
        self::FIELD_SUENO,
        self::FIELD_SUSTANCIAS,
    ];

    public const SYSTEM_LOCAL = 'bioenlace-mental-health';

    /**
     * Claves del prompt: los campos de sesión más el puntaje de cada instrumento.
     *
     * @return list<string>
     */
    public static function promptFields(): array
    {
        $fields = self::SESSION_FIELDS;
        foreach (MentalHealthInstrumentCatalog::all() as $instrument) {
            $fields[] = $instrument['label'];
        }

        return $fields;
    }

    /**
     * @var array<string, list<string>> alias por clave normalizada
     */
    private const SESSION_ALIASES = [
        self::FIELD_MOTIVO => ['motivo de sesion', 'motivo de consulta', 'motivo'],
        self::FIELD_ESTADO_MENTAL => ['estado mental', 'examen mental', 'psicopatologia'],
        self::FIELD_RIESGO_SUICIDA => ['riesgo suicida', 'ideacion suicida', 'riesgo'],
        self::FIELD_OBJETIVOS => ['objetivos', 'objetivos terapeuticos'],
        self::FIELD_EVOLUCION => ['evolucion', 'evolucion de la sesion'],
        self::FIELD_ADHERENCIA => ['adherencia', 'adherencia al tratamiento'],
        self::FIELD_SUENO => ['sueno', 'calidad del sueno'],
        self::FIELD_SUSTANCIAS => ['consumo de sustancias', 'sustancias', 'consumo'],
    ];

    /**
     * Alias de los instrumentos por código: el label lo aporta el catálogo.
     *
     * @var array<string, list<string>>
     */
    private const INSTRUMENT_ALIASES = [
        MentalHealthInstrumentCatalog::PHQ9 => ['phq-9', 'phq9', 'phq 9', 'phq'],
        MentalHealthInstrumentCatalog::GAD7 => ['gad-7', 'gad7', 'gad 7', 'gad'],
    ];

    public static function panelKind(): string
    {
        return self::PANEL_KIND;
    }

    public static function observationCategory(): string
    {
        return self::OBSERVATION_CATEGORY;
    }

    public static function matchesModelo(string $modelo): bool
    {
        return ExtractedRowFields::matchesModelo($modelo, self::MODELO);
    }

    /**
     * Cómo se guarda un campo: código, sistema y tipo de valor.
     *
     * @return array{code: string, system: string, type: string, unit: ?string}|null
     */
    public static function storageFor(string $field): ?array
    {
        foreach (MentalHealthInstrumentCatalog::all() as $instrument) {
            if ($field === $instrument['label']) {
                return [
                    'code' => $instrument['code'] . '_total',
                    'system' => self::SYSTEM_LOCAL,
                    'type' => 'numeric',
                    'unit' => $instrument['unit'],
                ];
            }
        }
        if (!in_array($field, self::SESSION_FIELDS, true)) {
            return null;
        }

        return [
            'code' => 'mh_' . strtr(mb_strtolower($field, 'UTF-8'), [
                'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', ' ' => '_',
            ]),
            'system' => self::SYSTEM_LOCAL,
            'type' => 'text',
            'unit' => null,
        ];
    }

    /**
     * @param array<string, mixed>|string $row
     */
    public static function assess($row, string $categoryTitle = '', int $index = 0): ClinicalCaptureRowCompleteness
    {
        $values = is_array($row) ? self::normalize($row) : [];

        return new ClinicalCaptureRowCompleteness(
            $values === [] ? self::promptFields() : [],
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

        return $out;
    }

    public static function fieldFor(string $key): ?string
    {
        $normalized = strtr(mb_strtolower(trim($key), 'UTF-8'), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        ]);

        foreach (self::SESSION_ALIASES as $field => $aliases) {
            if (in_array($normalized, $aliases, true)) {
                return $field;
            }
        }
        foreach (MentalHealthInstrumentCatalog::all() as $instrument) {
            $aliases = self::INSTRUMENT_ALIASES[$instrument['code']] ?? [];
            $aliases[] = $instrument['code'];
            if (in_array($normalized, $aliases, true)) {
                return $instrument['label'];
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
        foreach (self::promptFields() as $field) {
            if (!isset($values[$field])) {
                continue;
            }
            $parts[] = $field . ': ' . $values[$field];
            if (count($parts) >= 2) {
                break;
            }
        }

        return $parts === [] ? 'sesión' : implode(' · ', $parts);
    }
}