<?php

namespace common\components\Core\DataAccess\Presentation;

use common\components\Core\DataAccess\MetricExecutionResult;

/**
 * Construye parámetros de render para ui_json {@see data-access/info}.
 */
interface MetricInfoPresentationHandlerInterface
{
    /**
     * @return array<string, mixed>
     */
    public function buildRenderParams(MetricExecutionResult $result): array;
}
