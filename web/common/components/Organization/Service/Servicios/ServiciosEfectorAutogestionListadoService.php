<?php

namespace common\components\Organization\Service\Servicios;

use common\components\Scheduling\Service\ReservaTriageServicioSugeridoService;
use common\models\ServiciosEfector;

/**
 * Listados de servicios para autogestión de turnos (origen {@see ServiciosEfector}, no catálogo `servicios` aislado).
 */
final class ServiciosEfectorAutogestionListadoService
{
    private const EXCLUDED_ID_SERVICIO = 62;

    /**
     * Servicios distintos habilitados en al menos un efector: `servicios_efector` + join `servicios` con agenda.
     *
     * @param array<string, mixed>|null $triageDraft campos triage para filtrar por rol sugerido (opcional)
     * @return list<array{id: string, name: string}>
     */
    public static function uiJsonItemsServiciosDistintosAceptaTurnos(?array $triageDraft = null, bool $soloHubPaciente = false): array
    {
        $items = self::buildBaseItems();
        if ($soloHubPaciente || ($triageDraft !== null && $triageDraft !== [])) {
            return (new ReservaTriageServicioSugeridoService())->filtrarItemsUiJson(
                $items,
                $triageDraft ?? [],
                $soloHubPaciente && ($triageDraft === null || $triageDraft === [])
            );
        }

        return $items;
    }

    /**
     * @return list<int>
     */
    public static function idsServiciosDistintosAceptaTurnos(): array
    {
        $ids = [];
        foreach (self::buildBaseItems() as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    private static function buildBaseItems(): array
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
            if ($idServicio <= 0 || $idServicio === self::EXCLUDED_ID_SERVICIO) {
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
