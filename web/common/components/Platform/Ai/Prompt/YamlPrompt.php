<?php

namespace common\components\Platform\Ai\Prompt;

use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;

/**
 * Prompts de IA versionados en YAML (`version` + `template`, y opcionalmente `texts`).
 *
 * El template usa placeholders `{nombre}`; solo se reemplazan los que tienen forma de identificador,
 * así que el JSON de ejemplo dentro del prompt no se toca. Un placeholder sin valor o un valor
 * sobrante lanzan error: el prompt nunca sale a la IA con llaves sin resolver.
 */
final class YamlPrompt
{
    private const PLACEHOLDER = '/\{([a-z][a-z0-9_]*)\}/';

    /**
     * @param array<string, string|int|float> $vars
     */
    public static function render(string $file, array $vars = []): string
    {
        $template = self::template($file);
        $used = [];
        $out = preg_replace_callback(self::PLACEHOLDER, static function (array $m) use ($vars, $file, &$used): string {
            if (!array_key_exists($m[1], $vars)) {
                throw new \LogicException("Prompt {$file}: falta el valor de {{$m[1]}}");
            }
            $used[$m[1]] = true;

            return (string) $vars[$m[1]];
        }, $template);

        $extra = array_diff(array_keys($vars), array_keys($used));
        if ($extra !== []) {
            throw new \LogicException("Prompt {$file}: valores sin placeholder: " . implode(', ', $extra));
        }

        return (string) $out;
    }

    public static function template(string $file): string
    {
        $template = self::data($file)['template'] ?? '';
        if (!is_string($template) || trim($template) === '') {
            throw new \LogicException("Prompt {$file}: falta `template`");
        }

        return $template;
    }

    /** Texto auxiliar del mismo YAML (`texts.<clave>`), sin placeholders. */
    public static function text(string $file, string $key): string
    {
        $text = self::data($file)['texts'][$key] ?? null;
        if (!is_string($text) || $text === '') {
            throw new \LogicException("Prompt {$file}: falta texts.{$key}");
        }

        return $text;
    }

    public static function version(string $file): int
    {
        return (int) (self::data($file)['version'] ?? 0);
    }

    /**
     * @return list<string> placeholders del template, sin repetir
     */
    public static function placeholders(string $file): array
    {
        preg_match_all(self::PLACEHOLDER, self::template($file), $m);

        return array_values(array_unique($m[1]));
    }

    /**
     * @return array<string, mixed>
     */
    private static function data(string $file): array
    {
        if (!is_file($file)) {
            throw new \LogicException("Prompt YAML inexistente: {$file}");
        }

        return AssistantMetadataLoader::load($file);
    }
}
