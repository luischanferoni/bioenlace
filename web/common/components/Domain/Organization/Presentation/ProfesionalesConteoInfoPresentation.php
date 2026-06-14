<?php

namespace common\components\Domain\Organization\Presentation;

use common\components\Platform\Core\DataAccess\MetricExecutionResult;
use common\components\Platform\Core\DataAccess\Presentation\MetricInfoPresentationHandlerInterface;
use common\models\Efector;

/**
 * Texto de resumen para métrica profesionales_conteo_efector.
 */
final class ProfesionalesConteoInfoPresentation implements MetricInfoPresentationHandlerInterface
{
    public function buildRenderParams(MetricExecutionResult $result): array
    {
        $formatted = $this->formatResult($result);

        return [
            'info_title' => 'Profesionales del efector',
            'info_texto' => (string) ($formatted['resumen_texto'] ?? ''),
            'total' => (string) (int) ($formatted['total_profesionales'] ?? 0),
        ];
    }

    /**
     * @return array{
     *   total_profesionales: int,
     *   id_efector: int,
     *   nombre_efector: string,
     *   servicio_rol: string|null,
     *   servicio_rol_label: string|null,
     *   sexo_biologico: int|null,
     *   sexo_biologico_label: string|null,
     *   resumen_texto: string
     * }
     */
    public function formatResult(MetricExecutionResult $result): array
    {
        $idEfector = (int) ($result->meta['id_efector'] ?? 0);
        $total = $result->primaryAggregateValue();

        $servicioRol = isset($result->resolvedFilters['servicio_rol'])
            ? (string) $result->resolvedFilters['servicio_rol']
            : null;
        $servicioRolLabel = isset($result->resolvedFilters['servicio_rol_label'])
            ? (string) $result->resolvedFilters['servicio_rol_label']
            : null;
        $sexoBiologico = isset($result->resolvedFilters['sexo_biologico'])
            ? (int) $result->resolvedFilters['sexo_biologico']
            : null;
        $sexoLabel = isset($result->resolvedFilters['sexo_biologico_label'])
            ? (string) $result->resolvedFilters['sexo_biologico_label']
            : null;

        $efector = $idEfector > 0 ? Efector::findOne($idEfector) : null;
        $nombreEfector = $efector !== null ? trim((string) $efector->nombre) : ('Efector #' . $idEfector);
        if ($nombreEfector === '') {
            $nombreEfector = 'Efector #' . $idEfector;
        }

        $sinServicio = $result->shortCircuitEmpty && !empty($result->meta['sin_servicio_en_efector']);

        return [
            'total_profesionales' => $total,
            'id_efector' => $idEfector,
            'nombre_efector' => $nombreEfector,
            'servicio_rol' => $servicioRol,
            'servicio_rol_label' => $servicioRolLabel,
            'sexo_biologico' => $sexoBiologico,
            'sexo_biologico_label' => $sexoLabel,
            'resumen_texto' => $this->buildResumenTexto(
                $nombreEfector,
                $total,
                $servicioRolLabel,
                $sexoLabel,
                $sinServicio
            ),
        ];
    }

    private function buildResumenTexto(
        string $nombreEfector,
        int $total,
        ?string $servicioRolLabel,
        ?string $sexoLabel,
        bool $sinServicioEnEfector
    ): string {
        if ($sinServicioEnEfector && $servicioRolLabel !== null && $servicioRolLabel !== '') {
            return 'En '
                . $nombreEfector
                . ' no hay servicios de '
                . $servicioRolLabel
                . ' habilitados; no hay profesionales asignados bajo ese criterio.';
        }

        $partes = ['En ' . $nombreEfector . ' hay ' . $total . ' profesional' . ($total === 1 ? '' : 'es')];
        if ($servicioRolLabel !== null && $servicioRolLabel !== '') {
            $partes[] = ' en ' . $servicioRolLabel;
        }
        if ($sexoLabel !== null && $sexoLabel !== '') {
            $partes[] = ' (sexo biológico ' . $sexoLabel . ')';
        }
        $partes[] = '.';

        return implode('', $partes);
    }
}
