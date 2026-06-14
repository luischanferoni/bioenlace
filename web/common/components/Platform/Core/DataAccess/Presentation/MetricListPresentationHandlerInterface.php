<?php

namespace common\components\Platform\Core\DataAccess\Presentation;

use common\components\Platform\Core\DataAccess\MetricExecutionResult;

/**
 * Construye items de lista para ui_json {@see data-access/listar}.
 */
interface MetricListPresentationHandlerInterface
{
    /**
     * @return list<array{id: string, name: string, meta?: array<string, mixed>}>
     */
    public function buildListItems(MetricExecutionResult $result): array;

    /**
     * @return array<string, mixed>
     */
    public function buildRenderParams(MetricExecutionResult $result): array;
}
