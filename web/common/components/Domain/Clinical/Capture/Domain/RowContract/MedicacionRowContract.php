<?php

namespace common\components\Domain\Clinical\Capture\Domain\RowContract;

use common\components\Domain\Clinical\Capture\Domain\Catalog\MedicacionCatalog;
use common\components\Domain\Clinical\Capture\Domain\Model\CaptureIssueFactory;
use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCaptureRowCompleteness;

/** Contrato Domain: medicación (`ConsultaMedicamentos`). */
final class MedicacionRowContract
{
    public const MODELO = 'ConsultaMedicamentos';

    public const TYPE_MENTIONED = 'mentioned';
    public const TYPE_ORDERED = 'ordered';

    public const FIELD_NOMBRE = 'Nombre del medicamento';
    public const FIELD_TIPO = 'Tipo';
    public const FIELD_CANTIDAD = 'Cantidad';
    public const FIELD_VIA = 'Via de administracion';
    public const FIELD_FRECUENCIA = 'Frecuencia de administracion';
    public const FIELD_TIPO_FRECUENCIA = 'Tipo de frecuencia';
    public const FIELD_DURACION = 'Duracion del tratamiento';
    public const FIELD_TIPO_DURACION = 'Tipo de duracion';

    public static function matchesModelo(string $modelo): bool
    {
        return ExtractedRowFields::matchesModelo($modelo, self::MODELO);
    }

    /**
     * @return list<string>
     */
    public static function typeValues(): array
    {
        return [self::TYPE_MENTIONED, self::TYPE_ORDERED];
    }

    /**
     * @param array<string, mixed>|string $row
     * @return array{
     *   nombre: string,
     *   tipo: string|null,
     *   cantidad: string|null,
     *   via: string|null,
     *   frecuencia: string|null,
     *   tipoFrecuencia: string|null,
     *   duracion: string|null,
     *   tipoDuracion: string|null
     * }
     */
    public static function parse($row): array
    {
        $p = [
            'nombre' => '',
            'tipo' => null,
            'cantidad' => null,
            'via' => null,
            'frecuencia' => null,
            'tipoFrecuencia' => null,
            'duracion' => null,
            'tipoDuracion' => null,
        ];
        if (($s = ExtractedRowFields::stringRow($row)) !== null) {
            $p['nombre'] = $s;
        } elseif (is_array($row)) {
            $p['nombre'] = ExtractedRowFields::firstNonEmpty($row, [
                self::FIELD_NOMBRE,
                'nombre',
                'medicamento',
                'medication_display',
                'termino',
                'display',
                'label',
                'texto',
            ]) ?? '';
            $p['tipo'] = ExtractedRowFields::firstNonEmpty($row, [self::FIELD_TIPO, 'tipo', 'type', 'kind']);
            $p['cantidad'] = ExtractedRowFields::firstNonEmpty($row, [self::FIELD_CANTIDAD, 'cantidad', 'dose', 'dosis']);
            $p['via'] = ExtractedRowFields::firstNonEmpty($row, [self::FIELD_VIA, 'via', 'route']);
            $p['frecuencia'] = ExtractedRowFields::firstNonEmpty($row, [self::FIELD_FRECUENCIA, 'frecuencia', 'frequency']);
            $p['tipoFrecuencia'] = ExtractedRowFields::firstNonEmpty($row, [
                self::FIELD_TIPO_FRECUENCIA,
                'tipo_frecuencia',
                'frecuencia_tipo',
            ]);
            $p['duracion'] = ExtractedRowFields::firstNonEmpty($row, [self::FIELD_DURACION, 'duracion', 'durante']);
            $p['tipoDuracion'] = ExtractedRowFields::firstNonEmpty($row, [
                self::FIELD_TIPO_DURACION,
                'tipo_duracion',
                'durante_tipo',
            ]);
        }

        return self::normalizeParsed($p);
    }

