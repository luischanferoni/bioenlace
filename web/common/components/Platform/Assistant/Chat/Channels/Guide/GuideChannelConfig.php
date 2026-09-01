<?php

namespace common\components\Platform\Assistant\Chat\Channels\Guide;

use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;
use common\components\Platform\Core\Product\ProductMetadataPaths;

/**
 * Metadata del canal guide ({@see prompts/guide.yaml}).
 */
final class GuideChannelConfig
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

    public static function stablePrompt(): string
    {
        return self::stablePromptTemplate();
    }

    /**
     * @param array<string, string> $dataVars
     */
    public static function assemblePrompt(array $dataVars): string
    {
        $out = AssistantMetadataLoader::applyPlaceholders(self::stablePromptTemplate(), $dataVars);
        $out = self::stripOrphanInlineHeaders($out, $dataVars);
        $out = preg_replace("/\n{3,}/", "\n\n", $out) ?? $out;

        return trim($out);
    }

    /**
     * Adjunto opcional: plantilla en optional_attachments.{key} con placeholder {data}.
     */
    public static function formatOptionalAttachment(string $key, string $data): string
    {
        $data = trim($data);
        if ($data === '') {
            return '';
        }

        $template = AssistantMetadataLoader::dotString(self::load(), 'optional_attachments.' . $key);
        if ($template === '') {
            return $data;
        }

        return trim(AssistantMetadataLoader::applyPlaceholders($template, ['data' => $data]));
    }

    public static function formatArticleContent(string $title, string $body): string
    {
        $title = trim($title);
        $body = trim($body);
        if ($body === '') {
            return '';
        }

        $parts = [];
        if ($title !== '') {
            $parts[] = 'Título: ' . $title;
        }
        $parts[] = 'Contenido:';
        $parts[] = $body;

        return implode("\n", $parts);
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
     * @param array<string, string> $vars
     */
    private static function stripOrphanInlineHeaders(string $text, array $vars): string
    {
        $stripWhenEmpty = [
            'query_scope_lines' => ['Ámbito de esta consulta:'],
            'scoped_system_records' => ['Registros del sistema para el ámbito de la consulta:'],
            'intent_semantics' => [
                'Funcionalidades que este usuario puede ejecutar en el sistema:',
            ],
            'conversation_history' => [
                'Conversación previa (más antigua → más reciente):',
            ],
        ];

        foreach ($stripWhenEmpty as $dataKey => $headerLines) {
            if (trim((string) ($vars[$dataKey] ?? '')) !== '') {
                continue;
            }
            foreach ($headerLines as $line) {
                $text = str_replace($line, '', $text);
            }
        }

        return $text;
    }

    private static function stablePromptTemplate(): string
    {
        $template = trim((string) (self::load()['stable_prompt'] ?? ''));
        if ($template === '') {
            return 'Respondé en español, breve y claro.';
        }

        return $template;
    }

    /**
     * @return array<string, mixed>
     */
    private static function load(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        self::$config = AssistantMetadataLoader::load(ProductMetadataPaths::guideChannelFile());

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
}
