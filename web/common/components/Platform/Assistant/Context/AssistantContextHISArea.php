<?php

namespace common\components\Platform\Assistant\Context;

/**
 * Áreas top-level del HIS expuestas al preprocess (lista cerrada).
 */
final class AssistantContextHISArea
{
    public const APPOINTMENTS = 'appointments';
    public const ENCOUNTERS = 'encounters';
    public const CLINICAL_RECORD = 'clinical_record';
    public const DIAGNOSTICS = 'diagnostics';
    public const MEDICATION = 'medication';
    public const REPRESENTATION = 'representation';
    public const COVERAGE = 'coverage';
    public const PRODUCT = 'product';
    public const GEO_RESOURCES = 'geo_resources';

    /** @var array<string, string> id => descripción para preprocess */
    private const CATALOG = [
        self::APPOINTMENTS => 'Citas y turnos del paciente; reglas del centro sobre citas',
        self::ENCOUNTERS => 'Atenciones y consultas ya realizadas',
        self::CLINICAL_RECORD => 'Resumen clínico del paciente (alergias, medicación, condiciones)',
        self::DIAGNOSTICS => 'Estudios, laboratorio y resultados',
        self::MEDICATION => 'Recetas y medicación',
        self::REPRESENTATION => 'Tutela, representantes y operar por otro',
        self::COVERAGE => 'Cobertura y obra social',
        self::PRODUCT => 'Cómo funciona Bioenlace (guías de uso)',
        self::GEO_RESOURCES => 'Centros, ubicación y recursos del sistema de salud',
    ];

    /**
     * @return list<string>
     */
    public static function sortByProductPriority(array $areas): array
    {
        $valid = [];
        foreach ($areas as $area) {
            if (!is_string($area)) {
                continue;
            }
            $area = trim($area);
            if ($area !== '' && self::isValid($area) && !in_array($area, $valid, true)) {
                $valid[] = $area;
            }
        }
        if ($valid === []) {
            return [];
        }

        $order = array_flip(self::productPriorityOrder());
        usort(
            $valid,
            static fn (string $a, string $b): int => ($order[$a] ?? 999) <=> ($order[$b] ?? 999)
        );

        return $valid;
    }

    /**
     * @return list<string>
     */
    private static function productPriorityOrder(): array
    {
        return [
            self::APPOINTMENTS,
            self::ENCOUNTERS,
            self::CLINICAL_RECORD,
            self::DIAGNOSTICS,
            self::MEDICATION,
            self::REPRESENTATION,
            self::COVERAGE,
            self::PRODUCT,
            self::GEO_RESOURCES,
        ];
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_keys(self::CATALOG);
    }

    public static function isValid(string $id): bool
    {
        return isset(self::CATALOG[trim($id)]);
    }

    public static function description(string $id): string
    {
        return self::CATALOG[$id] ?? '';
    }

    /**
     * Texto para inyectar en el prompt del preprocess.
     */
    public static function catalogForPreprocess(): string
    {
        $lines = [
            'Áreas del sistema (context_areas). Devolvé solo claves de la lista.',
            'Si el mensaje es solo saludo o meta sin necesidad de datos del HIS, devolvé [].',
            '',
        ];
        foreach (self::CATALOG as $id => $desc) {
            $lines[] = '- ' . $id . ' — ' . $desc;
        }

        return implode("\n", $lines);
    }
}
