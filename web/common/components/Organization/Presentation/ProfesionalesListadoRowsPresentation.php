<?php

namespace common\components\Organization\Presentation;

use common\components\Core\DataAccess\MetricExecutionResult;
use common\components\Core\DataAccess\Presentation\MetricListPresentationHandlerInterface;
use common\models\Servicio;

/**
 * Items de lista para métrica profesionales_listado_efector.
 */
final class ProfesionalesListadoRowsPresentation implements MetricListPresentationHandlerInterface
{
    public function buildRenderParams(MetricExecutionResult $result): array
    {
        return [];
    }

    /**
     * @return list<array{
     *   id: string,
     *   name: string,
     *   servicio_nombre: string,
     *   sexo_label: string,
     *   meta: array<string, mixed>
     * }>
     */
    public function buildListItems(MetricExecutionResult $result): array
    {
        $idServicios = [];
        foreach ($result->rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id_servicio'] ?? 0);
            if ($id > 0) {
                $idServicios[$id] = true;
            }
        }

        $nombresServicio = $this->nombresServicioPorIds(array_keys($idServicios));

        $items = [];
        foreach ($result->rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $idPersona = (int) ($row['id_persona'] ?? 0);
            $idServicio = (int) ($row['id_servicio'] ?? 0);
            $nombre = trim((string) ($row['nombre'] ?? ''));
            $apellido = trim((string) ($row['apellido'] ?? ''));
            $label = trim($apellido . ', ' . $nombre, ', ');
            if ($label === '') {
                $label = $idPersona > 0 ? ('Profesional #' . $idPersona) : 'Profesional';
            }

            $items[] = [
                'id' => (string) ($idPersona > 0 ? $idPersona : count($items) + 1),
                'name' => $label,
                'servicio_nombre' => $nombresServicio[$idServicio] ?? ($idServicio > 0 ? ('Servicio #' . $idServicio) : '—'),
                'sexo_label' => $this->sexoLabel($row['sexo_biologico'] ?? null),
                'meta' => [
                    'id_persona' => $idPersona,
                    'id_servicio' => $idServicio,
                    'sexo_biologico' => $row['sexo_biologico'] ?? null,
                ],
            ];
        }

        return $items;
    }

    /**
     * @param list<int> $ids
     * @return array<int, string>
     */
    private function nombresServicioPorIds(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0));
        if ($ids === []) {
            return [];
        }

        $out = [];
        foreach (Servicio::find()->where(['id_servicio' => $ids])->all() as $servicio) {
            if (!$servicio instanceof Servicio) {
                continue;
            }
            $nombre = trim((string) $servicio->nombre);
            $out[(int) $servicio->id_servicio] = $nombre !== '' ? $nombre : ('Servicio #' . $servicio->id_servicio);
        }

        return $out;
    }

    private function sexoLabel(mixed $code): string
    {
        return match ((int) $code) {
            1 => 'F',
            2 => 'M',
            default => '—',
        };
    }
}
