<?php

namespace common\components\Platform\Assistant\Preprocess;

use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;
use common\components\Platform\Core\Product\ProductMetadataPaths;

/**
 * Catálogo cerrado de routing_hint del preprocess.
 *
 * Textos para la IA: {@see catalog/preprocess-routing-hints.yaml}.
 * Alias, mapas legacy y tags extra: constantes de esta clase (no YAML).
 */
final class PreprocessRoutingHintCatalog
{
    public const CLARA = 'clara';
    public const INCOMPLETAS = 'incompletas';
    public const DUDOSA = 'dudosa';
    public const FUERA_DE_HIS = 'fuera_de_his';

    /** Alias preprocess (IA o tests legacy) → hint canónico. */
    private const ALIASES = [
        'directo' => self::CLARA,
    ];

    /**
     * Alias legacy user_goal → routing_hint (transición preprocess v1).
     *
     * @var array<string, string>
     */
    private const LEGACY_USER_GOAL_TO_HINT = [
        'guide' => self::INCOMPLETAS,
        'operational' => self::CLARA,
        'in_flow_question' => self::CLARA,
        'ambiguous' => self::DUDOSA,
    ];

    /** Tag preprocess no derivado del smart-catalog. */
    public const TAG_IN_FLOW_QUESTION = 'in_flow_question';

    /**
     * Tags que la 1ª IA puede emitir aunque no estén en smart-catalog triggers.
     *
     * @var list<string>
     */
    private const EXTRA_PREPROCESS_TAGS = [
        self::TAG_IN_FLOW_QUESTION,
    ];

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
        $id = self::applyAlias(trim($id));

        return $id !== '' && in_array($id, self::all(), true);
    }

    public static function applyAlias(string $id): string
    {
        $id = trim($id);
        if ($id === '') {
            return '';
        }

        return self::ALIASES[$id] ?? $id;
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
        return array_keys(self::LEGACY_USER_GOAL_TO_HINT);
    }

    public static function routingHintFromLegacyGoal(string $goal): string
    {
        $goal = trim($goal);
        if ($goal === '') {
            return self::DUDOSA;
        }

        return self::LEGACY_USER_GOAL_TO_HINT[$goal] ?? self::DUDOSA;
    }

    public static function legacyUserGoalFromRoutingHint(string $routingHint, bool $inFlowQuestion = false): string
    {
        if ($inFlowQuestion) {
            return self::TAG_IN_FLOW_QUESTION;
        }
        $routingHint = trim($routingHint);
        if ($routingHint === self::CLARA) {
            return 'operational';
        }
        if ($routingHint === self::INCOMPLETAS) {
            return 'guide';
        }

        return 'ambiguous';
    }

    /**
     * @return list<string>
     */
    public static function extraPreprocessTags(): array
    {
        return self::EXTRA_PREPROCESS_TAGS;
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

        $config = AssistantMetadataLoader::load(ProductMetadataPaths::preprocessRoutingHintsFile());
        $rawHints = $config['hints'] ?? [];
        if (!is_array($rawHints)) {
            self::$idsCache = [];
            self::$descriptionCache = [];

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

        self::$idsCache = $ids;
        self::$descriptionCache = $descriptions;
    }
}
