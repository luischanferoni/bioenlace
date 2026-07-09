<?php

namespace common\components\Domain\Clinical\Workflow;

use common\models\Clinical\Encounter;

/**
 * Plantillas declarativas de workflow_json para {@see \common\models\Clinical\EncounterDefinition}.
 */
final class EncounterDefinitionWorkflowCatalog
{
    public const TEMPLATE_AMB_STANDARD = 'amb_standard';
    public const TEMPLATE_AMB_OPHTHALMOLOGY = 'amb_ophthalmology';
    public const TEMPLATE_AMB_ODONTOLOGY = 'amb_odontology';
    public const TEMPLATE_IMP_STANDARD = 'imp_standard';
    public const TEMPLATE_EMER_STANDARD = 'emer_standard';

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public static function templates(): array
    {
        return [
            self::TEMPLATE_AMB_STANDARD => [
                self::step('Motivos de consulta', 'ConsultaMotivos', false),
                self::step('Diagnóstico', 'DiagnosticoConsulta', false),
                self::step('Medicación', 'ConsultaMedicamentos', false),
                self::step('Prácticas e indicaciones', 'ConsultaPracticas', false),
                self::step('Derivaciones', 'ConsultaDerivaciones', false),
            ],
            self::TEMPLATE_AMB_OPHTHALMOLOGY => [
                self::step('Motivos de consulta', 'ConsultaMotivos', false),
                self::step('Diagnóstico', 'DiagnosticoConsulta', false),
                self::step('Medicación', 'ConsultaMedicamentos', false),
                self::step('Prácticas oftalmológicas', 'ConsultaPracticasOftalmologia', false),
                self::step('Estudios oftalmológicos', 'ConsultaPracticasOftalmologiaEstudios', false),
                self::step('Receta de lentes', 'ConsultasRecetaLentes', false),
            ],
            self::TEMPLATE_AMB_ODONTOLOGY => [
                self::step('Motivos de consulta', 'ConsultaMotivos', false),
                self::step('Diagnóstico', 'DiagnosticoConsulta', false),
                self::step('Prácticas odontológicas', 'ConsultaOdontologiaPracticas', false),
                self::step('Diagnósticos odontológicos', 'ConsultaOdontologiaDiagnosticos', false),
                self::step('Estado dental', 'ConsultaOdontologiaEstados', false),
                self::step('Medicación', 'ConsultaMedicamentos', false),
            ],
            self::TEMPLATE_IMP_STANDARD => [
                self::step('Evolución', 'DiagnosticoConsulta', false),
                self::step('Medicación', 'ConsultaMedicamentos', false),
                self::step('Indicaciones', 'ConsultaPracticas', false),
                self::step('Régimen', 'ConsultaRegimen', false),
                self::step('Balance hídrico', 'ConsultaBalanceHidrico', false),
            ],
            self::TEMPLATE_EMER_STANDARD => [
                self::step('Motivos de consulta', 'ConsultaMotivos', false),
                self::step('Diagnóstico', 'DiagnosticoConsulta', false),
                self::step('Medicación', 'ConsultaMedicamentos', false),
                self::step('Prácticas e indicaciones', 'ConsultaPracticas', false),
            ],
        ];
    }

    public static function workflowJsonForTemplate(string $templateKey): string
    {
        $steps = self::templates()[$templateKey] ?? self::templates()[self::TEMPLATE_AMB_STANDARD];

        return json_encode(['conf' => $steps], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function templateForServiceName(string $serviceName, string $encounterClass): string
    {
        $name = mb_strtoupper(trim($serviceName));

        if ($encounterClass === Encounter::ENCOUNTER_CLASS_IMP) {
            return self::TEMPLATE_IMP_STANDARD;
        }
        if ($encounterClass === Encounter::ENCOUNTER_CLASS_EMER) {
            return self::TEMPLATE_EMER_STANDARD;
        }

        if (str_contains($name, 'ODONTO')) {
            return self::TEMPLATE_AMB_ODONTOLOGY;
        }

        foreach ([
            'OFTALMO',
            'RETINA',
            'CORNEA',
            'YAG LASER',
            'OCULOPLAST',
            'NEUROOFTALMO',
            'SEGMENTO ANTERIOR',
            'BAJA VISION',
        ] as $needle) {
            if (str_contains($name, $needle)) {
                return self::TEMPLATE_AMB_OPHTHALMOLOGY;
            }
        }

        return self::TEMPLATE_AMB_STANDARD;
    }

    /**
     * @return array{titulo: string, relacion: string, requerido: bool, url: string}
     */
    private static function step(string $titulo, string $relacion, bool $requerido): array
    {
        return [
            'titulo' => $titulo,
            'relacion' => $relacion,
            'requerido' => $requerido,
            'url' => '',
        ];
    }
}
