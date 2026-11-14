<?php

namespace common\components\Domain\Clinical\Specialty\Domain\Catalog;

use common\components\Domain\Clinical\Capture\Domain\Catalog\EncounterDefinitionWorkflowCatalog;
use common\components\Domain\Clinical\Capture\Domain\RowContract\EnfermeriaSignosVitalesRowContract;
use common\components\Domain\Clinical\Capture\Domain\RowContract\KinesiologiaSesionRowContract;
use common\components\Domain\Clinical\Capture\Domain\RowContract\NutricionEvaluacionRowContract;
use common\components\Domain\Clinical\Capture\Domain\RowContract\ObstetriciaControlRowContract;
use common\components\Domain\Clinical\Capture\Domain\RowContract\SaludMentalSesionRowContract;
use common\components\Domain\Clinical\Specialty\Domain\Catalog\MentalHealthInstrumentCatalog;
use common\components\Domain\Clinical\Capture\Domain\RowContract\OdontologiaItemRowContract;
use common\components\Domain\Clinical\Capture\Domain\RowContract\OftalmologiaEstudioRowContract;
use common\components\Domain\Clinical\Capture\Domain\RowContract\OftalmologiaPracticaRowContract;
use common\components\Domain\Clinical\Capture\Domain\RowContract\OftalmologiaRecetaRowContract;

/**
 * Fuente única de las especialidades clínicas y de lo que se interpreta en cada una.
 *
 * Tres estados:
 *
 * - `specific`: el workflow de captura pide campos propios (`sections`).
 * - `standard`: opera con el workflow clínico estándar; los campos son los de
 *   {@see standardSectionLabels()}.
 * - `planned`: oferta declarada sin configuración de captura todavía.
 *
 * Los `key` de cada sección son la clave `relacion` de `workflow_json`; los `key`
 * de cada campo son exactamente las claves que la IA debe devolver, tomadas del
 * contrato de fila (`PROMPT_FIELDS`) cuando existe. Un campo con
 * `extracted => false` está declarado para la UI pero aún no se le pide a la IA.
 */
final class ClinicalSpecialtyCatalog
{
    public const ODONTOLOGY = 'odontology';
    public const OPHTHALMOLOGY = 'ophthalmology';
    public const NURSING = 'nursing';
    public const GENERAL_MEDICINE = 'general-medicine';
    public const PEDIATRICS = 'pediatrics';
    public const MENTAL_HEALTH = 'mental-health';
    public const PSYCHOLOGY = 'psychology';
    public const OBSTETRICS = 'obstetrics';
    public const GYNECOLOGY = 'gynecology';
    public const CARDIOLOGY = 'cardiology';
    public const PNEUMONOLOGY = 'pneumonology';
    public const ENDOCRINOLOGY = 'endocrinology';
    public const NEPHROLOGY = 'nephrology';
    public const UROLOGY = 'urology';
    public const NEUROLOGY = 'neurology';
    public const DERMATOLOGY = 'dermatology';
    public const TRAUMATOLOGY = 'traumatology';
    public const GENERAL_SURGERY = 'general-surgery';
    public const ANESTHESIOLOGY = 'anesthesiology';
    public const NUTRITION = 'nutrition';
    public const KINESIOLOGY = 'kinesiology';
    public const GERIATRICS = 'geriatrics';
    public const ALLERGY = 'allergy';
    public const OTORHINOLARYNGOLOGY = 'otorhinolaryngology';
    public const SPEECH_THERAPY = 'speech-therapy';
    public const OPHTHALMOLOGY_OPTOMETRY = 'optometry';
    public const ORTHODONTICS = 'orthodontics';
    public const NEONATOLOGY = 'neonatology';
    public const INFECTOLOGY = 'infectology';
    public const RHEUMATOLOGY = 'rheumatology';
    public const HEMATOLOGY = 'hematology';
    public const GASTROENTEROLOGY = 'gastroenterology';
    public const PALLIATIVE_CARE = 'palliative-care';
    public const OCCUPATIONAL_MEDICINE = 'occupational-medicine';
    public const MEDICAL_GENETICS = 'medical-genetics';

    public const STATUS_SPECIFIC = 'specific';
    public const STATUS_STANDARD = 'standard';
    public const STATUS_PLANNED = 'planned';

