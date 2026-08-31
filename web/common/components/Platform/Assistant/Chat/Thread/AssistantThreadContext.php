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
     *   clear_history: bool,
     *   guide_focus: array{primary_area: string, active_areas: list<string>}|null
     * }|null */
    private static ?array $decision = null;

    /**
     * @param array{
     *   thread_tag: string,
     *   previous_tag?: string,
     *   diverted?: bool,
     *   confidence?: float,
     *   offer_cta?: bool,
     *   clear_history?: bool,
     *   guide_focus?: array{primary_area?: string, active_areas?: list<string>}|null
     * } $decision
     */
    public static function set(array $decision): void
    {
        $guideFocus = null;
        if (isset($decision['guide_focus']) && is_array($decision['guide_focus'])) {
            $primary = trim((string) ($decision['guide_focus']['primary_area'] ?? ''));
            $active = $decision['guide_focus']['active_areas'] ?? [];
            if (!is_array($active)) {
                $active = [];
            }
            if ($primary !== '' || $active !== []) {
                $guideFocus = [
                    'primary_area' => $primary,
                    'active_areas' => array_values(array_filter(array_map(
                        static fn ($a) => is_string($a) ? trim($a) : '',
                        $active
                    ))),
                ];
            }
        }

        self::$decision = [
            'thread_tag' => trim((string) ($decision['thread_tag'] ?? '')),
            'previous_tag' => trim((string) ($decision['previous_tag'] ?? '')),
            'diverted' => !empty($decision['diverted']),
            'confidence' => (float) ($decision['confidence'] ?? 0.0),
            'offer_cta' => !empty($decision['offer_cta']),
            'clear_history' => !empty($decision['clear_history']),
            'guide_focus' => $guideFocus,
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
     *   clear_history: bool,
     *   guide_focus: array{primary_area: string, active_areas: list<string>}|null
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

    /**
     * @return array{primary_area: string, active_areas: list<string>}|null
     */
    public static function guideFocus(): ?array
    {
        return self::$decision['guide_focus'] ?? null;
    }

    public static function guideFocusPrimaryArea(): string
    {
        $focus = self::guideFocus();

        return trim((string) ($focus['primary_area'] ?? ''));
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
