<?php

namespace common\components\Domain\Organization\Pes\Domain\Catalog;

/**
 * Catálogo único de costos y precios por volumen de atenciones.
 */
final class PricingPesByEncounterClassCatalog
{
    /** Addon incluido en el precio de esa clase: no se elige, se informa. */
    public const ADDON_INCLUDED = 'included';

    /** Addon disponible pero opcional: el usuario decide si lo suma. */
    public const ADDON_OPTIONAL = 'optional';

    /** Addon no disponible para esa clase. */
    public const ADDON_UNAVAILABLE = 'unavailable';

    /** El addon aplica a todos los tipos vendibles: se elige una sola vez. */
    public const ADDON_SCOPE_ALL = 'all';

    /** El addon aplica solo a algunos tipos: se elige por tipo de atención. */
    public const ADDON_SCOPE_PER_CLASS = 'per_class';

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return [
        'version' => 16,
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
            'infra_usd_per_professional_month' => 0.03,
            'infra_note' => 'App + BD + backups + monitoreo, por profesional/mes. Base: 2–3 VPS tipo KVM2 (Hostinger ~ARS 44.000/mes c/u; el tope ~ARS 145.000) ≈ USD 90–160/mes en total, compartidos entre todos los tenants; a 5.000 profesionales son USD 0,02–0,03. Se reparte entre las atenciones de referencia de cada clase. No incluye análisis de imágenes ni storage de video (este último va en el add-on de videollamada). Medir con la factura real.',
            'patient_chat_messages_budget' => 10,
            'patient_chat_note' => 'COGS de chat atribuido solo a AMB: en EMER/IMP el paciente no opera el asistente (triage, tablero e ingreso son staff), así que no se asigna consumo de chat por atención. Cupo de 10 msgs alrededor del turno para prompts más largos y posibles múltiples llamadas IA; no implica uso pleno en cada mensaje.',
            'motivos_note' => 'Motivos de consulta sin audio ni STT: cuestionario con opciones, triage de reserva y mensajes al asistente; 1 resumen + insights por consulta. Mismo costo en AMB/EMER/IMP.',
            'videollamada_share' => 0.4,
            'videollamada_note' => 'Planificación: ~40% de atenciones ambulatorias por videollamada cuando el add-on está contratado (12 min, 2 participantes). COGS infra+storage escalado desde el techo @ 80%.',
            'dictado_minutes' => 5,
            'dictado_effective_usd_per_minute' => 0.0005,
            'dictado_note' => 'STT del profesional: ~5 min por atención × USD 0,0005/min = USD 0,0025. Con 4 min: USD 0,0020 (−20 % del STT, −5,4 % del COGS AMB; −USD 0,85/mes a 500 atenciones).',
            'whatsapp_service_messages_per_encounter' => 1.0,
            'whatsapp_service_usd_per_message' => 0.026,
            'whatsapp_didit_links_per_encounter' => 0.01,
            'whatsapp_didit_link_usd' => 0.33,
            'whatsapp_note' => 'Solo reactivo: 1 mensaje service por atención + vinculación Didit amortizada (1 cada 100 atenciones). Sin plantillas proactivas. Las escrituras se derivan a la app, que hace el step-up con huella/passkey; por eso no hay Didit por escritura.',
        ],
        'cogs_usd_per_encounter' => [
            'patient_chat_amb' => 0.0048,
            'motivos' => 0.0006,
            'motivos_by_class' => [
                'AMB' => 0.0006,
                'EMER' => 0.0006,
                'IMP' => 0.0006,
            ],
            'captura_ia' => 0.0007,
            'videollamada' => 0.0044,
            'whatsapp' => 0.0293,
        ],
        'margin_on_cost_percent' => 233,
        /*
         * Margen propio de los add-ons opcionales (p. ej. videollamada), separado
         * del margen de la licencia para poder abaratarlos sin tocar el precio
         * base. Usar el mismo valor = mismo criterio que la licencia.
         */
        'addon_margin_on_cost_percent' => 233,
        /*
         * Supuestos fiscales (Argentina) de los que se DERIVA el margen después
         * de impuestos. No escribir ese margen a mano en los tramos: se calcula
         * en `PricingPesByEncounterClassMetadata::afterTaxMarginPercent()`.
         *
         * iibb_rate_percent: Santiago del Estero, servicios en general
         *   (valor de planificación; validar contra la Ley Impositiva vigente y
         *   el coeficiente del Convenio Multilateral si se factura fuera).
         * income_tax_rate_percent: tramo marginal superior de la escala
         *   25/30/35 % (Ley 27.743). Criterio conservador a escala; con
         *   utilidades bajas la tasa efectiva es menor.
         * bank_debit_credit_tax_percent: IDCB 0,6 % sobre créditos y débitos.
         *   Parte puede computarse como pago a cuenta; se modela completo.
         *
         * Las percepciones/retenciones de IIBB de clientes no cambian el
         * impuesto total (son caja y saldo a favor), por eso no entran acá.
         */
        'tax_assumptions' => [
            'jurisdiction' => 'Santiago del Estero (AR)',
            'iibb_rate_percent' => 4.0,
            'income_tax_rate_percent' => 35.0,
            'bank_debit_credit_tax_percent' => 0.6,
        ],
        /*
         * Escala de volumen: cada escalón es un múltiplo de la capacidad de
         * referencia de la clase. No se escriben volúmenes acá: se calculan en
         * `PricingPesByEncounterClassMetadata::attentionVolumePresets()`.
         */
        'attention_volume_multipliers' => [0.5, 1, 2, 4, 10, 20, 40, 100, 200],
        /*
         * Copy de los escalones que tienen nombre propio, por clase. `unit` se
         * usa para armar el label con el volumen calculado; `steps` se indexa por
         * multiplicador (0.5, 1, ...) para no depender del orden de la escala.
         */
        'attention_volume_copy' => [
            'AMB' => [
                'unit' => 'atenciones',
                'steps' => [
                    '0.5' => [
                        'prefix' => 'Media jornada',
                        'hint' => '~12–13 atenciones/día',
                    ],
                    '1' => [
                        'prefix' => 'Jornada completa',
                        'hint' => '~25 atenciones/día',
                    ],
                ],
            ],
            'EMER' => [
                'unit' => 'atenciones',
                'steps' => [
                    '0.5' => [
                        'prefix' => 'Medio turno de guardia',
                        'hint' => '~7 turnos de 12 h',
                    ],
                    '1' => [
                        'prefix' => 'Guardia completa',
                        'hint' => '~15 turnos de 12 h',
                    ],
                ],
            ],
            'IMP' => [
                'unit' => 'pases',
                'steps' => [
                    '0.5' => [
                        'prefix' => 'Media jornada',
                        'hint' => '~10 evoluciones/día',
                    ],
                    '1' => [
                        'prefix' => 'Jornada completa',
                        'hint' => '~20 evoluciones/día',
                    ],
                ],
            ],
        ],
        /*
         * Volumen inicial de la cotización: media jornada. Se deriva de la
         * capacidad de referencia, no se declara por clase.
         */
        'defaults' => [
            'initial_attentions_multiplier' => 0.5,
        ],
        'volume_discount_tiers' => [
            0 => [
                'id' => 'lista',
                'label' => 'Precio base',
                'min_attentions' => 1,
                'max_attentions' => 4999,
                'margin_on_cost_percent' => 233,
                'discount_vs_list_percent' => 0,
            ],
            1 => [
                'id' => 'mediano',
                'label' => 'Mediano',
                'min_attentions' => 5000,
                'max_attentions' => 14999,
                'margin_on_cost_percent' => 163,
                'discount_vs_list_percent' => 21,
            ],
            2 => [
                'id' => 'grande',
                'label' => 'Grande',
                'min_attentions' => 15000,
                'max_attentions' => 39999,
                'margin_on_cost_percent' => 134,
                'discount_vs_list_percent' => 30,
            ],
            3 => [
                'id' => 'alto_volumen',
                'label' => '40.000+ atenciones / mes',
                'min_attentions' => 40000,
                'max_attentions' => null,
                'margin_on_cost_percent' => 117,
                'discount_vs_list_percent' => 35,
            ],
        ],
        'cogs_source' => 'docs/negocio/costos/costos-api.md — conversación del paciente con costo AMB conservador para preprocess + guide con adjuntos e historial; motivos sin audio (cuestionario pre-turno); dictado incluido',
        /*
         * Addons (funcionalidades) por clase de atención. Cada addon declara su
         * estado por clase:
         *   included    → viene incluido en el precio (se informa dónde aplica)
         *   optional    → el usuario lo puede seleccionar (checkbox) e impacta el precio
         *   unavailable → no aplica a esa clase
         * `plan_field` es el campo del contrato `POST /licencia/cotizar` (solo
         * addons opcionales que se persisten como entitlement).
         *
         * El ámbito (`scope`, ver `PricingPesByEncounterClassMetadata::addonScope()`)
         * se deriva de esta matriz: si todos los tipos vendibles comparten el mismo
         * estado elegible el addon se elige una sola vez (`all`); si difiere, aplica
         * a algunos (`per_class`). No se declara a mano para no duplicar datos.
         *
         * El impacto en el precio de un addon se declara una sola vez en
         * `cogs_usd_per_encounter[<id del addon>]`; sin entrada no cambia el precio.
         */
        'addons' => [
            'videollamada' => [
                'label' => 'Videollamada',
                'unavailable_label' => 'Sin videollamada',
                'description' => 'Teleconsulta online. La transcripción ya está cubierta por el dictado incluido.',
                'plan_field' => 'videollamada_permitida',
                'classes' => [
                    'AMB' => self::ADDON_OPTIONAL,
                    'EMER' => self::ADDON_UNAVAILABLE,
                    'IMP' => self::ADDON_UNAVAILABLE,
                ],
            ],
            /*'whatsapp' => [
                'label' => 'WhatsApp con el paciente',
                'unavailable_label' => 'Sin WhatsApp',
                'description' => 'El paciente consulta turnos e información por WhatsApp. Las altas, bajas y modificaciones se confirman en la app con huella o passkey.',
                'plan_field' => 'whatsapp_permitido',
                'classes' => [
                    'AMB' => self::ADDON_OPTIONAL,
                    'EMER' => self::ADDON_OPTIONAL,
                    'IMP' => self::ADDON_OPTIONAL,
                ],
            ],*/
            'dictado' => [
                'label' => 'Informe clínico dictado por voz',
                'included_label' => 'Informe clínico con dictado por voz',
                'unavailable_label' => 'Sin dictado por voz',
                'description' => 'Dictás el informe y se transcribe en la captura clínica.',
                'plan_field' => 'dictado_incluido',
                'classes' => [
                    'AMB' => self::ADDON_INCLUDED,
                    'EMER' => self::ADDON_INCLUDED,
                    'IMP' => self::ADDON_INCLUDED,
                ],
            ],            
            'autogestion_paciente' => [
                'label' => 'Autogestión del paciente',
                'included_label' => 'Autogestión del paciente desde la app',
                'unavailable_label' => 'Sin autogestión del paciente',
                'description' => 'El paciente se autogestiona con la ayuda del asistente de IA.',
                'classes' => [
                    'AMB' => self::ADDON_INCLUDED,
                    'EMER' => self::ADDON_UNAVAILABLE,
                    'IMP' => self::ADDON_UNAVAILABLE,
                ],
            ],
            'autogestion_personal' => [
                'label' => 'Autogestión del personal de salud',
                'included_label' => 'Autogestión del personal de salud',
                'unavailable_label' => 'Sin autogestión del personal de salud',
                'description' => 'Cada profesional se autogestiona con la ayuda del asistente de IA.',
                'classes' => [
                    'AMB' => self::ADDON_INCLUDED,
                    'EMER' => self::ADDON_INCLUDED,
                    'IMP' => self::ADDON_INCLUDED,
                ],
            ],
            'consultas_flash' => [
                'label' => 'Consultas flash',
                'included_label' => 'Consultas flash',
                'unavailable_label' => 'Sin consultas flash',
                'description' => 'El paciente puede enviar mensajes preguntando sobre tratamientos activos y sobre su última atención.',
                'classes' => [
                    'AMB' => self::ADDON_INCLUDED,
                    'EMER' => self::ADDON_INCLUDED,
                    'IMP' => self::ADDON_INCLUDED,
                ],
            ],
            'agente_anti_ausencias' => [
                'label' => 'Agente anti ausencias',
                'included_label' => 'Agente anti ausencias',
                'unavailable_label' => 'Sin agente anti ausencias',
                'description' => 'Algoritmos que ayudan a prevenir las ausencias de los pacientes. Buscan ocupar los turnos vacíos y mejorar la eficiencia de la agenda.',
                'classes' => [
                    'AMB' => self::ADDON_INCLUDED,
                    'EMER' => self::ADDON_INCLUDED,
                    'IMP' => self::ADDON_INCLUDED,
                ],
            ],
            'seguimiento_post_atencion' => [
                'label' => 'Seguimiento post atención',
                'included_label' => 'Seguimiento post atención',
                'unavailable_label' => 'Sin seguimiento post atención',
                'description' => 'Sistema de seguimiento después de la atención médica o alta de internación para mejorar la experiencia del paciente.',
                'classes' => [
                    'AMB' => self::ADDON_INCLUDED,
                    'EMER' => self::ADDON_INCLUDED,
                    'IMP' => self::ADDON_INCLUDED,
                ],
            ],            
        ],
        'sellable_classes' => [
            'AMB' => [
                'label' => 'AMBULATORIO',
                'short' => '',
                'includes_patient_chat' => true,
                'product_gates' => [
                    0 => 'session_encounter_class',
                    1 => 'amb_agenda',
                    2 => 'patient_booking',
                ],
            ],
            'EMER' => [
                'label' => 'URGENCIA / GUARDIA',
                'short' => '',
                'includes_patient_chat' => false,
                'product_gates' => [
                    0 => 'session_encounter_class',
                    1 => 'emer_horario',
                    2 => 'emer_tablero',
                ],
            ],
            'IMP' => [
                'label' => 'INTERNACIÓN',
                'short' => '',
                'includes_patient_chat' => false,
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
            'subtitle' => 'Elegí tipos de atención y el volumen aproximado. Las funcionalidades incluidas y los add-ons se detallan debajo.',
            'cta_label' => 'Crear cuenta',
            'cta_href' => 'alta.html',
            'footnotes' => [
            ],
        ],
    ];
    }
}