    /**
     * @return array<string, array{code: string, label: string, status: string, public_copy: string, sections: list<array<string, mixed>>, extra_sections: list<array<string, mixed>>}>
     */
    public static function all(): array
    {
        return self::definitions();
    }

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_keys(self::definitions());
    }

    public static function isValid(string $code): bool
    {
        return isset(self::definitions()[$code]);
    }

    /**
     * @return array{code: string, label: string, status: string, public_copy: string, sections: list<array<string, mixed>>}|null
     */
    public static function get(string $code): ?array
    {
        return self::definitions()[$code] ?? null;
    }

    public static function label(string $code): string
    {
        return self::definitions()[$code]['label'] ?? $code;
    }

    /**
     * Secciones de captura propias de la especialidad (reemplazan al workflow estándar).
     *
     * @return list<array<string, mixed>>
     */
    public static function sectionsFor(string $code): array
    {
        return self::definitions()[$code]['sections'] ?? [];
    }

    /**
     * Categorías que la especialidad agrega al workflow estándar.
     *
     * No identifican a la especialidad: el modelo de captura sigue perteneciendo a
     * quien lo declaró como sección propia.
     *
     * @return list<array<string, mixed>>
     */
    public static function extraSectionsFor(string $code): array
    {
        return self::definitions()[$code]['extra_sections'] ?? [];
    }

    /**
     * Campos que la IA devuelve hoy para la especialidad, con su etiqueta.
     *
     * @return array<string, string> clave => etiqueta
     */
    public static function extractedFieldsFor(string $code): array
    {
        $out = [];
        $sections = array_merge(self::sectionsFor($code), self::extraSectionsFor($code));
        foreach ($sections as $section) {
            foreach ($section['fields'] as $field) {
                if (($field['extracted'] ?? true) === true) {
                    $out[$field['key']] = $field['label'];
                }
            }
        }

        return $out;
    }

    /**
     * Categorías del workflow clínico estándar, derivadas de su plantilla.
     *
     * @return list<string>
     */
    public static function standardSectionLabels(): array
    {
        $steps = EncounterDefinitionWorkflowCatalog::templates()[EncounterDefinitionWorkflowCatalog::TEMPLATE_AMB_STANDARD];

        return array_values(array_map(
            static fn (array $step): string => (string) $step['titulo'],
            $steps
        ));
    }

    /**
     * Especialidad a la que pertenece una categoría de captura.
     */
    public static function specialtyForCaptureModel(string $model): ?string
    {
        $model = self::shortModelName($model);
        if ($model === '') {
            return null;
        }
        foreach (self::definitions() as $code => $definition) {
            foreach ($definition['sections'] as $section) {
                if (($section['key'] ?? '') === $model) {
                    return $code;
                }
            }
        }

        return null;
    }

    private static function shortModelName(string $model): string
    {
        $model = trim($model);
        $pos = strrpos($model, '\\');

        return $pos === false ? $model : substr($model, $pos + 1);
    }

    /**
     * @return array<string, array{code: string, label: string, status: string, public_copy: string, sections: list<array<string, mixed>>, extra_sections: list<array<string, mixed>>}>
     */
    private static function definitions(): array
    {
        static $definitions = null;
        if ($definitions !== null) {
            return $definitions;
        }

        $definitions = [
            self::ODONTOLOGY => self::specific(
                self::ODONTOLOGY,
                'Odontología',
                'Pieza dental, caras afectadas y momento del tratamiento, además de prácticas, '
                . 'diagnósticos y estado de cada pieza, con vista de odontograma.',
                [
                    self::dentalSection('EncounterDentalPractice', 'Prácticas odontológicas', 1),
                    self::dentalSection('EncounterDentalDiagnosis', 'Diagnósticos odontológicos', 2),
                    self::dentalSection('EncounterDentalStatus', 'Estado dental', 3),
                ]
            ),
            self::ORTHODONTICS => self::planned(
                self::ORTHODONTICS,
                'Ortodoncia',
                'Clase de Angle, overjet, overbite, apiñamiento y aparatología en cada control.'
            ),
            self::OPHTHALMOLOGY => self::specific(
                self::OPHTHALMOLOGY,
                'Oftalmología',
                'Ojo, prueba, estado e informe de cada práctica y estudio, y receta de lentes '
                . 'con esfera, cilindro, eje y adición por ojo.',
                [
                    self::practiceSection(),
                    self::studySection(),
                    self::lensSection(),
                ]
            ),
            self::OPHTHALMOLOGY_OPTOMETRY => self::planned(
                self::OPHTHALMOLOGY_OPTOMETRY,
                'Optometría',
                'Agudeza visual, queratometría y distancia pupilar junto a la receta óptica.'
            ),
            self::NURSING => self::standard(
                self::NURSING,
                'Enfermería',
                'Signos vitales y antropometría en cada control, con el registro de las prácticas '
                . 'de enfermería realizadas.',
                [self::vitalsSection(
                    'Signos vitales',
                    EnfermeriaSignosVitalesRowContract::PROMPT_FIELDS
                )]
            ),
            self::GENERAL_MEDICINE => self::standard(
                self::GENERAL_MEDICINE,
                'Clínica médica y medicina familiar',
                'Motivo, diagnóstico, medicación, prácticas, indicaciones y derivaciones en una '
                . 'misma nota clínica estructurada.'
            ),
            self::PEDIATRICS => self::standard(
                self::PEDIATRICS,
                'Pediatría',
                'Peso, talla, perímetro cefálico y circunferencia abdominal en cada control, '
                . 'graficados en las curvas de crecimiento junto al registro clínico completo.',
                [self::vitalsSection(
                    'Signos vitales y antropometría',
                    EnfermeriaSignosVitalesRowContract::ANTHROPOMETRY_FIELDS
                )]
            ),
            self::NEONATOLOGY => self::planned(
                self::NEONATOLOGY,
                'Neonatología',
                'Peso al nacer, edad gestacional, Apgar y tipo de parto del recién nacido.'
            ),
            self::OBSTETRICS => self::specific(
                self::OBSTETRICS,
                'Obstetricia',
                'FUM, edad gestacional, gravidez y paridad, altura uterina, latido fetal, '
                . 'presentación y controles prenatales.',
                [self::obstetricSection()]
            ),
            self::GYNECOLOGY => self::planned(
                self::GYNECOLOGY,
                'Ginecología',
                'Ciclo menstrual, método anticonceptivo, PAP, colposcopía y estudios de imagen.'
            ),
            self::MENTAL_HEALTH => self::specific(
                self::MENTAL_HEALTH,
                'Salud mental',
                'Estado mental, riesgo, evolución por sesión y escalas de screening como PHQ-9 '
                . 'y GAD-7 con su puntaje e interpretación.',
                [self::mentalHealthSection()]
            ),
            self::PSYCHOLOGY => self::standard(
                self::PSYCHOLOGY,
                'Psicología',
                'Motivo de la sesión, objetivos, evolución y adherencia, con seguimiento del '
                . 'plan terapéutico.',
                [self::mentalHealthSection()]
            ),
            self::CARDIOLOGY => self::planned(
                self::CARDIOLOGY,
                'Cardiología',
                'Tensión arterial, frecuencia cardíaca, hallazgos del ECG, clase funcional y '
                . 'factores de riesgo.'
            ),
            self::PNEUMONOLOGY => self::planned(
                self::PNEUMONOLOGY,
                'Neumonología',
                'Frecuencia respiratoria, saturación de oxígeno y valores de espirometría '
                . 'con sus relaciones.'
            ),
            self::ENDOCRINOLOGY => self::planned(
                self::ENDOCRINOLOGY,
                'Endocrinología',
                'Glucemia, hemoglobina glicosilada, hormonas tiroideas y medidas antropométricas.'
            ),
            self::NEPHROLOGY => self::planned(
                self::NEPHROLOGY,
                'Nefrología',
                'Creatinina, filtrado glomerular, proteinuria y diuresis del seguimiento renal.'
            ),
            self::UROLOGY => self::planned(
                self::UROLOGY,
                'Urología',
                'PSA, flujo urinario, hallazgos ecográficos y tacto rectal.'
            ),
            self::NEUROLOGY => self::standard(
                self::NEUROLOGY,
                'Neurología',
                'Examen neurológico y Glasgow sobre el registro clínico completo del encuentro.'
            ),
            self::DERMATOLOGY => self::planned(
                self::DERMATOLOGY,
                'Dermatología',
                'Topografía, morfología, tamaño, fototipo y hallazgos dermatoscópicos de cada lesión.'
            ),
            self::TRAUMATOLOGY => self::planned(
                self::TRAUMATOLOGY,
                'Traumatología y ortopedia',
                'Región, lado, mecanismo de lesión, rango de movilidad y fuerza muscular.'
            ),
            self::GENERAL_SURGERY => self::planned(
                self::GENERAL_SURGERY,
                'Cirugía general',
                'Procedimiento, hallazgos intraoperatorios, riesgo anestésico y complicaciones.'
            ),
            self::ANESTHESIOLOGY => self::planned(
                self::ANESTHESIOLOGY,
                'Anestesiología',
                'Clasificación ASA, vía aérea y tipo de anestesia de cada procedimiento.'
            ),
            self::NUTRITION => self::specific(
                self::NUTRITION,
                'Nutrición',
                'Peso, talla, cintura y composición corporal con el IMC calculado, más el plan '
                . 'alimentario y su adherencia.',
                [self::nutritionSection()]
            ),
            self::KINESIOLOGY => self::specific(
                self::KINESIOLOGY,
                'Kinesiología y fisiatría',
                'Región y lado, rango de movilidad, fuerza muscular e intensidad del dolor en '
                . 'cada sesión del plan.',
                [self::kinesiologySection()]
            ),
            self::GERIATRICS => self::planned(
                self::GERIATRICS,
                'Geriatría',
                'Escalas de autonomía y función cognitiva, riesgo de caídas y polifarmacia.'
            ),
            self::ALLERGY => self::planned(
                self::ALLERGY,
                'Alergia e inmunología',
                'Alérgeno, tipo de reacción, gravedad y estudios de sensibilización.'
            ),
            self::OTORHINOLARYNGOLOGY => self::planned(
                self::OTORHINOLARYNGOLOGY,
                'Otorrinolaringología',
                'Hallazgos de otoscopía, rinoscopía y audiometría de cada evaluación.'
            ),
            self::SPEECH_THERAPY => self::planned(
                self::SPEECH_THERAPY,
                'Fonoaudiología',
                'Audiometría, logoaudiometría e impedanciometría del estudio audiológico.'
            ),
            self::INFECTOLOGY => self::planned(
                self::INFECTOLOGY,
                'Infectología',
                'Foco infeccioso, cultivos y esquema antibiótico indicado.'
            ),
            self::RHEUMATOLOGY => self::planned(
                self::RHEUMATOLOGY,
                'Reumatología',
                'Articulaciones afectadas, rigidez matinal, dolor y actividad de la enfermedad.'
            ),
            self::HEMATOLOGY => self::planned(
                self::HEMATOLOGY,
                'Hematología y oncología',
                'Estadio, estado funcional, esquema de tratamiento y valores hematológicos.'
            ),
            self::GASTROENTEROLOGY => self::planned(
                self::GASTROENTEROLOGY,
                'Gastroenterología',
                'Hallazgos endoscópicos, biopsias y scores de severidad hepática.'
            ),
            self::PALLIATIVE_CARE => self::planned(
                self::PALLIATIVE_CARE,
                'Cuidados paliativos',
                'Estado funcional, control del dolor, opioides y plan de confort.'
            ),
            self::OCCUPATIONAL_MEDICINE => self::planned(
                self::OCCUPATIONAL_MEDICINE,
                'Medicina del trabajo',
                'Exposición laboral, aptitud médica y elementos de protección indicados.'
            ),
            self::MEDICAL_GENETICS => self::planned(
                self::MEDICAL_GENETICS,
                'Genética médica',
                'Cariotipo, variante identificada y consejo genético.'
            ),
        ];

        return $definitions;
    }

    /**
     * @param list<array<string, mixed>> $sections
     * @return array{code: string, label: string, status: string, public_copy: string, sections: list<array<string, mixed>>, extra_sections: list<array<string, mixed>>}
     */
    private static function specific(string $code, string $label, string $publicCopy, array $sections): array
    {
        return [
            'code' => $code,
            'label' => $label,
            'status' => self::STATUS_SPECIFIC,
            'public_copy' => $publicCopy,
            'sections' => $sections,
            'extra_sections' => [],
        ];
    }

    /**
     * @param list<array<string, mixed>> $extraSections categorías sumadas al workflow estándar
     * @return array{code: string, label: string, status: string, public_copy: string, sections: list<array<string, mixed>>, extra_sections: list<array<string, mixed>>}
     */
    private static function standard(
        string $code,
        string $label,
        string $publicCopy,
        array $extraSections = []
    ): array {
        return [
            'code' => $code,
            'label' => $label,
            'status' => self::STATUS_STANDARD,
            'public_copy' => $publicCopy,
            'sections' => [],
            'extra_sections' => $extraSections,
        ];
    }

    /**
     * @return array{code: string, label: string, status: string, public_copy: string, sections: list<array<string, mixed>>, extra_sections: list<array<string, mixed>>}
     */
    private static function planned(string $code, string $label, string $publicCopy): array
    {
        return [
            'code' => $code,
            'label' => $label,
            'status' => self::STATUS_PLANNED,
            'public_copy' => $publicCopy,
            'sections' => [],
            'extra_sections' => [],
        ];
    }

    /**
     * Sección odontológica: los campos salen del contrato de fila, así el prompt
     * y la UI no pueden desfasar.
     *
     * @return array<string, mixed>
     */
    private static function dentalSection(string $key, string $label, int $order): array
    {
        $labels = [
            OdontologiaItemRowContract::FIELD_TIPO => ['Tipo', null, 'text'],
            OdontologiaItemRowContract::FIELD_CODIGO => ['Código', null, 'text'],
            OdontologiaItemRowContract::FIELD_PIEZA => ['Pieza', null, 'text'],
            OdontologiaItemRowContract::FIELD_CARAS => ['Caras', null, 'text'],
            OdontologiaItemRowContract::FIELD_TIEMPO => ['Momento', null, 'enum'],
        ];

        $fields = [];
        $index = 0;
        foreach (OdontologiaItemRowContract::PROMPT_FIELDS as $promptKey) {
            $index++;
            [$fieldLabel, $unit, $format] = $labels[$promptKey] ?? [$promptKey, null, 'text'];
            $fields[] = self::field(
                $promptKey,
                $fieldLabel,
                $unit,
                $format,
                $index,
                true,
                $format === 'enum' ? OdontologiaItemRowContract::TIME_QUALIFIERS : null
            );
        }

        return [
            'key' => $key,
            'label' => $label,
            'order' => $order,
            'fields' => $fields,
            'validation' => [
                'type' => 'at_least_one',
                'fields' => [OdontologiaItemRowContract::FIELD_TIPO, OdontologiaItemRowContract::FIELD_CODIGO],
            ],
        ];
    }

    /**
     * Evaluación nutricional: el IMC se declara derivado, no extraído.
     *
     * @return array<string, mixed>
     */
    private static function nutritionSection(): array
    {
        $labels = [
            NutricionEvaluacionRowContract::FIELD_PESO => ['Peso', 'kg', 'decimal'],
            NutricionEvaluacionRowContract::FIELD_TALLA => ['Talla', 'cm', 'decimal'],
            NutricionEvaluacionRowContract::FIELD_CINTURA => ['Circunferencia de cintura', 'cm', 'decimal'],
            NutricionEvaluacionRowContract::FIELD_GRASA => ['Porcentaje de grasa', '%', 'decimal'],
            NutricionEvaluacionRowContract::FIELD_OBJETIVO => ['Objetivo', null, 'text'],
            NutricionEvaluacionRowContract::FIELD_PLAN => ['Plan alimentario', null, 'text'],
            NutricionEvaluacionRowContract::FIELD_ADHERENCIA => ['Adherencia', null, 'text'],
        ];

        $fields = [];
        $index = 0;
        foreach (NutricionEvaluacionRowContract::PROMPT_FIELDS as $promptKey) {
            $index++;
            [$fieldLabel, $unit, $format] = $labels[$promptKey] ?? [$promptKey, null, 'text'];
            $fields[] = self::field($promptKey, $fieldLabel, $unit, $format, $index, true);
        }
        $fields[] = self::field(
            NutricionEvaluacionRowContract::FIELD_IMC,
            'IMC (calculado)',
            'kg/m2',
            'decimal',
            $index + 1,
            false
        );

        return [
            'key' => NutricionEvaluacionRowContract::MODELO,
            'label' => 'Evaluación nutricional',
            'order' => 1,
            'fields' => $fields,
            'validation' => [
                'type' => 'at_least_one',
                'fields' => NutricionEvaluacionRowContract::PROMPT_FIELDS,
            ],
        ];
    }

    /**
     * Sesión de kinesiología.
     *
     * @return array<string, mixed>
     */
    private static function kinesiologySection(): array
    {
        $labels = [
            KinesiologiaSesionRowContract::FIELD_REGION => ['Región', null, 'text'],
            KinesiologiaSesionRowContract::FIELD_LADO => ['Lado', null, 'enum'],
            KinesiologiaSesionRowContract::FIELD_MOVILIDAD => ['Rango de movilidad', '°', 'integer'],
            KinesiologiaSesionRowContract::FIELD_FUERZA => ['Fuerza muscular', '/5', 'integer'],
            KinesiologiaSesionRowContract::FIELD_DOLOR => ['Dolor EVA', '/10', 'integer'],
            KinesiologiaSesionRowContract::FIELD_EJERCICIO => ['Ejercicio indicado', null, 'text'],
            KinesiologiaSesionRowContract::FIELD_SESIONES => ['Sesiones indicadas', null, 'integer'],
            KinesiologiaSesionRowContract::FIELD_EVOLUCION => ['Evolución', null, 'text'],
        ];

        $fields = [];
        $index = 0;
        foreach (KinesiologiaSesionRowContract::PROMPT_FIELDS as $promptKey) {
            $index++;
            [$fieldLabel, $unit, $format] = $labels[$promptKey] ?? [$promptKey, null, 'text'];
            $fields[] = self::field(
                $promptKey,
                $fieldLabel,
                $unit,
                $format,
                $index,
                true,
                $format === 'enum' ? KinesiologiaSesionRowContract::SIDES : null
            );
        }

        return [
            'key' => KinesiologiaSesionRowContract::MODELO,
            'label' => 'Sesión de kinesiología',
            'order' => 1,
            'fields' => $fields,
            'validation' => [
                'type' => 'at_least_one',
                'fields' => KinesiologiaSesionRowContract::PROMPT_FIELDS,
            ],
        ];
    }

    /**
     * Sesión de salud mental: campos clínicos más el puntaje de cada instrumento.
     *
     * Los instrumentos salen del catálogo de escalas; agregar uno no requiere tocar
     * esta sección.
     *
     * @return array<string, mixed>
     */
    private static function mentalHealthSection(): array
    {
        $labels = [
            SaludMentalSesionRowContract::FIELD_MOTIVO => ['Motivo de la sesión', null, 'text'],
            SaludMentalSesionRowContract::FIELD_ESTADO_MENTAL => ['Estado mental', null, 'text'],
            SaludMentalSesionRowContract::FIELD_RIESGO_SUICIDA => ['Riesgo suicida', null, 'text'],
            SaludMentalSesionRowContract::FIELD_OBJETIVOS => ['Objetivos', null, 'text'],
            SaludMentalSesionRowContract::FIELD_EVOLUCION => ['Evolución', null, 'text'],
            SaludMentalSesionRowContract::FIELD_ADHERENCIA => ['Adherencia', null, 'text'],
            SaludMentalSesionRowContract::FIELD_SUENO => ['Sueño', null, 'text'],
            SaludMentalSesionRowContract::FIELD_SUSTANCIAS => ['Consumo de sustancias', null, 'text'],
        ];

        $fields = [];
        $index = 0;
        foreach (SaludMentalSesionRowContract::SESSION_FIELDS as $promptKey) {
            $index++;
            [$fieldLabel, $unit, $format] = $labels[$promptKey] ?? [$promptKey, null, 'text'];
            $fields[] = self::field($promptKey, $fieldLabel, $unit, $format, $index, true);
        }
        foreach (MentalHealthInstrumentCatalog::all() as $instrument) {
            $index++;
            $fields[] = self::field(
                $instrument['label'],
                $instrument['label'] . ' (puntaje)',
                $instrument['unit'],
                'integer',
                $index,
                true
            );
        }

        return [
            'key' => SaludMentalSesionRowContract::MODELO,
            'label' => 'Sesión de salud mental',
            'order' => 1,
            'fields' => $fields,
            'validation' => [
                'type' => 'at_least_one',
                'fields' => SaludMentalSesionRowContract::promptFields(),
            ],
        ];
    }

    /**
     * Control prenatal: una fila por control, cada campo con su código de observación.
     *
     * @return array<string, mixed>
     */
    private static function obstetricSection(): array
    {
        $units = [
            ObstetriciaControlRowContract::FIELD_EDAD_GESTACIONAL => ['Edad gestacional', 'sem', 'decimal'],
            ObstetriciaControlRowContract::FIELD_GRAVIDEZ => ['Gravidez', null, 'integer'],
            ObstetriciaControlRowContract::FIELD_PARIDAD => ['Paridad', null, 'integer'],
            ObstetriciaControlRowContract::FIELD_ABORTOS => ['Abortos', null, 'integer'],
            ObstetriciaControlRowContract::FIELD_ALTURA_UTERINA => ['Altura uterina', 'cm', 'decimal'],
            ObstetriciaControlRowContract::FIELD_LATIDO_FETAL => ['Latido fetal', 'lpm', 'integer'],
            ObstetriciaControlRowContract::FIELD_PRESENTACION => ['Presentación fetal', null, 'text'],
            ObstetriciaControlRowContract::FIELD_FUM => ['FUM', null, 'date'],
            ObstetriciaControlRowContract::FIELD_EDEMA => ['Edema', null, 'text'],
            ObstetriciaControlRowContract::FIELD_PROTEINURIA => ['Proteinuria', null, 'text'],
            ObstetriciaControlRowContract::FIELD_MOVIMIENTOS_FETALES => ['Movimientos fetales', null, 'text'],
        ];

        $fields = [];
        $index = 0;
        foreach (ObstetriciaControlRowContract::PROMPT_FIELDS as $promptKey) {
            $index++;
            [$fieldLabel, $unit, $format] = $units[$promptKey] ?? [$promptKey, null, 'text'];
            $fields[] = self::field($promptKey, $fieldLabel, $unit, $format, $index, true);
        }

        return [
            'key' => ObstetriciaControlRowContract::MODELO,
            'label' => 'Control prenatal',
            'order' => 1,
            'fields' => $fields,
            'validation' => [
                'type' => 'at_least_one',
                'fields' => ObstetriciaControlRowContract::PROMPT_FIELDS,
            ],
        ];
    }

    /**
     * Antropometría del control pediátrico: alimenta las curvas de crecimiento.
     *
     * Las medidas son las del contrato de signos vitales, filtradas a las que el
     * gráfico consume. No identifican a la especialidad: cualquier encounter con
     * esta categoría mide crecimiento.
     *
     * @return array<string, mixed>
     */
    private static function vitalsSection(string $label, array $validationFields): array
    {
        $labels = [
            EnfermeriaSignosVitalesRowContract::FIELD_TA_SISTOLICA => ['TA sistólica', 'mmHg', 'integer'],
            EnfermeriaSignosVitalesRowContract::FIELD_TA_DIASTOLICA => ['TA diastólica', 'mmHg', 'integer'],
            EnfermeriaSignosVitalesRowContract::FIELD_FC => ['Frecuencia cardíaca', 'lpm', 'integer'],
            EnfermeriaSignosVitalesRowContract::FIELD_FR => ['Frecuencia respiratoria', 'rpm', 'integer'],
            EnfermeriaSignosVitalesRowContract::FIELD_SAT_O2 => ['Saturación O₂', '%', 'integer'],
            EnfermeriaSignosVitalesRowContract::FIELD_TEMPERATURA => ['Temperatura', '°C', 'decimal'],
            EnfermeriaSignosVitalesRowContract::FIELD_GLUCEMIA => ['Glucemia', 'mg/dL', 'integer'],
            EnfermeriaSignosVitalesRowContract::FIELD_GLASGOW => ['Glasgow', 'pts', 'integer'],
            EnfermeriaSignosVitalesRowContract::FIELD_PESO => ['Peso', 'kg', 'decimal'],
            EnfermeriaSignosVitalesRowContract::FIELD_TALLA => ['Talla', 'cm', 'decimal'],
            EnfermeriaSignosVitalesRowContract::FIELD_PERIMETRO_CEFALICO => ['Perímetro cefálico', 'cm', 'decimal'],
            EnfermeriaSignosVitalesRowContract::FIELD_CIRCUNFERENCIA_ABDOMINAL => ['Circunferencia abdominal', 'cm', 'decimal'],
        ];

        $fields = [];
        $index = 0;
        foreach (EnfermeriaSignosVitalesRowContract::PROMPT_FIELDS as $promptKey) {
            $index++;
            [$fieldLabel, $unit, $format] = $labels[$promptKey] ?? [$promptKey, null, 'decimal'];
            $fields[] = self::field($promptKey, $fieldLabel, $unit, $format, $index, true);
        }

        return [
            'key' => EnfermeriaSignosVitalesRowContract::MODELO,
            'label' => $label,
            'order' => 100,
            'fields' => $fields,
            'validation' => [
                'type' => 'at_least_one',
                'fields' => $validationFields,
            ],
        ];
    }

    /**
     * Sección de prácticas oftalmológicas: campos desde el contrato de fila.
     *
     * @return array<string, mixed>
     */
    private static function practiceSection(): array
    {
        $labels = [
            OftalmologiaPracticaRowContract::FIELD_CODIGO => ['Código', null, 'text'],
            OftalmologiaPracticaRowContract::FIELD_OJO => ['Ojo', null, 'enum'],
            OftalmologiaPracticaRowContract::FIELD_PRUEBA => ['Prueba', null, 'text'],
            OftalmologiaPracticaRowContract::FIELD_ESTADO => ['Estado', null, 'text'],
            OftalmologiaPracticaRowContract::FIELD_INFORME => ['Informe', null, 'text'],
            OftalmologiaPracticaRowContract::FIELD_TIPO => ['Tipo', null, 'text'],
        ];

        $fields = [];
        $index = 0;
        foreach (OftalmologiaPracticaRowContract::PROMPT_FIELDS as $promptKey) {
            $index++;
            [$fieldLabel, $unit, $format] = $labels[$promptKey] ?? [$promptKey, null, 'text'];
            $fields[] = self::field(
                $promptKey,
                $fieldLabel,
                $unit,
                $format,
                $index,
                true,
                $format === 'enum' ? OftalmologiaPracticaRowContract::EYE_VALUES : null
            );
        }

        return [
            'key' => OftalmologiaPracticaRowContract::MODELO,
            'label' => 'Prácticas oftalmológicas',
            'order' => 1,
            'fields' => $fields,
            'validation' => [
                'type' => 'at_least_one',
                'fields' => [
                    OftalmologiaPracticaRowContract::FIELD_CODIGO,
                    OftalmologiaPracticaRowContract::FIELD_PRUEBA,
                ],
            ],
        ];
    }

    /**
     * Sección de receta de lentes: una fila con ambos ojos.
     *
     * @return array<string, mixed>
     */
    private static function lensSection(): array
    {
        $labels = [
            OftalmologiaRecetaRowContract::FIELD_OD_ESFERA => ['OD esfera', 'D', 'decimal'],
            OftalmologiaRecetaRowContract::FIELD_OD_CILINDRO => ['OD cilindro', 'D', 'decimal'],
            OftalmologiaRecetaRowContract::FIELD_OD_EJE => ['OD eje', '°', 'integer'],
            OftalmologiaRecetaRowContract::FIELD_OD_ADICION => ['OD adición', 'D', 'decimal'],
            OftalmologiaRecetaRowContract::FIELD_OI_ESFERA => ['OI esfera', 'D', 'decimal'],
            OftalmologiaRecetaRowContract::FIELD_OI_CILINDRO => ['OI cilindro', 'D', 'decimal'],
            OftalmologiaRecetaRowContract::FIELD_OI_EJE => ['OI eje', '°', 'integer'],
            OftalmologiaRecetaRowContract::FIELD_OI_ADICION => ['OI adición', 'D', 'decimal'],
        ];

        $fields = [];
        $index = 0;
        foreach (OftalmologiaRecetaRowContract::PROMPT_FIELDS as $promptKey) {
            $index++;
            [$fieldLabel, $unit, $format] = $labels[$promptKey] ?? [$promptKey, null, 'text'];
            $fields[] = self::field($promptKey, $fieldLabel, $unit, $format, $index, true);
        }

        return [
            'key' => OftalmologiaRecetaRowContract::MODELO,
            'label' => 'Receta de lentes',
            'order' => 3,
            'fields' => $fields,
            'validation' => [
                'type' => 'at_least_one',
                'fields' => OftalmologiaRecetaRowContract::PROMPT_FIELDS,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function studySection(): array
    {
        $labels = [
            OftalmologiaEstudioRowContract::FIELD_CODIGO => 'Código',
            OftalmologiaEstudioRowContract::FIELD_OJO => 'Ojo',
            OftalmologiaEstudioRowContract::FIELD_INFORME => 'Informe',
        ];

        $fields = [];
        $index = 0;
        foreach (OftalmologiaEstudioRowContract::PROMPT_FIELDS as $promptKey) {
            $index++;
            $fields[] = self::field($promptKey, $labels[$promptKey] ?? $promptKey, null, 'text', $index, true);
        }

        return [
            'key' => 'OphthalmologyStudy',
            'label' => 'Estudios oftalmológicos',
            'order' => 2,
            'fields' => $fields,
            'validation' => [
                'type' => 'at_least_one',
                'fields' => [OftalmologiaEstudioRowContract::FIELD_CODIGO, OftalmologiaEstudioRowContract::FIELD_INFORME],
            ],
        ];
    }

    /**
     * @param list<string>|null $options
     * @return array{key: string, label: string, value: null, unit: ?string, format: string, order: int, extracted: bool, options: ?list<string>}
     */
    private static function field(
        string $key,
        string $label,
        ?string $unit,
        string $format,
        int $order,
        bool $extracted,
        ?array $options = null
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'value' => null,
            'unit' => $unit,
            'format' => $format,
            'order' => $order,
            'extracted' => $extracted,
            'options' => $options,
        ];
    }
}
