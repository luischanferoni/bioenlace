<?php

namespace common\components\Platform\Assistant\Chat\Channels\Ambiguous;

use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;
use common\components\Platform\Core\Product\ProductMetadataPaths;

/**
 * Metadata de encauzamiento ambiguous ({@see prompts/ambiguous_conversational.yaml}).
 */
final class AmbiguousChannelConfig
{
    public const STEER_INTENT_PREFIX = 'assistant.channel.';

    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    public static function resetCacheForTests(): void
    {
        self::$config = null;
        AssistantMetadataLoader::resetCacheForTests();
    }

    public static function promptText(): string
    {
        $text = AssistantMetadataLoader::dotString(self::load(), 'prompt_text');

        return $text !== ''
            ? $text
            : 'No estoy seguro de qué necesitás. ¿Podés contarme un poco más?';
    }

    /**
     * @return list<array{id: string, label: string, next_channel: string, content: string, intent_id: string}>
     */
    public static function options(): array
    {
        $raw = self::load()['options'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $label = trim((string) ($row['label'] ?? ''));
            $next = trim((string) ($row['next_channel'] ?? ''));
            if ($label === '' || $next === '') {
                continue;
            }
            $id = trim((string) ($row['id'] ?? $next));
            $content = trim((string) ($row['content'] ?? $label));
            $out[] = [
                'id' => $id !== '' ? $id : $next,
                'label' => $label,
                'next_channel' => $next,
                'content' => $content,
                'intent_id' => self::STEER_INTENT_PREFIX . $next,
            ];
        }

        return $out;
    }

    public static function isSteerIntentId(string $intentId): bool
    {
        $intentId = trim($intentId);

        return $intentId !== '' && str_starts_with($intentId, self::STEER_INTENT_PREFIX);
    }

    public static function channelFromSteerIntentId(string $intentId): string
    {
        $intentId = trim($intentId);
        if (!self::isSteerIntentId($intentId)) {
            return '';
        }

        return trim(substr($intentId, strlen(self::STEER_INTENT_PREFIX)));
    }

    /**
     * @return array<string, mixed>
     */
    private static function load(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        self::$config = AssistantMetadataLoader::load(ProductMetadataPaths::ambiguousConversationalFile());

        return self::$config;
    }
}
