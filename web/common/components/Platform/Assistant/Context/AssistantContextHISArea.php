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

    /**
     * @return list<AssistantContextHISAreaAspect>
     */
    public static function defaultAspects(string $areaId): array
    {
        $out = [];
        foreach (AssistantContextHISAreaAspect::allForArea($areaId) as $aspect) {
            if (AssistantContextHISAreaAspect::isImplemented($aspect)) {
                $out[] = $aspect;
            }
        }

        return $out;
    }
}
