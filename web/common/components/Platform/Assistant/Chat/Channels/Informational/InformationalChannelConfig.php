<?php

namespace common\components\Platform\Assistant\Chat\Channels\Informational;

use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;
use common\components\Platform\Core\Product\ProductMetadataPaths;

/**
 * Metadata del canal informational ({@see prompts/informational_conversational.yaml}).
 */
final class InformationalChannelConfig
{
    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    public static function resetCacheForTests(): void
    {
        self::$config = null;
        AssistantMetadataLoader::resetCacheForTests();
    }

    public static function stablePrompt(): string
    {
        $text = AssistantMetadataLoader::dotString(self::load(), 'stable_prompt');

        return $text !== ''
            ? $text
            : 'Respondé en español usando solo la fuente inyectada.';
    }

    public static function message(string $key, string $default = ''): string
    {
        $text = AssistantMetadataLoader::dotString(self::load(), 'messages.' . $key);

        return $text !== '' ? $text : $default;
    }

    public static function sourceInjectionHeader(): string
    {
        return AssistantMetadataLoader::dotString(
            self::load(),
            'source_injection.header',
            'Fuente (única verdad para la respuesta):'
        );
    }

    public static function formatSourceTitleLine(string $title): string
    {
        $template = AssistantMetadataLoader::dotString(
            self::load(),
            'source_injection.title_line',
            'Título: {title}'
        );

        return AssistantMetadataLoader::applyPlaceholders($template, ['title' => $title]);
    }

    public static function sourceInjectionBodyHeader(): string
    {
        return AssistantMetadataLoader::dotString(
            self::load(),
            'source_injection.body_header',
            'Contenido:'
        );
    }

    /**
     * Arma el bloque de fuente para el prompt de IA (fase 03).
     */
    public static function formatSourceBlock(string $title, string $body): string
    {
        $title = trim($title);
        $body = trim($body);
        if ($body === '') {
            return '';
        }

        $parts = [self::sourceInjectionHeader()];
        if ($title !== '') {
            $parts[] = self::formatSourceTitleLine($title);
        }
        $parts[] = self::sourceInjectionBodyHeader();
        $parts[] = $body;

        return implode("\n", $parts);
    }

    /**
     * @return array<string, mixed>
     */
    private static function load(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        self::$config = AssistantMetadataLoader::load(ProductMetadataPaths::informationalConversationalFile());

        return self::$config;
    }
}
