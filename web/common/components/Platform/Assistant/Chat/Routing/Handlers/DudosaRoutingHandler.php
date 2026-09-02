<?php

namespace common\components\Platform\Assistant\Chat\Routing\Handlers;

use common\components\Platform\Assistant\Chat\Channels\Ambiguous\AmbiguousChannel;

/**
 * Routing dudosa: preguntas fijas del canal ambiguous (sin 2ª IA).
 */
final class DudosaRoutingHandler
{
    /**
     * @return array<string, mixed>
     */
    public static function handle(): array
    {
        return AmbiguousChannel::handle();
    }
}
