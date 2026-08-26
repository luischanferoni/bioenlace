<?php

namespace common\components\Platform\Assistant\Chat\Channels\Conversational;

use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;
use common\components\Platform\Core\Product\ProductMetadataPaths;

/**
 * Metadata del canal conversational_clinical ({@see prompts/conversational_clinical.yaml}).
 * Prioridad booking: {@see routing/booking-offer.yaml}.
 */
final class ChatConversationalConfig
{
    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    /** @var array<string, mixed>|null */
    private static ?array $bookingConfig = null;

    public static function resetCacheForTests(): void
    {
        self::$config = null;
        self::$bookingConfig = null;
        AssistantMetadataLoader::resetCacheForTests();
    }

    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return self::load();
    }

    public static function stablePrompt(): string
    {
        $template = trim((string) (self::load()['stable_prompt'] ?? ''));
        if ($template === '') {
            return 'Respondé en español, breve y amable.';
        }

        return self::applyPromptPlaceholders($template);
    }

    /**
     * Fragmento de copy del prompt ({@see prompts/conversational_clinical.yaml} → prompt_fragments).
     * Ruta con punto: offer.header, offer.continuing_line, etc.
     */
    public static function promptFragment(string $path, string $default = ''): string
    {
        $raw = self::load()['prompt_fragments'] ?? [];
        if (!is_array($raw)) {
            return $default;
        }

        $node = $raw;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($node) || !array_key_exists($segment, $node)) {
                return $default;
            }
            $node = $node[$segment];
        }

        if (!is_string($node)) {
            return $default;
        }

        $text = trim($node);

        return $text !== '' ? self::applyPromptPlaceholders($text) : $default;
    }

    /**
     * @param array<string, string> $extra
     */
    public static function formatPromptFragment(string $path, array $extra = [], string $default = ''): string
    {
        $template = self::promptFragment($path, $default);
        if ($template === '') {
            return '';
        }

        $vars = array_merge(self::basePromptPlaceholders(), $extra);
        $out = $template;
        foreach ($vars as $key => $value) {
            $out = str_replace('{' . $key . '}', $value, $out);
        }

        return $out;
    }

    public static function emptyResponseFallback(): string
    {
        $text = trim((string) (self::load()['empty_response_fallback'] ?? ''));

        return $text !== '' ? $text : 'Entiendo tu consulta.';
    }

    /**
     * @return array<string, string>
     */
    public static function capabilityLabels(): array
    {
        $raw = self::loadBooking()['capability_labels'] ?? [];
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $k => $v) {
            if (is_string($k) && is_string($v) && trim($k) !== '' && trim($v) !== '') {
                $out[trim($k)] = trim($v);
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    public static function bookingOfferIntentPriority(): array
    {
        return AssistantMetadataLoader::dotStringList(self::loadBooking(), 'booking_offer_intent_priority');
    }

    public static function bookingOfferIntentPrefixFallback(): string
    {
        return AssistantMetadataLoader::dotString(
            self::loadBooking(),
            'booking_offer_intent_prefix_fallback'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function load(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        self::$config = AssistantMetadataLoader::load(ProductMetadataPaths::conversationalChannelFile());

        return self::$config;
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadBooking(): array
    {
        if (self::$bookingConfig !== null) {
            return self::$bookingConfig;
        }

        self::$bookingConfig = AssistantMetadataLoader::load(ProductMetadataPaths::bookingOfferFile());

        return self::$bookingConfig;
    }

    /**
     * @return array<string, string>
     */
    private static function basePromptPlaceholders(): array
    {
        $raw = self::load()['prompt_fragments']['offer_block_title'] ?? 'Oferta disponible';
        $title = is_string($raw) ? trim($raw) : 'Oferta disponible';

        return ['offer_block_title' => $title !== '' ? $title : 'Oferta disponible'];
    }

    private static function applyPromptPlaceholders(string $text): string
    {
        return AssistantMetadataLoader::applyPlaceholders($text, self::basePromptPlaceholders());
    }
}
