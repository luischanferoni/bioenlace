<?php

namespace common\components\Platform\Assistant\Preprocess;

use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;
use common\components\Platform\Assistant\Service\HintCandidateProviderRegistry;
use common\components\Platform\Core\Product\ProductMetadataPaths;

/**
 * Catálogo de categorías de extractions del preprocess.
 *
 * - **Ids**: los declaran los dominios vía {@see HintCandidateProviderInterface::declaredEntities()}.
 * - **Texto** para el prompt: YAML `preprocess-extraction-categories.yaml` (id → descripción).
 * - Un test cierra el círculo: id sin texto / texto sin id → falla.
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
        self::ensureLoaded();

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
        self::ensureLoaded();

        return self::$descriptionCache[$id] ?? '';
    }

    /**
     * Lista `- clave — descripción` para placeholders de prompt.
     * Solo entidades declaradas por un dominio (con texto del YAML).
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

    /**
     * Textos del YAML (pueden incluir huérfanos; el test de source-of-truth los marca).
     *
     * @return array<string, string>
     */
    public static function textsFromYaml(): array
    {
        self::loadYamlTexts();

        return self::$descriptionCache ?? [];
    }

    public static function resetCacheForTests(): void
    {
        self::$idsCache = null;
        self::$descriptionCache = null;
        HintCandidateProviderRegistry::resetForTests();
    }

    private static function ensureLoaded(): void
    {
        if (self::$idsCache !== null) {
            return;
        }

        self::loadYamlTexts();
        self::$idsCache = HintCandidateProviderRegistry::allDeclaredEntities();
    }

    private static function loadYamlTexts(): void
    {
        if (self::$descriptionCache !== null) {
            return;
        }

        $config = AssistantMetadataLoader::load(ProductMetadataPaths::preprocessExtractionCategoriesFile());
        $raw = $config['categories'] ?? [];
        if (!is_array($raw)) {
            self::$descriptionCache = [];

            return;
        }

        $descriptions = [];
        foreach ($raw as $id => $desc) {
            $id = trim((string) $id);
            if ($id === '') {
                continue;
            }
            $descriptions[$id] = trim((string) $desc);
        }

        self::$descriptionCache = $descriptions;
    }
}
