<?php

namespace common\components\Domain\Scheduling\Assistant;

use common\components\Domain\Scheduling\Service\TurnoReservaSlotService;
use common\components\Platform\Ui\UiScreenParamsExpanderInterface;
use common\models\ProfesionalEfectorServicio;

/**
 * Expande `slot_id` de turnos a fecha, hora, PES, efector y servicio.
 */
final class SchedulingUiScreenParamsExpander implements UiScreenParamsExpanderInterface
{
    public static function providerKey(): string
    {
        return 'scheduling';
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public static function expand(string $entity, string $action, array $params): array
    {
        $slotId = $params['slot_id'] ?? null;
        if (!is_string($slotId) || trim($slotId) === '') {
            return $params;
        }

        $parsed = TurnoReservaSlotService::parseSlotId($slotId);
        if ($parsed === null) {
            return $params;
        }

        $pesId = (int) $parsed['id_profesional_efector_servicio'];
        if (
            $pesId > 0
            && (!isset($params['id_profesional_efector_servicio']) || $params['id_profesional_efector_servicio'] === '' || $params['id_profesional_efector_servicio'] === null)
        ) {
            $params['id_profesional_efector_servicio'] = $pesId;
        }

        $fecha = (string) $parsed['fecha'];
        $hora = (string) $parsed['hora'];
        if ($fecha !== '' && (!isset($params['fecha']) || $params['fecha'] === '' || $params['fecha'] === null)) {
            $params['fecha'] = $fecha;
        }
        if ($hora !== '' && (!isset($params['hora']) || $params['hora'] === '' || $params['hora'] === null)) {
            $params['hora'] = $hora;
        }
        if (!isset($params['intervalo_minutos_reserva']) || $params['intervalo_minutos_reserva'] === '' || $params['intervalo_minutos_reserva'] === null) {
            $params['intervalo_minutos_reserva'] = (int) $parsed['intervalo_minutos'];
        }

        if ($pesId > 0) {
            $pes = ProfesionalEfectorServicio::findOne(['id' => $pesId, 'deleted_at' => null]);
            if ($pes !== null) {
                if (
                    (!isset($params['id_servicio_asignado']) || $params['id_servicio_asignado'] === '' || $params['id_servicio_asignado'] === null)
                    && (int) $pes->id_servicio > 0
                ) {
                    $params['id_servicio_asignado'] = (int) $pes->id_servicio;
                }
                if (
                    (!isset($params['id_efector']) || $params['id_efector'] === '' || $params['id_efector'] === null)
                    && (int) $pes->id_efector > 0
                ) {
                    $params['id_efector'] = (int) $pes->id_efector;
                }
            }
        }

        return $params;
    }
}
