<?php

namespace common\components\Platform\Assistant\Chat\Channels\Ambiguous;

use common\components\Platform\Assistant\Chat\Envelope\AssistantEnvelope;

/**
 * Canal ambiguous: preguntas/botones fijos (metadata) para encauzar sin 2.ª IA.
 */
final class AmbiguousChannel
{
    /**
     * @return array<string, mixed>
     */
    public static function handle(): array
    {
        $buttons = [];
        foreach (AmbiguousChannelConfig::options() as $opt) {
            $buttons[] = [
                'label' => $opt['label'],
                'intent_id' => $opt['intent_id'],
                'content' => $opt['content'],
            ];
        }

        return AssistantEnvelope::interactive(AmbiguousChannelConfig::promptText(), $buttons);
    }
}
