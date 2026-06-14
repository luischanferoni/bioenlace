<?php

namespace common\components\Platform\Core\DataAccess\Presentation;

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
        $total = $result->primaryAggregateValue();
        $label = trim((string) ($result->meta['metric_label'] ?? $result->metricId));

        return [
            'info_texto' => ($label !== '' ? $label . ': ' : '') . $total,
            'info_title' => $label !== '' ? $label : 'Resultado',
        ];
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
