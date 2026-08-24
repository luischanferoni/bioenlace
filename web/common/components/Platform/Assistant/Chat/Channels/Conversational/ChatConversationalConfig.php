<?php

namespace common\components\Platform\Assistant\Chat\Channels\Conversational;

use common\components\Platform\Core\Product\ProductMetadataPaths;
use Symfony\Component\Yaml\Yaml;
use Yii;

/**
 * Metadata de copy del canal conversacional ({@see conversational-channel.yaml}).
 */
final class ChatConversationalConfig
{
    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    public static function resetCacheForTests(): void
    {
        self::$config = null;
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
        $prompt = trim((string) (self::load()['stable_prompt'] ?? ''));

        return $prompt !== '' ? $prompt : 'Respondé en español, breve y amable.';
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
        $raw = self::load()['capability_labels'] ?? [];
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
        $raw = self::load()['booking_offer_intent_priority'] ?? [];
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $id) {
            if (is_string($id) && trim($id) !== '') {
                $out[] = trim($id);
            }
        }

        return $out;
    }

    public static function bookingOfferIntentPrefixFallback(): string
    {
        return trim((string) (self::load()['booking_offer_intent_prefix_fallback'] ?? ''));
    }

    /**
     * @return array<string, mixed>
     */
    private static function load(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }
        $path = ProductMetadataPaths::conversationalChannelFile();
        if (!is_file($path)) {
            self::$config = [];

            return self::$config;
        }
        try {
            $parsed = Yaml::parseFile($path);
            self::$config = is_array($parsed) ? $parsed : [];
        } catch (\Throwable $e) {
            Yii::warning('ChatConversationalConfig: ' . $e->getMessage(), __METHOD__);
            self::$config = [];
        }

        return self::$config;
    }
}
