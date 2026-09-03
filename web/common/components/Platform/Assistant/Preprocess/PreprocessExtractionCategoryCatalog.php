<?php

namespace common\components\Platform\Assistant\Preprocess;

use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;
use common\components\Platform\Core\Product\ProductMetadataPaths;

/**
 * Catálogo cerrado de categorías para extractions del preprocess.
 *
 * Definiciones: {@see catalog/preprocess-extraction-categories.yaml}.
 */
final class PreprocessExtractionCategoryCatalog
{
    /** @var list<string>|null */
    private static ?array $idsCache = null;

    /** @var array<string, string>|null */
    private static ?array $descriptionCache = null;

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        self::loadCatalog();

        return self::$idsCache ?? [];
    }

    public static function isValid(string $id): bool
    {
        $id = trim($id);

        return $id !== '' && in_array($id, self::all(), true);
    }

    public static function description(string $id): string
    {
        $id = trim($id);
        if ($id === '') {
            return '';
        }
        self::loadCatalog();

        return self::$descriptionCache[$id] ?? '';
    }

    /**
     * Lista `- clave — descripción` para placeholders de prompt.
     */
    public static function listForPrompt(): string
    {
        $lines = [];
        foreach (self::all() as $id) {
            $desc = self::description($id);
            $lines[] = $desc !== '' ? '- ' . $id . ' — ' . $desc : '- ' . $id;
        }

        return implode("\n", $lines);
    }

    public static function resetCacheForTests(): void
    {
        self::$idsCache = null;
        self::$descriptionCache = null;
    }

    private static function loadCatalog(): void
    {
        if (self::$idsCache !== null) {
            return;
        }

        $config = AssistantMetadataLoader::load(ProductMetadataPaths::preprocessExtractionCategoriesFile());
        $raw = $config['categories'] ?? [];
        if (!is_array($raw)) {
            self::$idsCache = [];
            self::$descriptionCache = [];

            return;
        }

        $ids = [];
        $descriptions = [];
        foreach ($raw as $id => $desc) {
            $id = trim((string) $id);
            if ($id === '') {
                continue;
            }
            $ids[] = $id;
            $descriptions[$id] = trim((string) $desc);
        }

        self::$idsCache = $ids;
        self::$descriptionCache = $descriptions;
    }
}
