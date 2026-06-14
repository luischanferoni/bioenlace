<?php

namespace common\components\Domain\Scheduling\Assistant;

use common\components\Domain\Organization\Service\ProfesionalEfectorServicio\ProfesionalEnEfectorListadoUiService;
use common\components\Domain\Scheduling\Service\TurnoSlotOfferService;

/**
 * Opciones estáticas de selects UI JSON de turnos (preload sin endpoint).
 */
final class SchedulingUiSelectOptionsService
{
    /**
     * @param mixed $filter
     * @param array<string, mixed> $params
     * @param array<string, mixed> $optionConfig
     * @return list<array<string, mixed>>
     */
    public static function resolveProfesionales(string $sourceKey, $filter, array $params, array $optionConfig): array
    {
        $idEfector = self::intParam($params, ['id_efector', 'idEfector']);
        $idServicio = self::intParam($params, ['id_servicio', 'id_servicio_asignado', 'idServicio']);
        if ($idEfector <= 0 || $idServicio <= 0) {
            return [];
        }

        $filters = [
            'id_efector' => $idEfector,
            'id_servicio' => $idServicio,
            'limit' => 200,
        ];
        if (!empty($params['tipo_atencion'])) {
            $filters['tipo_atencion'] = (string) $params['tipo_atencion'];
        }
        if ($filter === 'efector_rrhh' || $sourceKey === 'profesional-efector-servicio') {
            // Mismo listado PES por efector+servicio (filtro legacy del descriptor JSON).
        }

        try {
            $rows = ProfesionalEnEfectorListadoUiService::autocompletePorEfectorServicio('', $filters);
        } catch (\InvalidArgumentException $e) {
            return [];
        }

        $options = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = trim((string) ($row['id'] ?? ''));
            $text = trim((string) ($row['text'] ?? ''));
            if ($id === '') {
                continue;
            }
            $options[] = [
                'id' => $id,
                'name' => $text !== '' ? $text : ('PES #' . $id),
                'text' => $text !== '' ? $text : ('PES #' . $id),
                'id_profesional_efector_servicio' => (int) ($row['id_profesional_efector_servicio'] ?? $id),
            ];
        }

        return $options;
    }

    /**
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    public static function resolveSlotsDisponiblesPaciente(array $params): array
    {
        $idServicio = self::intParam($params, ['id_servicio', 'id_servicio_asignado']);
        $idEfector = self::intParam($params, ['id_efector', 'idEfector']);
        $idPes = self::intParam($params, ['id_profesional_efector_servicio', 'idProfesionalEfectorServicio']);
        if ($idServicio <= 0 || $idEfector <= 0 || $idPes <= 0) {
            return [];
        }

        $defaults = TurnoSlotOfferService::leerDefaultsTurnosPaciente();
        $criteria = [
            'id_servicio' => $idServicio,
            'id_efector' => $idEfector,
            'id_profesional_efector_servicio' => $idPes,
            'fecha_desde' => date('Y-m-d'),
            'min_minutos_desde_ahora' => $defaults['min_minutos_desde_ahora'],
        ];

        $fecha = isset($params['fecha']) ? trim((string) $params['fecha']) : '';
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) === 1) {
            $criteria['fecha_desde'] = $fecha;
        }

        $grouped = TurnoSlotOfferService::buildGrouped(
            $criteria,
            $defaults['limite'],
            $defaults['max_dias'],
            $defaults['franja_tarde_desde']
        );

        $options = [];
        foreach ($grouped['por_dia'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $fechaDia = isset($row['fecha']) ? (string) $row['fecha'] : '';
            if ($fechaDia === '') {
                continue;
            }
            foreach (['manana', 'tarde'] as $franja) {
                $slots = isset($row[$franja]) && is_array($row[$franja]) ? $row[$franja] : [];
                foreach ($slots as $slot) {
                    if (!is_array($slot)) {
                        continue;
                    }
                    $hora = trim((string) ($slot['hora'] ?? ''));
                    $pesSlot = (int) ($slot['id_profesional_efector_servicio'] ?? $idPes);
                    if ($hora === '' || $pesSlot <= 0) {
                        continue;
                    }
                    $slotId = 'pes:' . $pesSlot . '|' . $fechaDia . '|' . $hora;
                    $options[] = [
                        'id' => $slotId,
                        'name' => $hora,
                        'text' => $hora,
                        'value' => $slotId,
                        'label' => $hora,
                        'meta' => [
                            'fecha' => $fechaDia,
                            'hora' => $hora,
                            'id_profesional_efector_servicio' => $pesSlot,
                            'franja' => $franja,
                        ],
                    ];
                }
            }
        }

        return $options;
    }

    /**
     * @param array<string, mixed> $params
     * @param list<string> $keys
     */
    private static function intParam(array $params, array $keys): int
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $params) || $params[$key] === null || $params[$key] === '') {
                continue;
            }

            return (int) $params[$key];
        }

        return 0;
    }
}
