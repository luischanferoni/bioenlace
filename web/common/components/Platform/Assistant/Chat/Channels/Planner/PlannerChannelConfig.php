<?php

namespace common\components\Platform\Assistant\Chat\Channels\Planner;

use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;
use common\components\Platform\Core\Product\ProductMetadataPaths;

/**
 * Metadata del canal planificador ({@see prompts/planner.yaml}).
 */
final class PlannerChannelConfig
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
        $out = preg_replace("/\n{3,}/", "\n\n", $out) ?? $out;

        return trim($out);
    }

    private static function stablePromptTemplate(): string
    {
        $template = trim((string) (self::load()['stable_prompt'] ?? ''));
        if ($template === '') {
            return 'Elegí tool_ids del shortlist. Respondé JSON con tool_ids_ordered.';
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

        self::$config = AssistantMetadataLoader::load(ProductMetadataPaths::plannerPromptFile());

        return self::$config;
    }
}
