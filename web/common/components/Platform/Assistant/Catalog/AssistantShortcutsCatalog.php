<?php

namespace common\components\Platform\Assistant\Catalog;

use Symfony\Component\Yaml\Yaml;
use Yii;

/**
 * Atajos del asistente desde {@see schemas/assistant-shortcuts.yaml}.
 */
final class AssistantShortcutsCatalog
{
    /** @var list<array{id: string, titulo: string, intent_ids: list<string>, subgroups: list<array{id: string, titulo: string, intent_ids: list<string>}>}>|null */
    private static ?array $categories = null;

    /**
     * @return list<array{id: string, titulo: string, intent_ids: list<string>, subgroups: list<array{id: string, titulo: string, intent_ids: list<string>}>}>
     */
    public static function categories(): array
    {
        if (self::$categories !== null) {
            return self::$categories;
        }

        self::$categories = [];
        $path = \common\components\Platform\Core\Product\ProductMetadataPaths::assistantShortcutsFile();
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
            if ($id === '') {
                continue;
            }
            $titulo = trim((string) ($cat['titulo'] ?? ''));
            $subgroups = self::parseSubgroups($cat['subgroups'] ?? []);
            $intentIds = self::parseIntentIds($cat['intent_ids'] ?? []);
            if ($subgroups === [] && $intentIds === []) {
                continue;
            }
            self::$categories[] = [
                'id' => $id,
                'titulo' => $titulo !== '' ? $titulo : $id,
                'intent_ids' => $intentIds,
                'subgroups' => $subgroups,
            ];
        }

        return self::$categories;
    }

    /**
     * @param mixed $raw
     *
     * @return list<array{id: string, titulo: string, intent_ids: list<string>}>
     */
    private static function parseSubgroups($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $subgroups = [];
        foreach ($raw as $sg) {
            if (!is_array($sg)) {
                continue;
            }
            $sgId = trim((string) ($sg['id'] ?? ''));
            $intentIds = self::parseIntentIds($sg['intent_ids'] ?? []);
            if ($sgId === '' || $intentIds === []) {
                continue;
            }
            $sgTitulo = trim((string) ($sg['titulo'] ?? ''));
            $subgroups[] = [
                'id' => $sgId,
                'titulo' => $sgTitulo !== '' ? $sgTitulo : $sgId,
                'intent_ids' => $intentIds,
            ];
        }

        return $subgroups;
    }

    /**
     * @param mixed $raw
     *
     * @return list<string>
     */
    private static function parseIntentIds($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $intentIds = [];
        foreach ($raw as $iid) {
            if (is_string($iid) && trim($iid) !== '') {
                $intentIds[] = trim($iid);
            }
        }

        return $intentIds;
    }

    public static function resetCacheForTests(): void
    {
        self::$categories = null;
    }
}
