<?php

namespace common\components\Assistant\Catalog;

use Symfony\Component\Yaml\Yaml;
use Yii;

/**
 * Atajos del asistente desde {@see schemas/assistant-shortcuts.yaml}.
 */
final class AssistantShortcutsCatalog
{
    /** @var list<array{id: string, titulo: string, intent_ids: list<string>}>|null */
    private static ?array $categories = null;

    /**
     * @return list<array{id: string, titulo: string, intent_ids: list<string>}>
     */
    public static function categories(): array
    {
        if (self::$categories !== null) {
            return self::$categories;
        }

        self::$categories = [];
        $path = \common\components\Core\Product\ProductMetadataPaths::assistantShortcutsFile();
        if (!is_file($path)) {
            return self::$categories;
        }
        try {
            $data = Yaml::parseFile($path);
        } catch (\Throwable $e) {
            Yii::warning('AssistantShortcutsCatalog: YAML inválido: ' . $e->getMessage(), __METHOD__);

            return self::$categories;
        }
        if (!is_array($data)) {
            return self::$categories;
        }
        $raw = $data['categories'] ?? [];
        if (!is_array($raw)) {
            return self::$categories;
        }
        foreach ($raw as $cat) {
            if (!is_array($cat)) {
                continue;
            }
            $id = trim((string) ($cat['id'] ?? ''));
            $titulo = trim((string) ($cat['titulo'] ?? ''));
            $intentIds = [];
            foreach ($cat['intent_ids'] ?? [] as $iid) {
                if (is_string($iid) && trim($iid) !== '') {
                    $intentIds[] = trim($iid);
                }
            }
            if ($id === '' || $intentIds === []) {
                continue;
            }
            self::$categories[] = [
                'id' => $id,
                'titulo' => $titulo !== '' ? $titulo : $id,
                'intent_ids' => $intentIds,
            ];
        }

        return self::$categories;
    }

    public static function resetCacheForTests(): void
    {
        self::$categories = null;
    }
}
