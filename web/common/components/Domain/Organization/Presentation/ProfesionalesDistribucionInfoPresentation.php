<?php

namespace common\components\Domain\Organization\Presentation;

use common\components\Platform\Core\DataAccess\MetricExecutionResult;
use common\components\Platform\Core\DataAccess\Presentation\MetricInfoPresentationHandlerInterface;
use common\models\Efector;

/**
 * Texto de resumen para métrica profesionales_conteo_por_servicio_efector.
 */
final class ProfesionalesDistribucionInfoPresentation implements MetricInfoPresentationHandlerInterface
{
    public function buildRenderParams(MetricExecutionResult $result): array
    {
        return [
            'info_title' => 'Profesionales por servicio',
            'info_texto' => $this->buildResumenTexto($result),
        ];
    }

    public function buildResumenTexto(MetricExecutionResult $result): string
    {
        $nombreEfector = $this->nombreEfector($result);
        $groups = $this->normalizeGroups($result->groups);

        if ($groups === []) {
            return 'En ' . $nombreEfector . ' no hay profesionales asignados a servicios.';
        }

        if (count($groups) === 1) {
            $group = $groups[0];
            $n = $group['total'];

            return 'En ' . $nombreEfector . ' hay ' . $n . ' ' . $this->profesionalWord($n)
                . ' en ' . $group['nombre'] . '.';
        }

        $lines = ['En ' . $nombreEfector . ' hay profesionales en ' . count($groups) . ' servicios:', ''];
        foreach ($groups as $group) {
            $n = $group['total'];
            $lines[] = '• ' . $group['nombre'] . ': ' . $n . ' ' . $this->profesionalWord($n);
        }

        return implode("\n", $lines);
    }

    /**
     * @param list<array<string, mixed>> $groups
     * @return list<array{nombre: string, total: int}>
     */
    private function normalizeGroups(array $groups): array
    {
        $out = [];
        foreach ($groups as $group) {
            if (!is_array($group)) {
                continue;
            }
            $nombre = trim((string) ($group['servicio_nombre'] ?? ''));
            if ($nombre === '') {
                $idServicio = (int) ($group['id_servicio'] ?? 0);
                $nombre = $idServicio > 0 ? ('Servicio #' . $idServicio) : 'Servicio';
            }
            $out[] = [
                'nombre' => $nombre,
                'total' => (int) ($group['total'] ?? 0),
            ];
        }

        usort($out, static function (array $a, array $b): int {
            $byTotal = $b['total'] <=> $a['total'];
            if ($byTotal !== 0) {
                return $byTotal;
            }

            return strcmp($a['nombre'], $b['nombre']);
        });

        return $out;
    }

    private function nombreEfector(MetricExecutionResult $result): string
    {
        $fromMeta = trim((string) ($result->meta['nombre_efector'] ?? ''));
        if ($fromMeta !== '') {
            return $fromMeta;
        }

        $idEfector = (int) ($result->meta['id_efector'] ?? 0);
        if ($idEfector <= 0) {
            return 'el centro';
        }

        $efector = Efector::findOne($idEfector);
        $nombre = $efector !== null ? trim((string) $efector->nombre) : '';
        if ($nombre !== '') {
            return $nombre;
        }

        return 'Efector #' . $idEfector;
    }

    private function profesionalWord(int $n): string
    {
        return $n === 1 ? 'profesional' : 'profesionales';
    }
}
