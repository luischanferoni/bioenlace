<?php

namespace common\components\Domain\Organization\Pes\Domain\Catalog;

/**
 * Catálogo de dominio (ex metadata/bioenlace/organization/pricing-pes-by-encounter-class.yaml).
 */
final class PricingPesByEncounterClassCatalog
{
    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return [
        'version' => 11,
        'currency' => 'USD',
        'billing_period' => 'month',
        'tax_note' => 'Precios en USD. No incluyen IVA.',
        'default_when_empty' => 'allow_all',
        'reference_encounters_per_professional_month' => 200,
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
        ],
        'cogs_usd_per_encounter' => [
            'patient_chat_amb' => 0.0019,
            'motivos_audio' => 0.0014,
            'motivos_audio_by_class' => [
                'AMB' => 0.0014,
                'EMER' => 0.0018,
                'IMP' => 0.0019,
            ],
            'captura_ia' => 0.0006,
            'dictado_stt' => 0.0025,
            'videollamada' => 0.0044,
        ],
        'margin_on_cost_percent' => 233,
        'attention_volume_presets' => [
            0 => [
                'attentions' => 200,
                'label' => '1 profesional',
                'hint' => null,
            ],
            1 => [
                'attentions' => 400,
                'label' => '2 profesionales',
                'hint' => null,
            ],
            2 => [
                'attentions' => 800,
                'label' => '4 profesionales',
                'hint' => null,
            ],
            3 => [
                'attentions' => 2000,
                'label' => 'Clínica chica',
                'hint' => null,
            ],
            4 => [
                'attentions' => 5000,
                'label' => 'Clínica',
                'hint' => null,
            ],
            5 => [
                'attentions' => 10000,
                'label' => 'Centro',
                'hint' => null,
            ],
            6 => [
                'attentions' => 20000,
                'label' => 'Centro grande',
                'hint' => null,
            ],
            7 => [
                'attentions' => 50000,
                'label' => 'Enterprise',
                'hint' => null,
            ],
            8 => [
                'attentions' => 100000,
                'label' => 'Enterprise+',
                'hint' => null,
            ],
        ],
        'attention_volume_scale' => [
            0 => 200,
            1 => 400,
            2 => 800,
            3 => 2000,
            4 => 5000,
            5 => 10000,
            6 => 20000,
            7 => 50000,
            8 => 100000,
        ],
        'defaults' => [
            'consultorio_attentions' => 200,
            'clinica_attentions' => 5000,
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
                'id' => 'enterprise',
                'label' => 'Enterprise',
                'min_attentions' => 40000,
                'max_attentions' => null,
                'margin_on_cost_percent' => 117,
                'discount_vs_list_percent' => 35,
                'margin_after_iibb_ganancias_percent' => 37.5,
            ],
        ],
        'cogs_source' => 'web/docs/costos/costos-api.md — context caching; chat 10 msgs (capacidad); motivos audio por clase (AMB 30% / EMER 45% / IMP 50%); dictado incluido',
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
