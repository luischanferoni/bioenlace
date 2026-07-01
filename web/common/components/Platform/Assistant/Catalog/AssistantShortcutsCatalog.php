<?php

namespace common\components\Platform\Assistant\Catalog;

use Symfony\Component\Yaml\Yaml;
use Yii;

/**
 * Atajos del asistente desde {@see schemas/assistant-shortcuts.yaml}.
 */
final class AssistantShortcutsCatalog
{
    /** @var array<string, list<array{id: string, titulo: string, intent_ids: list<string>, subgroups: list<array{id: string, titulo: string, intent_ids: list<string>}>}>> */
    private static array $categoriesByCatalog = [];

    /**
     * @return list<array{id: string, titulo: string, intent_ids: list<string>, subgroups: list<array{id: string, titulo: string, intent_ids: list<string>}>}>
     */
    public static function categories(?string $catalogBasename = null): array
    {
        $catalogBasename = trim((string) ($catalogBasename ?? ''));
        if ($catalogBasename === '') {
            $catalogBasename = 'assistant-shortcuts.yaml';
        }

        if (isset(self::$categoriesByCatalog[$catalogBasename])) {
            return self::$categoriesByCatalog[$catalogBasename];
        }

        self::$categoriesByCatalog[$catalogBasename] = [];
        $path = \common\components\Platform\Core\Product\ProductMetadataPaths::assistantShortcutsFile($catalogBasename);
        if (!is_file($path)) {
            return self::$categoriesByCatalog[$catalogBasename];
        }
        try {
            $data = Yaml::parseFile($path);
        } catch (\Throwable $e) {
            Yii::warning('AssistantShortcutsCatalog: YAML inválido: ' . $e->getMessage(), __METHOD__);

            return self::$categoriesByCatalog[$catalogBasename];
        }
        if (!is_array($data)) {
            return self::$categoriesByCatalog[$catalogBasename];
        }
        $raw = $data['categories'] ?? [];
        if (!is_array($raw)) {
            return self::$categoriesByCatalog[$catalogBasename];
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
            self::$categoriesByCatalog[$catalogBasename][] = [
                'id' => $id,
                'titulo' => $titulo !== '' ? $titulo : $id,
                'intent_ids' => $intentIds,
                'subgroups' => $subgroups,
            ];
        }

        return self::$categoriesByCatalog[$catalogBasename];
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
        self::$categoriesByCatalog = [];
    }
}
