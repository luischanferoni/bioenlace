<?php

namespace common\components\Platform\Assistant\Preprocess;

use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;
use common\components\Platform\Core\Product\ProductMetadataPaths;

/**
 * Catálogo cerrado de routing_hint del preprocess (capa IA).
 *
 * Textos para la IA: {@see catalog/preprocess-routing-hints.yaml}.
 * Paths de decisión PHP ({@see PATH_*}) son vocabulario interno de handlers
 * mientras dura la migración a discovery unificado; no se piden al modelo.
 *
 * Hints canónicos: {@see GUIDE}, {@see FUERA_HIS}, {@see SIN_PEDIDO}.
 */
final class PreprocessRoutingHintCatalog
{
    /** Hints 1ª IA (lectura del mensaje). */
    public const GUIDE = 'guide';
    public const FUERA_HIS = 'fuera_his';
    public const SIN_PEDIDO = 'sin_pedido';

    /**
     * @deprecated Usar {@see GUIDE}.
     */
    public const PEDIDO_CLARO = self::GUIDE;

    /**
     * @deprecated Usar {@see GUIDE}.
     */
    public const PEDIDO_CLARO_MULTIPLE = self::GUIDE;

    /**
     * @deprecated Usar {@see FUERA_HIS}.
     */
    public const PEDIDO_FUERA_HIS = self::FUERA_HIS;

    /**
     * Paths de decisión PHP / routing_result (handlers).
     * No son hints de IA.
     */
    public const PATH_MATCH_DIRECT = 'clara';
    public const PATH_NEEDS_CONTEXT = 'incompletas';
    public const PATH_NO_ACTION = 'dudosa';
    public const PATH_OUTSIDE = 'fuera_de_his';

    public const TAG_IN_FLOW_QUESTION = 'in_flow_question';

    /**
     * Alias → hint canónico (YAML viejo, respuestas IA, tests).
     *
     * @var array<string, string>
     */
    private const HINT_ALIASES = [
        'pedido_claro' => self::GUIDE,
        'pedido_claro_multiple' => self::GUIDE,
        'pedido_fuera_his' => self::FUERA_HIS,
        'clara' => self::GUIDE,
        'incompletas' => self::GUIDE,
        'dudosa' => self::SIN_PEDIDO,
        'fuera_de_his' => self::FUERA_HIS,
        'directo' => self::GUIDE,
        'operational' => self::GUIDE,
        'in_flow_question' => self::GUIDE,
        'ambiguous' => self::SIN_PEDIDO,
    ];

    /**
     * @var array<string, string>
     */
    private const LEGACY_USER_GOAL_TO_HINT = [
        'guide' => self::GUIDE,
        'operational' => self::GUIDE,
        'in_flow_question' => self::GUIDE,
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
     * Hint IA → path PHP de handler.
     */
    public static function decisionPathFromHint(string $routingHint): string
    {
        $routingHint = self::applyAlias($routingHint);

        return match ($routingHint) {
            self::FUERA_HIS => self::PATH_OUTSIDE,
            self::SIN_PEDIDO => self::PATH_NO_ACTION,
            self::GUIDE => self::PATH_NEEDS_CONTEXT,
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

    /**
     * Hint o path → user_goal de hilo (guide | ambiguous | in_flow_question).
     */
    public static function legacyUserGoalFromRoutingHint(string $routingHintOrPath, bool $inFlowQuestion = false): string
    {
        if ($inFlowQuestion) {
            return self::TAG_IN_FLOW_QUESTION;
        }
        $raw = trim($routingHintOrPath);
        if ($raw === self::PATH_MATCH_DIRECT || $raw === self::PATH_NEEDS_CONTEXT) {
            return 'guide';
        }
        if ($raw === self::PATH_NO_ACTION || $raw === self::PATH_OUTSIDE) {
            return 'ambiguous';
        }

        $id = self::applyAlias($raw);
        if ($id === self::GUIDE) {
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
            $id = self::applyAlias(trim((string) $id));
            if ($id === '' || !self::isCanonicalHint($id)) {
                continue;
            }
            if (!in_array($id, $ids, true)) {
                $ids[] = $id;
            }
            $text = trim((string) $desc);
            if ($text !== '' && !isset($descriptions[$id])) {
                $descriptions[$id] = $text;
            }
        }

        self::$idsCache = $ids;
        self::$descriptionCache = $descriptions;
    }

    private static function isCanonicalHint(string $id): bool
    {
        return $id === self::GUIDE
            || $id === self::FUERA_HIS
            || $id === self::SIN_PEDIDO;
    }
}
