<?php

namespace common\services\EntityIntake;

/**
 * Registry de esquemas mínimos para intake por entidad.
 *
 * La idea es que el cliente (web/app) pida:
 * - entity: nombre lógico
 * - intent: contexto (opcional)
 * y el backend devuelva un "prefill" consistente para el formulario asociado.
 */
final class EntitySchemaRegistry
{
    /**
     * @return array<string, array{required: string[], optional: string[]}>
     */
    public static function getSchema(string $entity, ?string $intent = null): ?array
    {
        $entity = trim($entity);
        $intent = $intent ? trim($intent) : null;

        // Esquemas iniciales: Internación.
        // Nota: son "campos de formulario" esperados por el cliente, no necesariamente columnas DB.
        $schemas = [
            'internacion_ingreso' => [
                'required' => ['fecha_inicio', 'hora_inicio', 'id_tipo_ingreso', 'ingresa_en', 'ingresa_con'],
                'optional' => ['id_persona', 'id_cama', 'obra_social', 'id_efector_origen', 'id_efector_derivacion', 'datos_contacto_nombre', 'datos_contacto_tel', 'situacion_al_ingresar'],
            ],
            'internacion_alta' => [
                'required' => ['fecha_fin', 'hora_fin', 'id_tipo_alta'],
                'optional' => ['observaciones_alta', 'condiciones_derivacion'],
            ],
            'internacion_cambio_cama' => [
                'required' => ['id_cama'],
                'optional' => ['motivo'],
            ],
            'internacion_medicacion' => [
                'required' => ['medicamento'],
                'optional' => ['cantidad', 'dosis_diaria', 'indicacion', 'dosis', 'frecuencia', 'via', 'observaciones'],
            ],
            'internacion_diagnostico' => [
                'required' => ['diagnostico'],
                'optional' => ['snomed', 'observaciones'],
            ],
            'internacion_practica' => [
                'required' => ['practica'],
                'optional' => ['tipo_practica', 'observaciones'],
            ],
            // Agenda quirúrgica: borrador para POST /api/v1/quirofano/cirugias
            'quirofano_cirugia' => [
                'required' => [
                    'id_quirofano_sala',
                    'id_persona',
                    'fecha_hora_inicio',
                    'fecha_hora_fin_estimada',
                ],
                'optional' => [
                    'id_seg_nivel_internacion',
                    'id_practica',
                    'procedimiento_descripcion',
                    'observaciones',
                    'estado',
                    'documento_paciente',
                    'sala_nombre',
                    'sala_codigo',
                ],
            ],
        ];

        // Permitir que el intent determine el schema por defecto.
        if ($intent && isset($schemas[$intent])) {
            return $schemas[$intent];
        }

        return $schemas[$entity] ?? null;
    }
}

