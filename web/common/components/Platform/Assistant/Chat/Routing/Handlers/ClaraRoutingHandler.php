<?php

namespace common\components\Platform\Assistant\Chat\Routing\Handlers;

use common\components\Platform\Assistant\Chat\Channels\Operational\OperationalChannel;

/**
 * Match 100% a un intent: abre el flow (1 IA).
 */
final class ClaraRoutingHandler
{
    /**
     * @return array<string, mixed>
     */
    public static function handleSingle(string $content, string $intentId, int $userId): array
    {
        return OperationalChannel::handle($content, $intentId, $userId);
    }
}
