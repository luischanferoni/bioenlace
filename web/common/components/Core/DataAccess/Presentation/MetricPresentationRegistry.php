<?php

namespace common\components\Core\DataAccess\Presentation;

use common\components\Core\DataAccess\MetricExecutionResult;
use common\components\Organization\Presentation\ProfesionalesConteoInfoPresentation;
use common\components\Organization\Presentation\ProfesionalesListadoRowsPresentation;

/**
 * Mapa estable handler_id → presentación de métrica (IDs en attribute_groups_v1.yaml).
 */
final class MetricPresentationRegistry
{
    /** @var array<string, class-string> */
    private const INFO_HANDLERS = [
        'organization.profesionales_conteo_info' => ProfesionalesConteoInfoPresentation::class,
    ];

    /** @var array<string, class-string> */
    private const LIST_HANDLERS = [
        'organization.profesionales_listado_rows' => ProfesionalesListadoRowsPresentation::class,
    ];

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
        $class = self::INFO_HANDLERS[trim($handlerId)] ?? null;
        if ($class === null || !is_subclass_of($class, MetricInfoPresentationHandlerInterface::class)) {
            throw new \InvalidArgumentException('presentation_handler info desconocido: ' . $handlerId);
        }

        return new $class();
    }

    private static function resolveListHandler(string $handlerId): MetricListPresentationHandlerInterface
    {
        $class = self::LIST_HANDLERS[trim($handlerId)] ?? null;
        if ($class === null || !is_subclass_of($class, MetricListPresentationHandlerInterface::class)) {
            throw new \InvalidArgumentException('presentation_handler list desconocido: ' . $handlerId);
        }

        return new $class();
    }
}
