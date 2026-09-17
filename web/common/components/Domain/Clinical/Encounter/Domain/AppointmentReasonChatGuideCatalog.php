<?php

namespace common\components\Domain\Clinical\Encounter\Domain;

/**
 * Catálogo de dominio: guía del chat AppointmentReason (ex motivos_consulta_intake.yaml).
 */
final class AppointmentReasonChatGuideCatalog
{
    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return [
        'version' => 2,
        'enabled' => true,
        'presentation' => 'chat',
        'title' => 'Motivos de consulta',
        'notes_for_staff' => '',
        'chat_guide' => [
            'greeting' => 'Hola. Para preparar tu consulta, contanos:',
            'questions' => [
                0 => '¿Cuál es el principal motivo hoy?',
                1 => '¿Desde cuándo lo tenés?',
                2 => '¿Empeoró, mejoró o sigue igual?',
                3 => '¿Tomás alguna medicación o tenés alguna enfermedad conocida?',
                4 => '¿Hay algo que te preocupe especialmente (fiebre, dolor fuerte, falta de aire)?',
            ],
            'footer' => 'Podés responder todo en un solo mensaje o por audio. Hasta poco antes del horario del turno armamos un resumen para el médico.',
        ],
        'variants' => [
            0 => [
                'match' => [
                    'reserva_triage_code' => 'zona_pecho',
                ],
                'extra_questions' => [
                    0 => '¿Sentís opresión o dolor en el pecho al respirar o al hacer esfuerzo?',
                ],
            ],
            1 => [
                'match' => [
                    'reserva_triage_code' => 'zona_cabeza',
                ],
                'extra_questions' => [
                    0 => '¿Tuviste mareos, visión borrosa o el peor dolor de cabeza de tu vida?',
                ],
            ],
        ],
    ];
    }
}
