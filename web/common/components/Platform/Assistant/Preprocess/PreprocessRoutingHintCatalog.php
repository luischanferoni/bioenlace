<?php

namespace common\components\Platform\Assistant\Preprocess;

use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;
use common\components\Platform\Core\Product\ProductMetadataPaths;

/**
 * Catálogo cerrado de routing_hint del preprocess (capa IA).
 *
 * Textos para la IA: {@see catalog/preprocess-routing-hints.yaml}.
 * Paths de decisión PHP ({@see PATH_*}) son vocabulario interno del router/match;
 * no se piden al modelo ni viven en el YAML de hints.
 */
final class PreprocessRoutingHintCatalog
{
    /** Hints 1ª IA (lectura del mensaje). */
    public const PEDIDO_CLARO = 'pedido_claro';
    public const PEDIDO_CLARO_MULTIPLE = 'pedido_claro_multiple';
    public const SIN_PEDIDO = 'sin_pedido';
    public const PEDIDO_FUERA_HIS = 'pedido_fuera_his';

    /**
     * Paths de decisión PHP / routing_result (smart-catalog + handlers).
     * No son hints de IA.
     */
    public const PATH_MATCH_DIRECT = 'clara';
    public const PATH_NEEDS_CONTEXT = 'incompletas';
    public const PATH_NO_ACTION = 'dudosa';
    public const PATH_OUTSIDE = 'fuera_de_his';

    public const TAG_IN_FLOW_QUESTION = 'in_flow_question';

    /**
     * Alias de hints IA (respuestas viejas / tests).
     *
     * @var array<string, string>
     */
    private const HINT_ALIASES = [
        'clara' => self::PEDIDO_CLARO,
        'incompletas' => self::PEDIDO_CLARO,
        'dudosa' => self::SIN_PEDIDO,
        'fuera_de_his' => self::PEDIDO_FUERA_HIS,
        'directo' => self::PEDIDO_CLARO,
    ];

    /**
     * @var array<string, string>
     */
    private const LEGACY_USER_GOAL_TO_HINT = [
        'guide' => self::PEDIDO_CLARO,
        'operational' => self::PEDIDO_CLARO,
        'in_flow_question' => self::PEDIDO_CLARO,
        'ambiguous' => self::SIN_PEDIDO,
    ];

    /**
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

    /**
     * @return list<string>
     */
    public static function phpDecisionPaths(): array
    {
        return [
            self::PATH_MATCH_DIRECT,
            self::PATH_NEEDS_CONTEXT,
            self::PATH_NO_ACTION,
            self::PATH_OUTSIDE,
        ];
    }

    public static function isValid(string $id): bool
    {
        $id = self::applyAlias(trim($id));

        return $id !== '' && in_array($id, self::all(), true);
    }

    public static function isPhpDecisionPath(string $id): bool
    {
        $id = trim($id);

        return $id !== '' && in_array($id, self::phpDecisionPaths(), true);
    }

    public static function applyAlias(string $id): string
    {
        $id = trim($id);
        if ($id === '') {
            return '';
        }

        return self::HINT_ALIASES[$id] ?? $id;
    }

    public static function description(string $id): string
    {
        $id = self::applyAlias($id);
        if ($id === '') {
            return '';
        }
        self::loadCatalog();

        return self::$descriptionCache[$id] ?? '';
    }

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
     * Hint IA → path PHP cuando no hay match 100 % que mande.
     */
    public static function decisionPathFromHint(string $routingHint): string
    {
        $routingHint = self::applyAlias($routingHint);

        return match ($routingHint) {
            self::PEDIDO_FUERA_HIS => self::PATH_OUTSIDE,
            self::SIN_PEDIDO => self::PATH_NO_ACTION,
            self::PEDIDO_CLARO_MULTIPLE => self::PATH_NEEDS_CONTEXT,
            self::PEDIDO_CLARO => self::PATH_NEEDS_CONTEXT,
            default => self::PATH_NO_ACTION,
        };
    }

    /**
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
            return self::SIN_PEDIDO;
        }

        return self::LEGACY_USER_GOAL_TO_HINT[$goal] ?? self::SIN_PEDIDO;
    }

    public static function legacyUserGoalFromRoutingHint(string $routingHintOrPath, bool $inFlowQuestion = false): string
    {
        if ($inFlowQuestion) {
            return self::TAG_IN_FLOW_QUESTION;
        }
        $raw = trim($routingHintOrPath);
        // Paths PHP primero: no aplicar HINT_ALIASES (p. ej. incompletas ≠ pedido_claro).
        if ($raw === self::PATH_MATCH_DIRECT) {
            return 'operational';
        }
        if ($raw === self::PATH_NEEDS_CONTEXT) {
            return 'guide';
        }
        if ($raw === self::PATH_NO_ACTION || $raw === self::PATH_OUTSIDE) {
            return 'ambiguous';
        }

        $id = self::applyAlias($raw);
        if ($id === self::PEDIDO_CLARO) {
            return 'operational';
        }
        if ($id === self::PEDIDO_CLARO_MULTIPLE) {
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
