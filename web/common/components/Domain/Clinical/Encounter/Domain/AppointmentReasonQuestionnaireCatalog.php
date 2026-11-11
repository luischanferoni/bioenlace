<?php

namespace common\components\Domain\Clinical\Encounter\Domain;

/**
 * Catálogo de dominio: cuestionario pre-turno de motivos de consulta.
 *
 * Declara el vocabulario y los textos que ve el paciente. La selección según el triage de la
 * reserva y la persistencia de respuestas viven en Application.
 */
final class AppointmentReasonQuestionnaireCatalog
{
    /** Pregunta del paso final: único texto libre del cuestionario. */
    public const CLOSING_ID = 'cierre_libre';

    /** Opción reservada que habilita el texto corto de cada pregunta. */
    public const OTHER_OPTION_ID = 'otra';

    /** Tope del texto libre final. */
    public const CLOSING_MAX_LENGTH = 320;

    /** Tope por defecto del texto de la opción «Otra». */
    public const OTHER_MAX_LENGTH = 120;

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return [
            'version' => 1,
            'enabled' => true,
            'title' => 'Prepará tu consulta',
            'intro' => 'Te hago unas preguntas rápidas para que el profesional llegue con contexto. Elegí una opción en cada paso.',
            'closing' => [
                'id' => self::CLOSING_ID,
                'text' => '¿Querés sumar algo más?',
                'hint' => 'Contanos lo que no hayas podido responder en las opciones anteriores.',
                'max_length' => self::CLOSING_MAX_LENGTH,
            ],
            'questions' => [
                [
                    'id' => 'inicio',
                    'text' => '¿Desde cuándo lo tenés?',
                    'allow_other' => true,
                    'options' => [
                        ['id' => 'hoy', 'label' => 'Desde hoy'],
                        ['id' => 'dias', 'label' => 'Hace 1 a 3 días'],
                        ['id' => 'semana', 'label' => 'Esta semana'],
                        ['id' => 'mas_semana', 'label' => 'Hace más de una semana'],
                        ['id' => 'mas_mes', 'label' => 'Hace más de un mes'],
                    ],
                ],
                [
                    'id' => 'evolucion',
                    'text' => '¿Cómo viene evolucionando?',
                    'skip_when_triage_fields' => ['triage_evolucion'],
                    'options' => [
                        ['id' => 'mejoro', 'label' => 'Mejoró'],
                        ['id' => 'igual', 'label' => 'Sigue igual'],
                        ['id' => 'empeoro', 'label' => 'Empeoró'],
                        ['id' => 'va_y_viene', 'label' => 'Va y viene'],
                    ],
                ],
                [
                    'id' => 'intensidad',
                    'text' => '¿Cuánto te molesta hoy?',
                    'options' => [
                        ['id' => 'leve', 'label' => 'Leve'],
                        ['id' => 'moderado', 'label' => 'Moderado'],
                        ['id' => 'intenso', 'label' => 'Intenso'],
                        ['id' => 'muy_intenso', 'label' => 'Muy intenso'],
                    ],
                ],
                [
                    'id' => 'alarma',
                    'text' => '¿Tenés alguno de estos síntomas ahora?',
                    'skip_when_triage_fields' => ['triage_alarmas'],
                    'options' => [
                        ['id' => 'falta_aire', 'label' => 'Falta de aire'],
                        ['id' => 'fiebre_alta', 'label' => 'Fiebre alta'],
                        ['id' => 'dolor_pecho', 'label' => 'Dolor en el pecho'],
                        ['id' => 'desmayo', 'label' => 'Desmayo o mareo fuerte'],
                        ['id' => 'ninguno', 'label' => 'Ninguno de estos'],
                    ],
                ],
                [
                    'id' => 'medicacion',
                    'text' => '¿Estás tomando alguna medicación?',
                    'allow_other' => true,
                    'options' => [
                        ['id' => 'para_esto', 'label' => 'Sí, para esto'],
                        ['id' => 'otra_cosa', 'label' => 'Sí, para otra cosa'],
                        ['id' => 'ninguna', 'label' => 'No tomo nada'],
                    ],
                ],
                [
                    'id' => 'antecedentes',
                    'text' => '¿Tenés alguna enfermedad o condición diagnosticada?',
                    'allow_other' => true,
                    'options' => [
                        ['id' => 'si', 'label' => 'Sí'],
                        ['id' => 'no', 'label' => 'No'],
                        ['id' => 'no_se', 'label' => 'No estoy seguro'],
                    ],
                ],
                [
                    'id' => 'estudios',
                    'text' => '¿Tenés estudios o resultados recientes para mostrar?',
                    'options' => [
                        ['id' => 'si', 'label' => 'Sí'],
                        ['id' => 'no', 'label' => 'No'],
                    ],
                ],
                [
                    'id' => 'expectativa',
                    'text' => '¿Qué esperás de la consulta?',
                    'allow_other' => true,
                    'options' => [
                        ['id' => 'revision', 'label' => 'Que me revise'],
                        ['id' => 'resultados', 'label' => 'Revisar resultados de estudios'],
                        ['id' => 'indicacion', 'label' => 'Receta o indicación'],
                    ],
                ],
                [
                    'id' => 'pecho_esfuerzo',
                    'text' => '¿El dolor o la falta de aire empeoran al hacer esfuerzo?',
                    'only_when' => ['triage_zona' => ['zona_pecho']],
                    'options' => [
                        ['id' => 'si', 'label' => 'Sí'],
                        ['id' => 'no', 'label' => 'No'],
                        ['id' => 'no_se', 'label' => 'No sé'],
                    ],
                ],
                [
                    'id' => 'cabeza_alarma',
                    'text' => '¿Tuviste mareos, visión borrosa o el peor dolor de cabeza de tu vida?',
                    'only_when' => ['triage_zona' => ['zona_cabeza_cuello']],
                    'options' => [
                        ['id' => 'si', 'label' => 'Sí'],
                        ['id' => 'no', 'label' => 'No'],
                        ['id' => 'no_se', 'label' => 'No sé'],
                    ],
                ],
                [
                    'id' => 'abdomen_digestivo',
                    'text' => '¿Tenés náuseas, vómitos o diarrea?',
                    'only_when' => ['triage_zona' => ['zona_abdomen']],
                    'options' => [
                        ['id' => 'si', 'label' => 'Sí'],
                        ['id' => 'no', 'label' => 'No'],
                    ],
                ],
                [
                    'id' => 'musculo_trauma',
                    'text' => '¿Empezó después de un golpe, caída o esfuerzo?',
                    'only_when' => ['triage_zona' => ['zona_musculoesqueletico']],
                    'options' => [
                        ['id' => 'si', 'label' => 'Sí'],
                        ['id' => 'no', 'label' => 'No'],
                        ['id' => 'no_se', 'label' => 'No sé'],
                    ],
                ],
                [
                    'id' => 'general_fiebre',
                    'text' => '¿Tuviste fiebre en los últimos días?',
                    'only_when' => ['triage_zona' => ['zona_general']],
                    'options' => [
                        ['id' => 'si', 'label' => 'Sí'],
                        ['id' => 'no', 'label' => 'No'],
                        ['id' => 'no_medi', 'label' => 'No me la medí'],
                    ],
                ],
                [
                    'id' => 'estudio_orden',
                    'text' => '¿Ya tenés la orden o el pedido del estudio?',
                    'only_when' => ['triage_raiz' => ['estudio_pedido']],
                    'options' => [
                        ['id' => 'si', 'label' => 'Sí'],
                        ['id' => 'no', 'label' => 'No'],
                    ],
                ],
                [
                    'id' => 'seguimiento_cambios',
                    'text' => '¿Hubo cambios en tu tratamiento o en cómo te sentís desde la última consulta?',
                    'only_when' => ['triage_raiz' => ['seguimiento_cronico']],
                    'options' => [
                        ['id' => 'si', 'label' => 'Sí'],
                        ['id' => 'no', 'label' => 'No'],
                        ['id' => 'no_se', 'label' => 'No estoy seguro'],
                    ],
                ],
            ],
        ];
    }
}
