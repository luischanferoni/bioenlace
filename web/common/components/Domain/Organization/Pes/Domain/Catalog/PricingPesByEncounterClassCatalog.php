<?php

namespace common\components\Domain\Organization\Pes\Domain\Catalog;

/**
 * Catálogo único de costos y precios por volumen de atenciones.
 */
final class PricingPesByEncounterClassCatalog
{
    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return [
        'version' => 12,
        'currency' => 'USD',
        'billing_period' => 'month',
        'tax_note' => 'El total mensual incluye IVA.',
        'default_when_empty' => 'allow_all',
        /*
         * Capacidad de referencia por profesional y mes (atenciones atendidas).
         *
         * AMB jornada completa = 500: 25 atenciones/día × 20 días.
         *   - 15 min por atención ≈ 6 h 15 de consulta.
         *   - Dentro de una jornada de 8 h quedan ~1 h 45 para tareas administrativas.
         * AMB media jornada = 250: 12–13 atenciones/día × 20 días (≈ 3 h 07 de consulta).
         *
         * EMER = 700: guardia de 12 h × 15 turnos/mes a 15 min por atención (~4/hora).
         * IMP = 400: 20 pases/evoluciones por día × 20 días (internación no se mide por turno).
         */
        'reference_encounters_per_professional_month' => [
            'AMB' => 500,
            'EMER' => 700,
            'IMP' => 400,
        ],
        'cogs_assumptions' => [
            'patient_chat_messages_budget' => 10,
            'patient_chat_note' => 'Cupo de 10 msgs alrededor del turno para prompts más largos y posibles múltiples llamadas IA; no implica uso pleno en cada mensaje.',
            'motivos_audio_share_by_class' => [
                'AMB' => 0.3,
                'EMER' => 0.45,
                'IMP' => 0.5,
            ],
            'motivos_audio_note' => 'Share de atenciones con audio en motivos/notas de voz. AMB ~30%; EMER ~45% (triaje y pases); IMP ~50% (enfermería y otros roles además del médico). Resto texto.',
            'videollamada_share' => 0.4,
            'videollamada_note' => 'Planificación: ~40% de atenciones ambulatorias por videollamada cuando el add-on está contratado (12 min, 2 participantes). COGS infra+storage escalado desde el techo @ 80%.',
            'dictado_minutes' => 5,
            'dictado_usd_per_minute' => 0.0005,
            'dictado_note' => 'STT del profesional: ~5 min por atención × USD 0,0005/min = USD 0,0025. Con 4 min: USD 0,0020 (−20 % del STT, −5,4 % del COGS AMB; −USD 0,85/mes a 500 atenciones).',
        ],
        'cogs_usd_per_encounter' => [
            'patient_chat_amb' => 0.0048,
            'motivos_audio' => 0.0014,
            'motivos_audio_by_class' => [
                'AMB' => 0.0014,
                'EMER' => 0.0018,
                'IMP' => 0.0019,
            ],
            'captura_ia' => 0.0006,
            'videollamada' => 0.0044,
        ],
        'margin_on_cost_percent' => 233,
        // Ladder por clase: 0,5× · 1× · 2× · 4× · 10× · 20× · 40× · 100× · 200× la referencia.
        'attention_volume_presets' => [
            'AMB' => [
                0 => [
                    'attentions' => 250,
                    'label' => 'Media jornada · 250 atenciones / mes',
                    'hint' => '12–13 atenciones/día × 20 días (≈ 3 h 07 de consulta)',
                ],
                1 => [
                    'attentions' => 500,
                    'label' => 'Jornada completa · 500 atenciones / mes',
                    'hint' => '25 atenciones/día × 20 días (≈ 6 h 15 de consulta)',
                ],
                2 => [
                    'attentions' => 1000,
                    'label' => '2 profesionales · 1.000 atenciones / mes',
                    'hint' => null,
                ],
                3 => [
                    'attentions' => 2000,
                    'label' => '2.000 atenciones / mes',
                    'hint' => null,
                ],
                4 => [
                    'attentions' => 5000,
                    'label' => '5.000 atenciones / mes',
                    'hint' => null,
                ],
                5 => [
                    'attentions' => 10000,
                    'label' => '10.000 atenciones / mes',
                    'hint' => null,
                ],
                6 => [
                    'attentions' => 20000,
                    'label' => '20.000 atenciones / mes',
                    'hint' => null,
                ],
                7 => [
                    'attentions' => 50000,
                    'label' => '50.000 atenciones / mes',
                    'hint' => null,
                ],
                8 => [
                    'attentions' => 100000,
                    'label' => '100.000 atenciones / mes',
                    'hint' => null,
                ],
            ],
            'EMER' => [
                0 => [
                    'attentions' => 350,
                    'label' => 'Medio turno de guardia · 350 atenciones / mes',
                    'hint' => '~7 turnos de 12 h × 15 min por atención',
                ],
                1 => [
                    'attentions' => 700,
                    'label' => 'Guardia completa · 700 atenciones / mes',
                    'hint' => '15 turnos de 12 h × 15 min por atención',
                ],
                2 => [
                    'attentions' => 1400,
                    'label' => '2 profesionales · 1.400 atenciones / mes',
                    'hint' => null,
                ],
                3 => [
                    'attentions' => 2800,
                    'label' => '2.800 atenciones / mes',
                    'hint' => null,
                ],
                4 => [
                    'attentions' => 7000,
                    'label' => '7.000 atenciones / mes',
                    'hint' => null,
                ],
                5 => [
                    'attentions' => 14000,
                    'label' => '14.000 atenciones / mes',
                    'hint' => null,
                ],
                6 => [
                    'attentions' => 28000,
                    'label' => '28.000 atenciones / mes',
                    'hint' => null,
                ],
                7 => [
                    'attentions' => 70000,
                    'label' => '70.000 atenciones / mes',
                    'hint' => null,
                ],
                8 => [
                    'attentions' => 140000,
                    'label' => '140.000 atenciones / mes',
                    'hint' => null,
                ],
            ],
            'IMP' => [
                0 => [
                    'attentions' => 200,
                    'label' => 'Media jornada · 200 pases / mes',
                    'hint' => '10 pases o evoluciones/día × 20 días',
                ],
                1 => [
                    'attentions' => 400,
                    'label' => 'Jornada completa · 400 pases / mes',
                    'hint' => '20 pases o evoluciones/día × 20 días',
                ],
                2 => [
                    'attentions' => 800,
                    'label' => '2 profesionales · 800 pases / mes',
                    'hint' => null,
                ],
                3 => [
                    'attentions' => 1600,
                    'label' => '1.600 pases / mes',
                    'hint' => null,
                ],
                4 => [
                    'attentions' => 4000,
                    'label' => '4.000 pases / mes',
                    'hint' => null,
                ],
                5 => [
                    'attentions' => 8000,
                    'label' => '8.000 pases / mes',
                    'hint' => null,
                ],
                6 => [
                    'attentions' => 16000,
                    'label' => '16.000 pases / mes',
                    'hint' => null,
                ],
                7 => [
                    'attentions' => 40000,
                    'label' => '40.000 pases / mes',
                    'hint' => null,
                ],
                8 => [
                    'attentions' => 80000,
                    'label' => '80.000 pases / mes',
                    'hint' => null,
                ],
            ],
        ],
        'defaults' => [
            'initial_attentions' => [
                'AMB' => 250,
                'EMER' => 350,
                'IMP' => 200,
            ],
        ],
        'volume_discount_tiers' => [
            0 => [
                'id' => 'lista',
                'label' => 'Precio base',
                'min_attentions' => 1,
                'max_attentions' => 4999,
                'margin_on_cost_percent' => 233,
                'discount_vs_list_percent' => 0,
                'margin_after_iibb_ganancias_percent' => 49,
            ],
            1 => [
                'id' => 'mediano',
                'label' => 'Mediano',
                'min_attentions' => 5000,
                'max_attentions' => 14999,
                'margin_on_cost_percent' => 163,
                'discount_vs_list_percent' => 21,
                'margin_after_iibb_ganancias_percent' => 43.5,
            ],
            2 => [
                'id' => 'grande',
                'label' => 'Grande',
                'min_attentions' => 15000,
                'max_attentions' => 39999,
                'margin_on_cost_percent' => 134,
                'discount_vs_list_percent' => 30,
                'margin_after_iibb_ganancias_percent' => 40,
            ],
            3 => [
                'id' => 'alto_volumen',
                'label' => '40.000+ atenciones / mes',
                'min_attentions' => 40000,
                'max_attentions' => null,
                'margin_on_cost_percent' => 117,
                'discount_vs_list_percent' => 35,
                'margin_after_iibb_ganancias_percent' => 37.5,
            ],
        ],
        'cogs_source' => 'docs/negocio/costos/costos-api.md — conversación del paciente con costo AMB conservador para preprocess + guide con adjuntos e historial; motivos audio por clase; dictado incluido',
        'addons' => [
            'videollamada' => [
                'label' => 'Videollamada',
                'description' => 'Teleconsulta self-host (SFU + TURN + Track Egress + storage). La transcripción ya está cubierta por el dictado incluido.',
                'default' => false,
            ],
        ],
        'sellable_classes' => [
            'AMB' => [
                'label' => 'Ambulatorio',
                'short' => 'Consultorios y turnos',
                'includes_patient_chat' => true,
                'audio_included' => true,
                'videollamada_allowed' => true,
                'includes' => [
                    0 => 'Agenda de cupos y reserva paciente',
                    1 => 'Captura clínica AMB con dictado',
                    2 => 'App paciente (turnos)',
                    3 => 'Chat paciente alrededor del turno (hasta 10 mensajes)',
                    4 => 'Motivos de consulta (audio según uso real)',
                ],
                'product_gates' => [
                    0 => 'session_encounter_class',
                    1 => 'amb_agenda',
                    2 => 'patient_booking',
                ],
            ],
            'EMER' => [
                'label' => 'Urgencia / guardia',
                'short' => 'Tablero y horarios de guardia',
                'includes_patient_chat' => false,
                'audio_included' => true,
                'videollamada_allowed' => false,
                'includes' => [
                    0 => 'Horario de presencia EMER',
                    1 => 'Tablero de guardia',
                    2 => 'Captura clínica EMER (dictado incluido)',
                    3 => 'Motivos de consulta (audio según uso real)',
                ],
                'product_gates' => [
                    0 => 'session_encounter_class',
                    1 => 'emer_horario',
                    2 => 'emer_tablero',
                ],
            ],
            'IMP' => [
                'label' => 'Internación',
                'short' => 'Piso, camas y evoluciones',
                'includes_patient_chat' => false,
                'audio_included' => true,
                'videollamada_allowed' => false,
                'includes' => [
                    0 => 'Horario de presencia IMP',
                    1 => 'Mapa de camas',
                    2 => 'Captura / evoluciones (dictado incluido)',
                    3 => 'Motivos de consulta (audio según uso real)',
                ],
                'product_gates' => [
                    0 => 'session_encounter_class',
                    1 => 'imp_horario',
                    2 => 'imp_mapa',
                ],
            ],
        ],
        'non_sellable_default' => 'disabled',
        'simulator' => [
            'title' => 'Calculá tu licencia',
            'subtitle' => 'Elegí tipos de atención y el volumen aproximado. El dictado está incluido; la videollamada es opcional en ambulatorio.',
            'cta_label' => 'Crear cuenta',
            'cta_href' => 'alta.html',
            'footnotes' => [
            ],
        ],
    ];
    }
}
