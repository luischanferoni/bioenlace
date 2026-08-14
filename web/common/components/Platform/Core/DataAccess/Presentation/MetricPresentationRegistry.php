<?php

namespace common\components\Platform\Core\DataAccess\Presentation;

use common\components\Platform\Core\DataAccess\AttributeGroupCatalog;
use common\components\Platform\Core\DataAccess\MetricExecutionResult;
use common\components\Platform\Core\Product\ProductRegistryConfig;

/**
 * Mapa estable handler_id → presentación de métrica (IDs en data-access-config).
 *
 * Clases en {@see common/config/product-registries.php} (`metricPresentationHandlers`).
 */
final class MetricPresentationRegistry
{
    public static function buildInfoRenderParams(string $handlerId, MetricExecutionResult $result): array
    {
        $handler = self::resolveInfoHandler($handlerId);

        return $handler->buildRenderParams($result);
    }

    /**
     * @return list<array{id: string, name: string, meta?: array<string, mixed>}>
     */
    public static function buildListItems(string $handlerId, MetricExecutionResult $result): array
    {
        $handler = self::resolveListHandler($handlerId);

        return $handler->buildListItems($result);
    }

    public static function buildListRenderParams(string $handlerId, MetricExecutionResult $result): array
    {
        $handler = self::resolveListHandler($handlerId);

        return $handler->buildRenderParams($result);
    }

    public static function buildGenericInfoRenderParams(MetricExecutionResult $result): array
    {
        $label = self::resolveMetricLabel($result);
        $title = $label !== '' ? $label : 'Resultado';
        if ($result->groups !== []) {
            $lines = self::formatGroupLines($result->groups);
            $texto = $lines !== []
                ? ($label !== '' ? $label . "\n\n" . implode("\n", $lines) : implode("\n", $lines))
                : ($label !== '' ? $label . ': 0' : 'Sin resultados');

            return [
                'info_texto' => $texto,
                'info_title' => $title,
            ];
        }

        $total = $result->primaryAggregateValue();

        return [
            'info_texto' => ($label !== '' ? $label . ': ' : '') . $total,
            'info_title' => $title,
        ];
    }

    private static function resolveMetricLabel(MetricExecutionResult $result): string
    {
        $fromMeta = trim((string) ($result->meta['metric_label'] ?? ''));
        if ($fromMeta !== '') {
            return $fromMeta;
        }

        $metric = (new AttributeGroupCatalog())->getMetric($result->metricId);
        if (!is_array($metric)) {
            return '';
        }

        return trim((string) ($metric['label'] ?? ''));
    }

    /**
     * @param list<array<string, mixed>> $groups
     * @return list<string>
     */
    private static function formatGroupLines(array $groups): array
    {
        $lines = [];
        foreach ($groups as $group) {
            if (!is_array($group)) {
                continue;
            }
            $name = self::groupDisplayName($group);
            $total = (int) ($group['total'] ?? 0);
            $lines[] = $name . ': ' . $total;
        }

        return $lines;
    }

    /**
     * @param array<string, mixed> $group
     */
    private static function groupDisplayName(array $group): string
    {
        foreach ($group as $key => $value) {
            if (!is_string($key) || str_starts_with($key, 'id_') || $key === 'id' || $key === 'total') {
                continue;
            }
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return 'Grupo';
    }

    private static function resolveInfoHandler(string $handlerId): MetricInfoPresentationHandlerInterface
    {
        $handlers = ProductRegistryConfig::section('metricPresentationHandlers');
        $info = is_array($handlers['info'] ?? null) ? $handlers['info'] : [];
        $class = $info[trim($handlerId)] ?? null;
        if ($class === null || !is_string($class) || !is_subclass_of($class, MetricInfoPresentationHandlerInterface::class)) {
            throw new \InvalidArgumentException('presentation_handler info desconocido: ' . $handlerId);
        }

        return new $class();
    }

    private static function resolveListHandler(string $handlerId): MetricListPresentationHandlerInterface
    {
        $handlers = ProductRegistryConfig::section('metricPresentationHandlers');
        $list = is_array($handlers['list'] ?? null) ? $handlers['list'] : [];
        $class = $list[trim($handlerId)] ?? null;
        if ($class === null || !is_string($class) || !is_subclass_of($class, MetricListPresentationHandlerInterface::class)) {
            throw new \InvalidArgumentException('presentation_handler list desconocido: ' . $handlerId);
        }

        return new $class();
    }
}
