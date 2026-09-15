<?php

namespace common\components\Domain\Scheduling\Domain;

/**
 * Catálogo de dominio (ex Scheduling/metadata/consulta_async_bandeja.yaml).
 */
final class ConsultaAsyncBandejaCatalog
{
    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return [
        'version' => '1',
        'section' => [
            'title' => 'Consultas clínicas por mensaje',
            'empty_message' => 'No hay consultas clínicas por mensaje pendientes en tus servicios.',
        ],
        'staff_groups' => [
            0 => [
                'id' => 'mias',
                'title' => 'Las mías',
                'empty_message' => 'No tenés consultas tomadas en curso.',
            ],
            1 => [
                'id' => 'por_tomar',
                'title' => 'Por tomar',
                'empty_message' => 'No hay solicitudes pendientes de tomar.',
            ],
        ],
        'sla_horas_respuesta' => [
            'A' => 4,
            'B' => 12,
            'C' => 24,
            'D' => 48,
            'default' => 48,
        ],
        'status_labels' => [
            'planned' => 'Pendiente de respuesta',
            'in-progress' => 'En curso',
            'on-hold' => 'En espera',
            'finished' => 'Finalizada',
            'cancelled' => 'Cancelada',
        ],
        'patient_section' => [
            'title' => 'Consultas clínicas por mensaje',
            'empty_message' => 'No tenés consultas clínicas por mensaje activas.',
            'history_title' => 'Consultas anteriores',
            'history_empty_message' => 'No tenés consultas clínicas cerradas recientes.',
            'history_limit' => 20,
        ],
        'solicitud' => [
            'mensaje_exito_generico' => 'Recibimos tu consulta clínica por mensaje. El equipo de salud te responderá cuando pueda.',
            'mensaje_exito_renovacion' => 'Registramos tu solicitud de renovación de medicación.',
            'mensaje_exito_renovacion_cierre' => 'Un profesional la revisará y te responderá por mensaje. Podés ver el estado en Inicio → Consultas clínicas por mensaje.',
            'duplicate_renovacion_abierta' => 'Ya tenés una solicitud de renovación pendiente para este tratamiento. Revisala en Inicio → Consultas clínicas por mensaje antes de enviar otra.',
        ],
        'intake_context' => [
            'section_label' => 'Contexto de la solicitud',
            'necesidad_label' => 'Necesidad',
            'medicamentos_label' => 'Medicamentos',
            'ajuste_motivo_label' => 'Motivo del ajuste',
            'tratamiento_label' => 'Tratamiento',
            'reference_encounter_line_label' => 'Atención previa',
            'reference_encounter_action' => 'Ver atención de referencia',
            'clinical_history_action' => 'Ver historia clínica',
            'encounter_detail_title' => 'Atención de referencia',
            'care_plan_origin_title' => 'Consulta de origen',
            'encounter_detail_empty' => 'No hay un resumen publicado de esa atención.',
        ],
    ];
    }
}
