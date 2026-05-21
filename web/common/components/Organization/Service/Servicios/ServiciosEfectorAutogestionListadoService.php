<?php

namespace common\components\Organization\Service\Servicios;

use common\models\ServiciosEfector;

/**
 * Listados de servicios para autogestión de turnos (origen {@see ServiciosEfector}, no catálogo `servicios` aislado).
 */
final class ServiciosEfectorAutogestionListadoService
{
    /**
     * Servicios distintos habilitados en al menos un efector: `servicios_efector` + join `servicios` con agenda.
     *
     * @return list<array{id: string, name: string}>
     */
    public static function uiJsonItemsServiciosDistintosAceptaTurnos(): array
    {
        // `servicios_efector` no tiene soft-delete en BD (sin `deleted_at`); no usar findActive().
        $rows = ServiciosEfector::find()
            ->alias('se')
            ->innerJoin(['s' => 'servicios'], 's.id_servicio = se.id_servicio')
            ->andWhere(['s.acepta_turnos' => 'SI'])
            ->select(['se.id_servicio', 's.nombre'])
            ->groupBy(['se.id_servicio', 's.nombre'])
            ->orderBy(['s.nombre' => SORT_ASC])
            ->asArray()
            ->all();

        $items = [];
        foreach ($rows as $row) {
            $idServicio = (int) ($row['id_servicio'] ?? 0);
            if ($idServicio <= 0 || $idServicio === 62) {
                continue;
            }
            $nombre = trim((string) ($row['nombre'] ?? ''));
            $items[] = [
                'id' => (string) $idServicio,
                'name' => $nombre !== '' ? $nombre : ('Servicio #' . $idServicio),
            ];
        }

        return $items;
    }
}
