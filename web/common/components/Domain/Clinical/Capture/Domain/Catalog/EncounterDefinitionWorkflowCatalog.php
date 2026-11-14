<?php

namespace common\components\Domain\Clinical\Capture\Domain\Catalog;

/**
 * Plantillas declarativas de workflow_json para definiciones de captura por oferta/clase.
 */
final class EncounterDefinitionWorkflowCatalog
{
    public const TEMPLATE_AMB_STANDARD = 'amb_standard';
    public const TEMPLATE_AMB_OPHTHALMOLOGY = 'amb_ophthalmology';
    public const TEMPLATE_AMB_ODONTOLOGY = 'amb_odontology';
    public const TEMPLATE_AMB_PEDIATRICS = 'amb_pediatrics';
    public const TEMPLATE_AMB_OBSTETRICS = 'amb_obstetrics';
    public const TEMPLATE_AMB_MENTAL_HEALTH = 'amb_mental_health';
    public const TEMPLATE_AMB_NUTRITION = 'amb_nutrition';
    public const TEMPLATE_AMB_KINESIOLOGY = 'amb_kinesiology';
    public const TEMPLATE_IMP_STANDARD = 'imp_standard';
    public const TEMPLATE_EMER_STANDARD = 'emer_standard';
    public const TEMPLATE_AMB_NURSING = 'amb_nursing';
    public const TEMPLATE_IMP_NURSING = 'imp_nursing';
    public const TEMPLATE_EMER_NURSING = 'emer_nursing';

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public static function templates(): array
    {
        return [
            self::TEMPLATE_AMB_STANDARD => [
                self::step('Motivos de consulta', 'EncounterReason', false),
                self::step('Diagnóstico', 'EncounterDiagnosis', false),
                self::step('Medicación', 'EncounterMedication', false),
                self::step('Prácticas realizadas', 'EncounterPractice', false),
                self::step('Indicaciones', 'EncounterInstruction', false),
                self::step('Derivaciones', 'EncounterReferralRequest', false),
            ],
            self::TEMPLATE_AMB_OPHTHALMOLOGY => [
                self::step('Motivos de consulta', 'EncounterReason', false),
                self::step('Diagnóstico', 'EncounterDiagnosis', false),
                self::step('Medicación', 'EncounterMedication', false),
                self::step('Prácticas oftalmológicas', 'OphthalmologyPractice', false),
                self::step('Estudios oftalmológicos', 'OphthalmologyStudy', false),
                self::step('Receta de lentes', 'OphthalmologyLensPrescription', false),
            ],
            self::TEMPLATE_AMB_ODONTOLOGY => [
                self::step('Motivos de consulta', 'EncounterReason', false),
                self::step('Diagnóstico', 'EncounterDiagnosis', false),
                self::step('Prácticas odontológicas', 'EncounterDentalPractice', false),
                self::step('Diagnósticos odontológicos', 'EncounterDentalDiagnosis', false),
                self::step('Estado dental', 'EncounterDentalStatus', false),
                self::step('Medicación', 'EncounterMedication', false),
            ],
            self::TEMPLATE_AMB_PEDIATRICS => [
                self::step('Motivos de consulta', 'EncounterReason', false),
                self::step('Diagnóstico', 'EncounterDiagnosis', false),
                self::step('Antropometría y crecimiento', 'EncounterNursingCare', false),
                self::step('Medicación', 'EncounterMedication', false),
                self::step('Prácticas realizadas', 'EncounterPractice', false),
                self::step('Indicaciones', 'EncounterInstruction', false),
                self::step('Derivaciones', 'EncounterReferralRequest', false),
            ],
            self::TEMPLATE_AMB_OBSTETRICS => [
                self::step('Motivos de consulta', 'EncounterReason', false),
                self::step('Control prenatal', 'EncounterObstetricControl', false),
                self::step('Diagnóstico', 'EncounterDiagnosis', false),
                self::step('Signos vitales', 'EncounterNursingCare', false),
                self::step('Medicación', 'EncounterMedication', false),
                self::step('Indicaciones', 'EncounterInstruction', false),
                self::step('Derivaciones', 'EncounterReferralRequest', false),
            ],
            self::TEMPLATE_AMB_MENTAL_HEALTH => [
                self::step('Motivos de consulta', 'EncounterReason', false),
                self::step('Sesión', 'EncounterMentalHealthSession', false),
                self::step('Diagnóstico', 'EncounterDiagnosis', false),
                self::step('Medicación', 'EncounterMedication', false),
                self::step('Indicaciones', 'EncounterInstruction', false),
                self::step('Derivaciones', 'EncounterReferralRequest', false),
            ],
            self::TEMPLATE_AMB_NUTRITION => [
                self::step('Motivos de consulta', 'EncounterReason', false),
                self::step('Evaluación nutricional', 'EncounterNutritionAssessment', false),
                self::step('Diagnóstico', 'EncounterDiagnosis', false),
                self::step('Indicaciones', 'EncounterInstruction', false),
                self::step('Derivaciones', 'EncounterReferralRequest', false),
            ],
            self::TEMPLATE_AMB_KINESIOLOGY => [
                self::step('Motivos de consulta', 'EncounterReason', false),
                self::step('Sesión de kinesiología', 'EncounterKinesiologySession', false),
                self::step('Diagnóstico', 'EncounterDiagnosis', false),
                self::step('Indicaciones', 'EncounterInstruction', false),
                self::step('Derivaciones', 'EncounterReferralRequest', false),
            ],
            self::TEMPLATE_EMER_STANDARD => [
                self::step('Motivos de consulta', 'EncounterReason', false),
                self::step('Signos vitales', 'EncounterNursingCare', false),
                self::step('Diagnóstico', 'EncounterDiagnosis', false),
                self::step('Medicación', 'EncounterMedication', false),
                self::step('Prácticas realizadas', 'EncounterPractice', false),
                self::step('Indicaciones', 'EncounterInstruction', false),
                self::step('Derivaciones', 'EncounterReferralRequest', false),
            ],
            self::TEMPLATE_IMP_STANDARD => [
                self::step('Evolución', 'EncounterDiagnosis', false),
                self::step('Medicación', 'EncounterMedication', false),
                self::step('Indicaciones', 'EncounterInstruction', false),
                self::step('Régimen', 'EncounterNutritionOrder', false),
                self::step('Balance hídrico', 'EncounterFluidBalance', false),
            ],
            self::TEMPLATE_AMB_NURSING => [
                self::step('Motivos de consulta', 'EncounterReason', false),
                self::step('Signos vitales', 'EncounterNursingCare', false),
                self::step('Medicación', 'EncounterMedication', false),
                self::step('Indicaciones', 'EncounterInstruction', false),
            ],
            self::TEMPLATE_EMER_NURSING => [
                self::step('Motivos de consulta', 'EncounterReason', false),
                self::step('Signos vitales', 'EncounterNursingCare', false),
                self::step('Medicación', 'EncounterMedication', false),
                self::step('Indicaciones', 'EncounterInstruction', false),
            ],
            self::TEMPLATE_IMP_NURSING => [
                self::step('Signos vitales', 'EncounterNursingCare', false),
                self::step('Evolución', 'EncounterDiagnosis', false),
                self::step('Medicación', 'EncounterMedication', false),
                self::step('Indicaciones', 'EncounterInstruction', false),
                self::step('Régimen', 'EncounterNutritionOrder', false),
                self::step('Balance hídrico', 'EncounterFluidBalance', false),
            ],
        ];
    }

