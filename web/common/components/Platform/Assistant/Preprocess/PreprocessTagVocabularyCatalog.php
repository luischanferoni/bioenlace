<?php

namespace common\components\Platform\Assistant\Preprocess;

use common\components\Platform\Assistant\Catalog\StateTagIndex;

/**
 * Vocabulario de tags del preprocess: meta.tags de intents + extras PHP (artículos / soft tags).
 */
final class PreprocessTagVocabularyCatalog
{
    /**
     * Tags que no viven en YAML de flow (artículos BD, señales soft del adapter).
     *
     * @var list<string>
     */
    private const EXTRA_DISCOVERY_TAGS = [
        'representacion',
        'tutela',
        'representante',
        'fuera_his',
        'llegar_tarde',
        'tolerancia',
    ];

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

        $tags = StateTagIndex::allTags();
        foreach (self::EXTRA_DISCOVERY_TAGS as $tag) {
            if (!in_array($tag, $tags, true)) {
                $tags[] = $tag;
            }
        }
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
        StateTagIndex::resetCacheForTests();
        PreprocessRoutingHintCatalog::resetCacheForTests();
    }
}
