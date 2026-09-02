<?php

namespace common\components\Platform\Assistant\Chat\Channels\Synthesis;

use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;
use common\components\Platform\Core\Product\ProductMetadataPaths;

/**
 * Metadata del canal síntesis ({@see prompts/synthesis.yaml}).
 */
final class SynthesisChannelConfig
{
    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    public static function resetCacheForTests(): void
    {
        self::$config = null;
        AssistantMetadataLoader::resetCacheForTests();
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

    /**
     * @param array<string, string> $vars
     */
    private static function stripOrphanInlineHeaders(string $text, array $vars): string
    {
        $stripWhenEmpty = [
            'context_his_areas_lines' => ['Ámbito de la consulta:'],
            'scoped_system_records' => ['Registros del sistema para responder:'],
            'necesidad_usuario' => ['Necesidad del usuario (explicitada por el sistema):'],
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
            return 'Respondé en español, breve y claro usando solo los datos adjuntos.';
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

        self::$config = AssistantMetadataLoader::load(ProductMetadataPaths::synthesisPromptFile());

        return self::$config;
    }
}