    /**
     * @param array<string, mixed>|string $row
     */
    public static function assess($row, string $categoryTitle = '', int $index = 0): ClinicalCaptureRowCompleteness
    {
        $p = self::parse($row);
        $missing = self::missingFields($p);
        $label = $p['nombre'] !== '' ? $p['nombre'] : 'ítem';
        $issues = self::buildIssues($missing, $categoryTitle, $index);

        return new ClinicalCaptureRowCompleteness($missing, $label, $issues);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function applyResolution(array $row, string $field, mixed $value): array
    {
        $row[$field] = is_string($value) ? trim($value) : $value;
        if ($field === self::FIELD_FRECUENCIA && is_numeric($value)) {
            $row[$field] = (string) (int) $value;
            if (empty($row[self::FIELD_TIPO_FRECUENCIA])) {
                $row[self::FIELD_TIPO_FRECUENCIA] = MedicacionCatalog::FRECUENCIA_TIPO_HORA;
            }
        }
        if ($field === self::FIELD_DURACION && is_numeric($value)) {
            $row[$field] = (string) (int) $value;
            if (empty($row[self::FIELD_TIPO_DURACION])) {
                $row[self::FIELD_TIPO_DURACION] = MedicacionCatalog::DURANTE_TIPO_DIA;
            }
        }

        return $row;
    }

    /**
     * @param array{
     *   nombre: string,
     *   tipo: string|null,
     *   cantidad: string|null,
     *   via: string|null,
     *   frecuencia: string|null,
     *   tipoFrecuencia: string|null,
     *   duracion: string|null,
     *   tipoDuracion: string|null
     * } $p
     * @return list<string>
     */
    public static function missingFields(array $p): array
    {
        $missing = [];
        if ($p['nombre'] === '') {
            $missing[] = self::FIELD_NOMBRE;
        }
        $tipo = (string) ($p['tipo'] ?? '');
        if ($tipo === '' || !in_array($tipo, self::typeValues(), true)) {
            $missing[] = self::FIELD_TIPO;
        }
        if ($tipo === self::TYPE_ORDERED) {
            if (trim((string) ($p['cantidad'] ?? '')) === '') {
                $missing[] = self::FIELD_CANTIDAD;
            }
            if (trim((string) ($p['frecuencia'] ?? '')) === '') {
                $missing[] = self::FIELD_FRECUENCIA;
            } elseif (trim((string) ($p['tipoFrecuencia'] ?? '')) === '') {
                $missing[] = self::FIELD_TIPO_FRECUENCIA;
            } elseif (!isset(MedicacionCatalog::FRECUENCIAS[(string) $p['tipoFrecuencia']])) {
                $missing[] = self::FIELD_TIPO_FRECUENCIA;
            }
        }
        if (trim((string) ($p['duracion'] ?? '')) !== '') {
            $td = trim((string) ($p['tipoDuracion'] ?? ''));
            if ($td === '' || !isset(MedicacionCatalog::DURANTES[$td])) {
                $missing[] = self::FIELD_TIPO_DURACION;
            }
        }

        return $missing;
    }

    /**
     * @return list<array{value: mixed, label: string}>
     */
    public static function optionsForField(string $field): array
    {
        if ($field === self::FIELD_TIPO) {
            return [
                ['value' => self::TYPE_ORDERED, 'label' => 'Indicada / prescrita'],
                ['value' => self::TYPE_MENTIONED, 'label' => 'Solo mencionada'],
            ];
        }
        if ($field === self::FIELD_TIPO_FRECUENCIA) {
            return self::mapToOptions(MedicacionCatalog::FRECUENCIAS);
        }
        if ($field === self::FIELD_TIPO_DURACION) {
            return self::mapToOptions(MedicacionCatalog::DURANTES);
        }
        if ($field === self::FIELD_FRECUENCIA) {
            $out = [];
            foreach ([1, 2, 3, 4, 6, 8, 12, 24] as $n) {
                $out[] = ['value' => (string) $n, 'label' => 'Cada ' . $n];
            }

            return $out;
        }
        if ($field === self::FIELD_DURACION) {
            $out = [];
            foreach ([1, 3, 5, 7, 10, 14, 30] as $n) {
                $out[] = ['value' => (string) $n, 'label' => (string) $n];
            }

            return $out;
        }
        if ($field === self::FIELD_VIA) {
            return [
                ['value' => 'oral', 'label' => 'Oral'],
                ['value' => 'sublingual', 'label' => 'Sublingual'],
                ['value' => 'IM', 'label' => 'Intramuscular'],
                ['value' => 'IV', 'label' => 'Intravenosa'],
                ['value' => 'subcutánea', 'label' => 'Subcutánea'],
                ['value' => 'tópica', 'label' => 'Tópica'],
                ['value' => 'inhalatoria', 'label' => 'Inhalatoria'],
            ];
        }
        if ($field === self::FIELD_CANTIDAD) {
            return [
                ['value' => '400 mg', 'label' => '400 mg'],
                ['value' => '500 mg', 'label' => '500 mg'],
                ['value' => '1 g', 'label' => '1 g'],
                ['value' => '1 comprimido', 'label' => '1 comprimido'],
                ['value' => '2 comprimidos', 'label' => '2 comprimidos'],
                ['value' => '5 ml', 'label' => '5 ml'],
                ['value' => '10 ml', 'label' => '10 ml'],
                ['value' => '1 sobre', 'label' => '1 sobre'],
            ];
        }

        return [];
    }

    /**
     * @param array{
     *   nombre: string,
     *   tipo: string|null,
     *   cantidad: string|null,
     *   via: string|null,
     *   frecuencia: string|null,
     *   tipoFrecuencia: string|null,
     *   duracion: string|null,
     *   tipoDuracion: string|null
     * } $p
     * @return array{
     *   nombre: string,
     *   tipo: string|null,
     *   cantidad: string|null,
     *   via: string|null,
     *   frecuencia: string|null,
     *   tipoFrecuencia: string|null,
     *   duracion: string|null,
     *   tipoDuracion: string|null
     * }
     */
    private static function normalizeParsed(array $p): array
    {
        if (trim((string) ($p['tipo'] ?? '')) === '') {
            $hasDosing = trim((string) ($p['cantidad'] ?? '')) !== ''
                || trim((string) ($p['frecuencia'] ?? '')) !== ''
                || trim((string) ($p['via'] ?? '')) !== ''
                || trim((string) ($p['duracion'] ?? '')) !== '';
            $p['tipo'] = $hasDosing ? self::TYPE_ORDERED : self::TYPE_MENTIONED;
        }

        $raw = strtolower(trim((string) $p['tipo']));
        $raw = str_replace(['-', ' '], '_', $raw);
        $map = [
            'mentioned' => self::TYPE_MENTIONED,
            'mencion' => self::TYPE_MENTIONED,
            'mención' => self::TYPE_MENTIONED,
            'registrado' => self::TYPE_MENTIONED,
            'registro' => self::TYPE_MENTIONED,
            'ordered' => self::TYPE_ORDERED,
            'order' => self::TYPE_ORDERED,
            'indicado' => self::TYPE_ORDERED,
            'indicada' => self::TYPE_ORDERED,
            'prescripto' => self::TYPE_ORDERED,
            'prescripta' => self::TYPE_ORDERED,
            'prescribe' => self::TYPE_ORDERED,
            'prescripcion' => self::TYPE_ORDERED,
            'prescripción' => self::TYPE_ORDERED,
        ];
        $p['tipo'] = $map[$raw] ?? ($raw !== '' ? $raw : null);

        if ($p['tipo'] === self::TYPE_ORDERED
            && trim((string) ($p['frecuencia'] ?? '')) !== ''
            && trim((string) ($p['tipoFrecuencia'] ?? '')) === '') {
            $p['tipoFrecuencia'] = MedicacionCatalog::FRECUENCIA_TIPO_DIA;
        }
        if (trim((string) ($p['duracion'] ?? '')) !== ''
            && trim((string) ($p['tipoDuracion'] ?? '')) === '') {
            $folded = mb_strtolower((string) $p['duracion'], 'UTF-8');
            if (str_contains($folded, 'cronic') || str_contains($folded, 'crónic')) {
                $p['tipoDuracion'] = MedicacionCatalog::DURANTE_TIPO_CRONICO;
            } else {
                $p['tipoDuracion'] = MedicacionCatalog::DURANTE_TIPO_DIA;
            }
        }

        return $p;
    }

    /**
     * @param list<string> $missing
     * @return list<array{id: string, field: string, options: list<array{value: mixed, label: string}>, allow_custom: bool}>
     */
    private static function buildIssues(array $missing, string $category, int $index): array
    {
        $issues = [];
        foreach ($missing as $field) {
            if ($field === self::FIELD_NOMBRE) {
                continue;
            }
            $options = self::optionsForField($field);
            if ($options === []) {
                continue;
            }
            $issues[] = CaptureIssueFactory::make($category, $index, $field, $options, false);
        }

        return $issues;
    }

    /**
     * @param array<string, string> $map
     * @return list<array{value: mixed, label: string}>
     */
    private static function mapToOptions(array $map): array
    {
        $out = [];
        foreach ($map as $value => $label) {
            $out[] = ['value' => $value, 'label' => $label];
        }

        return $out;
    }
}
