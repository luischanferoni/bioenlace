<?php

namespace common\components\Platform\Assistant\Chat\Routing\Handlers;

use common\components\Platform\Assistant\Chat\Envelope\AssistantEnvelope;
use common\components\Platform\Assistant\Planning\SmartCatalogRoutingDecision;

/**
 * Respuesta límite producto para consultas fuera del HIS.
 */
final class FueraDeHisHandler
{
    /**
     * @return array<string, mixed>
     */
    public static function handle(SmartCatalogRoutingDecision $decision): array
    {
        return AssistantEnvelope::message($decision->responseText);
    }
}
