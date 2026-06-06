<?php

namespace common\components\Organization\Presentation;

use common\components\Core\DataAccess\MetricExecutionResult;
use common\components\Core\DataAccess\Presentation\MetricListPresentationHandlerInterface;

/**
 * Items de lista para métrica profesionales_listado_efector.
 */
final class ProfesionalesListadoRowsPresentation implements MetricListPresentationHandlerInterface
{
    public function buildRenderParams(MetricExecutionResult $result): array
    {
        return [
            'list_title' => 'Profesionales del efector',
            'intro_texto' => 'Listado según permisos de lectura (sin documentos ni datos no autorizados).',
        ];
    }

    /**
     * @return list<array{id: string, name: string, meta: array<string, mixed>}>
     */
    public function buildListItems(MetricExecutionResult $result): array
    {
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
                'meta' => [
                    'id_persona' => $idPersona,
                    'id_servicio' => $idServicio,
                    'sexo_biologico' => $row['sexo_biologico'] ?? null,
                ],
            ];
        }

        return $items;
    }
}