    public static function workflowJsonForTemplate(string $templateKey): string
    {
        $steps = self::templates()[$templateKey] ?? self::templates()[self::TEMPLATE_AMB_STANDARD];

        return json_encode(['conf' => $steps], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Plantilla según oferta del centro (`item_name` + nombre) y clase FHIR.
     * Application extrae esos strings del AR Servicio; Domain no tipa persistencia.
     */
    public static function templateForOffering(string $itemName, string $serviceName, string $encounterClass): string
    {
        $itemName = mb_strtolower(trim($itemName));
        if ($itemName === 'enfermeria') {
            if ($encounterClass === EncounterClassCatalog::IMP) {
                return self::TEMPLATE_IMP_NURSING;
            }
            if ($encounterClass === EncounterClassCatalog::EMER) {
                return self::TEMPLATE_EMER_NURSING;
            }

            return self::TEMPLATE_AMB_NURSING;
        }

        return self::templateForServiceName($serviceName, $encounterClass);
    }

    public static function templateForServiceName(string $serviceName, string $encounterClass): string
    {
        $name = mb_strtoupper(trim($serviceName));

        if ($encounterClass === EncounterClassCatalog::IMP) {
            return self::TEMPLATE_IMP_STANDARD;
        }
        if ($encounterClass === EncounterClassCatalog::EMER) {
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

        if (str_contains($name, 'OBSTETR') || str_contains($name, 'PRENATAL') || str_contains($name, 'EMBARAZ')) {
            return self::TEMPLATE_AMB_OBSTETRICS;
        }

        foreach ([
            'NUTRICION', 'NUTRICIÓN', 'ALIMENTAR',
        ] as $needle) {
            if (str_contains($name, $needle)) {
                return self::TEMPLATE_AMB_NUTRITION;
            }
        }

        foreach ([
            'KINESIOLOG', 'FISIATR', 'REHABILITAC',
        ] as $needle) {
            if (str_contains($name, $needle)) {
                return self::TEMPLATE_AMB_KINESIOLOGY;
            }
        }

        foreach ([
            'SALUD MENTAL',
            'PSIQUIATR',
            'PSICOLOG',
            'PSICOTERAP',
        ] as $needle) {
            if (str_contains($name, $needle)) {
                return self::TEMPLATE_AMB_MENTAL_HEALTH;
            }
        }

        if (str_contains($name, 'PEDIATR') || str_contains($name, 'NEO NAT') || str_contains($name, 'NEONAT')) {
            return self::TEMPLATE_AMB_PEDIATRICS;
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
