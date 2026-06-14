<?php

namespace common\components\Platform\Core\DataAccess;

use common\components\Platform\Core\DataAccess\Presentation\MetricPresentationRegistry;
use common\components\Platform\Ui\UiScreenService;

/**
 * Ejecuta métricas staff y devuelve ui_json genérico ({@see /api/info}, {@see /api/listar}).
 */
final class DataAccessUiService
{
    /** @var MetricQueryExecutor */
    private $executor;

    /** @var AttributeGroupCatalog */
    private $catalog;

    public function __construct(?MetricQueryExecutor $executor = null, ?AttributeGroupCatalog $catalog = null)
    {
        $this->executor = $executor ?? new MetricQueryExecutor();
        $this->catalog = $catalog ?? new AttributeGroupCatalog();
    }

    /**
     * @param array<string, mixed> $params requiere metric_id
     * @return array<string, mixed>
     */
    public function renderInfo(array $params, ?PermissionContext $ctx = null): array
    {
        $metricId = $this->requireMetricId($params);
        $params['output_mode'] = $this->resolveOutputMode($params, $metricId, [
            QueryOutputMode::AGGREGATE,
            QueryOutputMode::GROUPED,
        ]);

        $result = $this->executor->executeFromParams($params, $ctx);
        $handlerId = $this->catalog->getPresentationHandler($metricId);
        $renderParams = $handlerId !== null
            ? MetricPresentationRegistry::buildInfoRenderParams($handlerId, $result)
            : MetricPresentationRegistry::buildGenericInfoRenderParams($result);

        $out = UiScreenService::renderUiDefinition('data-access', 'info', $renderParams, null);
        $out['success'] = true;
        $out['data'] = $this->buildDataPayload($result, [
            'presentation' => $renderParams,
        ]);

        return $out;
    }

    /**
     * @param array<string, mixed> $params requiere metric_id
     * @return array<string, mixed>
     */
    public function renderListar(array $params, ?PermissionContext $ctx = null): array
    {
        $metricId = $this->requireMetricId($params);
        $params['output_mode'] = QueryOutputMode::ROWS;
        $this->assertOutputModeAllowed($metricId, QueryOutputMode::ROWS);

        $result = $this->executor->executeFromParams($params, $ctx);
        $handlerId = $this->catalog->getPresentationHandler($metricId);
        if ($handlerId === null) {
            throw new \InvalidArgumentException('La métrica no define presentation_handler para listados.');
        }

        $renderParams = MetricPresentationRegistry::buildListRenderParams($handlerId, $result);
        $items = MetricPresentationRegistry::buildListItems($handlerId, $result);

        $out = UiScreenService::renderUiDefinition('data-access', 'listar', $renderParams, null);
        $out = UiScreenService::withListBlockItems($out, $items, 'rows');
        $out['success'] = true;
        $out['data'] = $this->buildDataPayload($result, [
            'items' => $items,
        ]);

        return $out;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function requireMetricId(array $params): string
    {
        $metricId = trim((string) ($params['metric_id'] ?? $params['metricId'] ?? ''));
        if ($metricId === '') {
            throw new \InvalidArgumentException('metric_id es requerido.');
        }
        if ($this->catalog->getMetric($metricId) === null) {
            throw new \InvalidArgumentException('Métrica no registrada: ' . $metricId);
        }

        return $metricId;
    }

    /**
     * @param list<string> $allowedModes
     */
    private function resolveOutputMode(array $params, string $metricId, array $allowedModes): string
    {
        $requested = trim((string) ($params['output_mode'] ?? ''));
        if ($requested === '') {
            $plan = $this->catalog->getMetricOutputPlan($metricId);
            $requested = trim((string) ($plan['default'] ?? QueryOutputMode::AGGREGATE));
        }
        $mode = QueryOutputMode::normalize($requested);
        $this->assertOutputModeAllowed($metricId, $mode, $allowedModes);

        return $mode;
    }

    /**
     * @param list<string>|null $allowedModes
     */
    private function assertOutputModeAllowed(string $metricId, string $mode, ?array $allowedModes = null): void
    {
        $plan = $this->catalog->getMetricOutputPlan($metricId);
        $modes = isset($plan['modes']) && is_array($plan['modes']) ? $plan['modes'] : [];
        if ($modes !== [] && !in_array($mode, $modes, true)) {
            throw new \InvalidArgumentException('output_mode no permitido para la métrica: ' . $mode);
        }
        if ($allowedModes !== null && !in_array($mode, $allowedModes, true)) {
            throw new \InvalidArgumentException('output_mode no válido en este endpoint: ' . $mode);
        }
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function buildDataPayload(MetricExecutionResult $result, array $extra = []): array
    {
        return array_merge([
            'metric_id' => $result->metricId,
            'output_mode' => $result->outputMode,
            'aggregates' => $result->aggregates,
            'rows' => $result->rows,
            'groups' => $result->groups,
            'resolved_filters' => $result->resolvedFilters,
            'short_circuit_empty' => $result->shortCircuitEmpty,
            'meta' => $result->meta,
        ], $extra);
    }
}
