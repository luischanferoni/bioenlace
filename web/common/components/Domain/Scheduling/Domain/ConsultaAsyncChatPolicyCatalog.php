<?php

namespace common\components\Domain\Scheduling\Domain;

/**
 * Catálogo de dominio (ex Scheduling/metadata/consulta_async_chat_policy.yaml).
 */
final class ConsultaAsyncChatPolicyCatalog
{
    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return [
        'version' => '1',
        'conversation_modes' => [
            'structured' => [
                'medicacion_operaciones' => [
                    0 => 'renovacion',
                    1 => 'ajuste',
                ],
                'patient_composer' => false,
                'patient_upload' => false,
                'staff_composer' => false,
                'composer_hint' => 'Tu solicitud ya fue registrada. Esperá la resolución del profesional; no hace falta enviar más mensajes.',
                'staff_composer_hint' => '',
            ],
            'conversational' => [
                'patient_composer' => true,
                'patient_upload' => true,
                'staff_composer' => true,
            ],
        ],
        'attachments' => [
            'allowed_message_types' => [
                0 => 'audio',
                1 => 'documento',
            ],
            'patient_allowed_message_types' => [
                0 => 'imagen',
            ],
            'image' => [
                'extensions' => [
                    0 => 'jpg',
                    1 => 'jpeg',
                    2 => 'png',
                    3 => 'webp',
                    4 => 'heic',
                ],
                'mime_types' => [
                    0 => 'image/jpeg',
                    1 => 'image/png',
                    2 => 'image/webp',
                    3 => 'image/heic',
                ],
                'max_bytes' => 5242880,
            ],
            'document' => [
                'extensions' => [
                    0 => 'pdf',
                ],
                'mime_types' => [
                    0 => 'application/pdf',
                ],
                'max_bytes' => 10485760,
            ],
            'audio' => [
                'extensions' => [
                    0 => 'm4a',
                    1 => 'mp3',
                    2 => 'webm',
                    3 => 'ogg',
                    4 => 'wav',
                ],
                'mime_types' => [
                    0 => 'audio/webm',
                    1 => 'audio/mp4',
                    2 => 'audio/mpeg',
                    3 => 'audio/ogg',
                    4 => 'audio/wav',
                ],
                'max_bytes' => 5242880,
            ],
        ],
        'limits' => [
            'conversational' => [
                'max_patient_messages_while_planned_without_staff' => 3,
                'max_patient_messages_total' => 20,
                'window_days' => 7,
                'auto_close_resolution' => 'limite_conversacion',
            ],
            'rate_limit' => [
                'window_days' => 30,
                'max_solicitudes_medicacion_por_plan' => 2,
            ],
        ],
        'duplicate' => [
            'block_open_medicacion_por_plan' => true,
            'block_renovacion_si_ajuste_abierto' => true,
            'messages' => [
                'open_renovacion' => 'Ya tenés una solicitud de renovación pendiente para este tratamiento. Revisala en Inicio → Consultas clínicas por mensaje.',
                'open_ajuste' => 'Ya tenés una solicitud de ajuste pendiente para este tratamiento. Revisala en Inicio → Consultas clínicas por mensaje.',
                'open_medicacion' => 'Ya tenés una consulta sobre medicación pendiente para este tratamiento. Esperá la respuesta antes de enviar otra.',
                'renovacion_con_ajuste_pendiente' => 'Tenés un ajuste de medicación pendiente. Cuando el profesional lo resuelva, podés solicitar la renovación de la medicación actualizada.',
                'rate_limit_medicacion' => 'Superaste el límite de solicitudes de medicación para este tratamiento en el período indicado. Pedí un turno de control si necesitás atención.',
            ],
        ],
        'cancel' => [
            'allowed_statuses' => [
                0 => 'planned',
            ],
            'require_no_staff_assigned' => true,
            'require_no_staff_message' => true,
            'message_exito' => 'Retiramos tu solicitud. Podés iniciar una nueva consulta cuando lo necesites.',
        ],
        'resolutions' => [
            'medicacion_renovada' => [
                'label' => 'Medicación renovada',
                'action_label' => 'Renovar medicación',
                'notify_patient' => true,
                'permite_renovacion_posterior' => false,
            ],
            'medicacion_ajustada' => [
                'label' => 'Medicación ajustada',
                'action_label' => 'Ajustar medicación',
                'notify_patient' => true,
                'permite_renovacion_posterior' => true,
            ],
            'medicacion_no_indicada' => [
                'label' => 'No corresponde renovación ni ajuste',
                'action_label' => 'Indicar que no corresponde',
                'require_note' => true,
                'notify_patient' => true,
            ],
            'requiere_control_presencial' => [
                'label' => 'Requiere control presencial',
                'action_label' => 'Indicar control presencial',
                'require_note' => false,
                'notify_patient' => true,
                'suggest_turno' => true,
            ],
            'consulta_resuelta' => [
                'label' => 'Consulta resuelta por mensaje',
                'action_label' => 'Marcar como resuelta',
                'notify_patient' => true,
            ],
            'limite_conversacion' => [
                'label' => 'Límite de mensajes alcanzado',
                'notify_patient' => true,
                'suggest_turno' => true,
            ],
            'cancelada_paciente' => [
                'label' => 'Cancelada por el paciente',
                'notify_patient' => false,
            ],
        ],
        'solicitud_categorias' => [
            'renovacion_medicacion' => [
                'label' => 'Solicitud de renovación de medicación',
                'message_type_legacy' => 'solicitud_renovacion',
            ],
            'ajuste_medicacion' => [
                'label' => 'Solicitud de ajuste de medicación',
                'message_type_legacy' => 'solicitud_ajuste',
            ],
            'consulta_evolucion' => [
                'label' => 'Consulta o evolución',
                'message_type_legacy' => 'solicitud_consulta',
            ],
        ],
        'solicitud_categoria_aliases' => [
            'renovacion' => 'renovacion_medicacion',
            'renovacion_medicacion' => 'renovacion_medicacion',
            'ajuste' => 'ajuste_medicacion',
            'ajuste_medicacion' => 'ajuste_medicacion',
            'renovar_medicacion' => 'renovacion_medicacion',
            'solicitar_ajuste' => 'ajuste_medicacion',
            'contar_evolucion' => 'consulta_evolucion',
            'duda' => 'consulta_evolucion',
            'consulta_general' => 'consulta_evolucion',
            'consulta_evolucion' => 'consulta_evolucion',
            'seguimiento' => 'consulta_evolucion',
            'seguimiento_consulta_previa' => 'consulta_evolucion',
            'default' => 'consulta_evolucion',
        ],
        'solicitud_message_types' => [
            'renovacion' => 'solicitud_renovacion',
            'ajuste' => 'solicitud_ajuste',
            'default' => 'solicitud_consulta',
        ],
        'system_messages' => [
            'solicitud_tomada' => 'Un profesional tomó tu solicitud y te responderá por este chat.',
            'solicitud_tomada_structured' => 'Un profesional está revisando tu solicitud y la resolverá en breve.',
            'solicitud_cerrada' => 'Cerramos esta consulta: {resolution_label}.{note_suffix}',
            'solicitud_cancelada' => 'Retiraste esta solicitud. Podés iniciar una nueva consulta cuando lo necesites.',
            'limite_conversacion' => 'Alcanzaste el límite de mensajes para esta consulta. Cerramos el chat; si necesitás seguir, solicitá un turno presencial o de control.',
            'limite_conversacion_turno' => 'Podés reservar un turno desde el asistente o la sección Turnos.',
        ],
        'staff_messages' => [
            'tomada_conversational' => 'Solicitud asignada. Podés responder por mensaje.',
            'tomada_structured' => 'Solicitud asignada. Elegí una resolución para el paciente.',
        ],
        'system_message_staff_view' => [
            'solicitud_tomada' => 'tomada_conversational',
            'solicitud_tomada_structured' => 'tomada_structured',
        ],
    ];
    }
}
