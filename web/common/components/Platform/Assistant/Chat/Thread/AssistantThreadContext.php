<?php

namespace common\components\Platform\Assistant\Chat\Thread;

/**
 * Decisión de hilo del request actual (request-scoped).
 */
final class AssistantThreadContext
{
    /** @var array{
     *   thread_tag: string,
     *   previous_tag: string,
     *   diverted: bool,
     *   confidence: float,
     *   offer_cta: bool,
     *   clear_history: bool
     * }|null */
    private static ?array $decision = null;

    /**
     * @param array{
     *   thread_tag: string,
     *   previous_tag?: string,
     *   diverted?: bool,
     *   confidence?: float,
     *   offer_cta?: bool,
     *   clear_history?: bool
     * } $decision
     */
    public static function set(array $decision): void
    {
        self::$decision = [
            'thread_tag' => trim((string) ($decision['thread_tag'] ?? '')),
            'previous_tag' => trim((string) ($decision['previous_tag'] ?? '')),
            'diverted' => !empty($decision['diverted']),
            'confidence' => (float) ($decision['confidence'] ?? 0.0),
            'offer_cta' => !empty($decision['offer_cta']),
            'clear_history' => !empty($decision['clear_history']),
        ];
    }

    public static function clear(): void
    {
        self::$decision = null;
    }

    /**
     * @return array{
     *   thread_tag: string,
     *   previous_tag: string,
     *   diverted: bool,
     *   confidence: float,
     *   offer_cta: bool,
     *   clear_history: bool
     * }|null
     */
    public static function get(): ?array
    {
        return self::$decision;
    }

    public static function threadTag(): string
    {
        return self::$decision['thread_tag'] ?? '';
    }

    public static function offerCta(): bool
    {
        return !empty(self::$decision['offer_cta']);
    }

    public static function clearHistory(): bool
    {
        return !empty(self::$decision['clear_history']);
    }

    public static function diverted(): bool
    {
        return !empty(self::$decision['diverted']);
    }
}
