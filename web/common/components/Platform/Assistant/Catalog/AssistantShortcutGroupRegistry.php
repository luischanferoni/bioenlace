<?php

namespace common\components\Platform\Assistant\Catalog;

use common\components\Platform\Core\Product\ProductMetadataPaths;
use common\models\Platform\AssistantShortcutGroup;
use Symfony\Component\Yaml\Yaml;
use Yii;

/**
 * Registro de etiquetas/orden de grupos de atajos (BD → fallback YAML).
 */
final class AssistantShortcutGroupRegistry
{
    private const CACHE_KEY = 'assistant_shortcut_group_registry_v2';

    private const CACHE_TTL = 3600;

    /** @var array{labels: array<string, string>, order: list<string>}|null */
    private static ?array $memory = null;

    /**
     * @return array{labels: array<string, string>, order: list<string>}
     */
    public static function snapshot(): array
    {
        if (self::$memory !== null) {
            return self::$memory;
        }

        if (Yii::$app->has('cache') && Yii::$app->cache !== null) {
            $cached = Yii::$app->cache->get(self::CACHE_KEY);
            if (is_array($cached)
                && isset($cached['labels'], $cached['order'])
                && is_array($cached['labels'])
                && is_array($cached['order'])) {
                self::$memory = [
                    'labels' => $cached['labels'],
                    'order' => array_values($cached['order']),
                ];

                return self::$memory;
            }
        }

        $fromDb = self::loadFromDatabase();
        if ($fromDb['labels'] !== []) {
            self::storeCache($fromDb);

            return $fromDb;
        }

        $fromYaml = self::loadFromYamlFallback();
        self::storeCache($fromYaml);

        return $fromYaml;
    }

    public static function invalidateCache(): void
    {
        self::$memory = null;
        if (Yii::$app->has('cache') && Yii::$app->cache !== null) {
            Yii::$app->cache->delete(self::CACHE_KEY);
        }
    }

    public static function resetCacheForTests(): void
    {
        self::invalidateCache();
    }

    /**
     * @return list<AssistantShortcutGroup>
     */
    public static function allOrdered(): array
    {
        if (!self::tableExists()) {
            return [];
        }

        return AssistantShortcutGroup::find()
            ->orderBy(['sort_order' => SORT_ASC, 'group_id' => SORT_ASC])
            ->all();
    }

    /**
     * @return array{labels: array<string, string>, order: list<string>}
     */
    public static function loadFromDatabase(): array
    {
        if (!self::tableExists()) {
            return ['labels' => [], 'order' => []];
        }

        $labels = [];
        $order = [];
        foreach (self::allOrdered() as $row) {
            $groupId = trim((string) $row->group_id);
            $label = trim((string) $row->label);
            if ($groupId === '' || $label === '') {
                continue;
            }
            $labels[$groupId] = $label;
            $order[] = $groupId;
        }

        return ['labels' => $labels, 'order' => $order];
    }

    /**
     * @return array{labels: array<string, string>, order: list<string>}
     */
    public static function loadFromYamlFallback(): array
    {
        $path = ProductMetadataPaths::assistantShortcutsFile('assistant-shortcut-group-labels.yaml');
        if (!is_file($path)) {
            return ['labels' => [], 'order' => []];
        }

        try {
            $data = Yaml::parseFile($path);
        } catch (\Throwable $e) {
            Yii::warning('AssistantShortcutGroupRegistry: YAML inválido: ' . $e->getMessage(), __METHOD__);

            return ['labels' => [], 'order' => []];
        }

        if (!is_array($data)) {
            return ['labels' => [], 'order' => []];
        }

        $labels = [];
        foreach ($data['labels'] ?? [] as $key => $label) {
            if (is_string($key) && trim($key) !== '' && is_string($label) && trim($label) !== '') {
                $labels[trim($key)] = trim($label);
            }
        }

        $order = [];
        foreach ($data['group_order'] ?? [] as $groupId) {
            if (is_string($groupId) && trim($groupId) !== '') {
                $order[] = trim($groupId);
            }
        }

        return ['labels' => $labels, 'order' => $order];
    }

    /**
     * Repone filas desde el YAML de respaldo (idempotente por group_id).
     *
     * @return array{inserted: int, updated: int, skipped: int}
     */
    public static function reseedFromYamlFallback(): array
    {
        if (!self::tableExists()) {
            throw new \RuntimeException('Tabla assistant_shortcut_group no disponible.');
        }

        $yaml = self::loadFromYamlFallback();
        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $sort = 0;

        $orderIndex = [];
        foreach ($yaml['order'] as $i => $groupId) {
            $orderIndex[$groupId] = ($i + 1) * 10;
        }

        $allGroupIds = array_unique(array_merge(
            array_keys($yaml['labels']),
            $yaml['order']
        ));

        foreach ($allGroupIds as $groupId) {
            $label = trim((string) ($yaml['labels'][$groupId] ?? ''));
            if ($label === '') {
                $skipped++;
                continue;
            }
            $sortOrder = $orderIndex[$groupId] ?? ($sort + 10);
            $sort = max($sort, $sortOrder);

            $row = AssistantShortcutGroup::findOne(['group_id' => $groupId]);
            if ($row === null) {
                $row = new AssistantShortcutGroup();
                $row->group_id = $groupId;
                $inserted++;
            } else {
                $updated++;
            }
            $row->label = $label;
            $row->sort_order = $sortOrder;
            if (!$row->save()) {
                throw new \RuntimeException('No se pudo guardar grupo «' . $groupId . '».');
            }
        }

        self::invalidateCache();

        return [
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }

    private static function tableExists(): bool
    {
        if (!Yii::$app->has('db')) {
            return false;
        }

        return Yii::$app->db->schema->getTableSchema(AssistantShortcutGroup::tableName(), true) !== null;
    }

    /**
     * @param array{labels: array<string, string>, order: list<string>} $snapshot
     */
    private static function storeCache(array $snapshot): void
    {
        self::$memory = $snapshot;
        if (Yii::$app->has('cache') && Yii::$app->cache !== null) {
            Yii::$app->cache->set(self::CACHE_KEY, $snapshot, self::CACHE_TTL);
        }
    }
}
