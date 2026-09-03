<?php

namespace common\components\Platform\Assistant\Preprocess;

use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;
use common\components\Platform\Core\Product\ProductMetadataPaths;

/**
 * Catálogo cerrado de routing_hint del preprocess.
 *
 * Definiciones: {@see catalog/preprocess-routing-hints.yaml}.
 */
final class PreprocessRoutingHintCatalog
{
    /** @var list<string>|null */
    private static ?array $idsCache = null;

    /** @var array<string, string>|null */
    private static ?array $descriptionCache = null;

    /** @var array<string, string>|null */
    private static ?array $legacyGoalCache = null;

    /** @var list<string>|null */
    private static ?array $extraTagsCache = null;

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

    /**
     * @deprecated Alias legacy user_goal del preprocess.
     *
     * @return list<string>
     */
    public static function legacyGoals(): array
    {
        self::loadCatalog();

        return array_keys(self::$legacyGoalCache ?? []);
    }

    public static function routingHintFromLegacyGoal(string $goal): string
    {
        self::loadCatalog();
        $goal = trim($goal);
        if ($goal === '') {
            return 'dudosa';
        }
        if (isset(self::$legacyGoalCache[$goal])) {
            return self::$legacyGoalCache[$goal];
        }

        return 'dudosa';
    }

    public static function legacyUserGoalFromRoutingHint(string $routingHint, bool $inFlowQuestion = false): string
    {
        if ($inFlowQuestion) {
            return 'in_flow_question';
        }
        $routingHint = trim($routingHint);
        if ($routingHint === 'clara') {
            return 'operational';
        }
        if ($routingHint === 'incompletas' || $routingHint === 'directo') {
            return 'guide';
        }

        return 'ambiguous';
    }

    /**
     * @return list<string>
     */
    public static function extraPreprocessTags(): array
    {
        self::loadCatalog();

        return self::$extraTagsCache ?? [];
    }

    public static function resetCacheForTests(): void
    {
        self::$idsCache = null;
        self::$descriptionCache = null;
        self::$legacyGoalCache = null;
        self::$extraTagsCache = null;
    }

    private static function loadCatalog(): void
    {
        if (self::$idsCache !== null) {
            return;
        }

        $config = AssistantMetadataLoader::load(ProductMetadataPaths::preprocessRoutingHintsFile());
        $rawHints = $config['hints'] ?? [];
        if (!is_array($rawHints)) {
            self::$idsCache = [];
            self::$descriptionCache = [];
            self::$legacyGoalCache = [];
            self::$extraTagsCache = [];

            return;
        }

        $ids = [];
        $descriptions = [];
        foreach ($rawHints as $id => $desc) {
            $id = trim((string) $id);
            if ($id === '') {
                continue;
            }
            $ids[] = $id;
            $descriptions[$id] = trim((string) $desc);
        }

        $legacy = [];
        $rawLegacy = $config['legacy_user_goal'] ?? [];
        if (is_array($rawLegacy)) {
            foreach ($rawLegacy as $goal => $hint) {
                $goal = trim((string) $goal);
                $hint = trim((string) $hint);
                if ($goal === '' || $hint === '') {
                    continue;
                }
                $legacy[$goal] = $hint;
            }
        }

        $extraTags = [];
        $rawExtra = $config['extra_preprocess_tags'] ?? [];
        if (is_array($rawExtra)) {
            foreach ($rawExtra as $tag) {
                if (is_string($tag) && trim($tag) !== '') {
                    $extraTags[] = trim($tag);
                }
            }
        }

        self::$idsCache = $ids;
        self::$descriptionCache = $descriptions;
        self::$legacyGoalCache = $legacy;
        self::$extraTagsCache = $extraTags;
    }
}
