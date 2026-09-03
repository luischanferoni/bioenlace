<?php

namespace common\components\Platform\Assistant\Preprocess;

use common\components\Platform\Assistant\Catalog\SmartCatalogRegistry;

/**
 * Vocabulario de tags del preprocess: unión de triggers del smart-catalog + extras YAML.
 */
final class PreprocessTagVocabularyCatalog
{
    /** @var list<string>|null */
    private static ?array $tagsCache = null;

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        if (self::$tagsCache !== null) {
            return self::$tagsCache;
        }

        $tags = SmartCatalogRegistry::allTriggerTags();
        foreach (PreprocessRoutingHintCatalog::extraPreprocessTags() as $tag) {
            if (!in_array($tag, $tags, true)) {
                $tags[] = $tag;
            }
        }
        sort($tags);

        return self::$tagsCache = $tags;
    }

    /**
     * Lista comma-separated para el prompt preprocess.
     */
    public static function listForPrompt(): string
    {
        return implode(', ', self::all());
    }

    public static function resetCacheForTests(): void
    {
        self::$tagsCache = null;
        SmartCatalogRegistry::resetCacheForTests();
        PreprocessRoutingHintCatalog::resetCacheForTests();
    }
}
