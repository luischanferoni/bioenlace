<?php

namespace common\components\Platform\Assistant\Planning;

use Yii;

/**
 * Telemetría de planificación por mensaje ({@see planning-log-v1.yaml}).
 */
final class AssistantPlanningLogService
{
    /** @var array<string, mixed>|null */
    private static ?array $current = null;

    public static function resetForTests(): void
    {
        self::$current = null;
    }

    /**
     * @param array<string, mixed> $firstIa
     * @param list<array{catalog_id: string, tool_id: string, score: int, routing_result: string}> $catalogMatches
     */
    public static function begin(array $firstIa, array $catalogMatches): void
    {
        self::$current = [
            'first_ia' => [
                'normalized_text' => trim((string) ($firstIa['normalized_text'] ?? '')),
                'necesidad_usuario' => trim((string) ($firstIa['necesidad_usuario'] ?? '')),
                'routing_hint' => trim((string) ($firstIa['routing_hint'] ?? '')),
                'tags' => is_array($firstIa['tags'] ?? null) ? array_values($firstIa['tags']) : [],
                'context_areas' => is_array($firstIa['context_areas'] ?? null) ? array_values($firstIa['context_areas']) : [],
                'extractions' => is_array($firstIa['extractions'] ?? null) ? $firstIa['extractions'] : [],
                'intent_ids_hint' => is_array($firstIa['intent_ids_hint'] ?? null) ? array_values($firstIa['intent_ids_hint']) : [],
            ],
            'catalog_matches' => array_slice($catalogMatches, 0, 8),
            'routing_result' => '',
            'declarative_plan' => null,
            'planner_invoked' => false,
            'planner_reason' => null,
            'planner_plan' => null,
            'executed_tools' => [],
            'final_path' => '',
            'gaps' => [],
        ];
    }

    public static function setRoutingResult(string $routingResult): void
    {
        self::ensure();
        self::$current['routing_result'] = trim($routingResult);
    }

    /**
     * @param list<string> $toolIds
     */
    public static function setDeclarativePlan(array $toolIds, string $reason, bool $needsPlanner): void
    {
        self::ensure();
        self::$current['declarative_plan'] = [
            'tool_ids' => array_values($toolIds),
            'reason' => trim($reason),
            'needs_planner' => $needsPlanner,
        ];
    }

    /**
     * @param list<string> $toolIdsOrdered
     */
    public static function setPlannerPlan(array $toolIdsOrdered, string $rationale, string $reason): void
    {
        self::ensure();
        self::$current['planner_invoked'] = true;
        self::$current['planner_reason'] = trim($reason);
        self::$current['planner_plan'] = [
            'tool_ids_ordered' => array_values($toolIdsOrdered),
            'rationale' => trim($rationale),
        ];
        self::computeGaps();
    }

    /**
     * @param array{tool_id: string, ms: int, chars: int, had_null_fields: bool} $row
     */
    public static function addExecutedTool(array $row): void
    {
        self::ensure();
        self::$current['executed_tools'][] = $row;
    }

    public static function setFinalPath(string $finalPath): void
    {
        self::ensure();
        self::$current['final_path'] = trim($finalPath);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function snapshot(): ?array
    {
        return self::$current;
    }

    /**
     * @param array<string, mixed> $envelope
     * @return array<string, mixed>
     */
    public static function attachDebugIfEnabled(array $envelope): array
    {
        if (!self::isDebugEnabled() || self::$current === null) {
            return $envelope;
        }

        $envelope['planning_applied'] = self::$current;

        return $envelope;
    }

    public static function flushToYiiLog(): void
    {
        if (self::$current === null) {
            return;
        }
        Yii::info(['planning_applied' => self::$current], 'asistente-planning');
    }

    public static function isDebugEnabled(): bool
    {
        return (bool) (Yii::$app->params['asistente_planning_debug'] ?? false);
    }

    private static function ensure(): void
    {
        if (self::$current === null) {
            self::$current = [
                'first_ia' => [],
                'catalog_matches' => [],
                'routing_result' => '',
                'declarative_plan' => null,
                'planner_invoked' => false,
                'planner_reason' => null,
                'planner_plan' => null,
                'executed_tools' => [],
                'final_path' => '',
                'gaps' => [],
            ];
        }
    }

    private static function computeGaps(): void
    {
        if (self::$current === null) {
            return;
        }
        $decl = self::$current['declarative_plan']['tool_ids'] ?? [];
        if (!is_array($decl)) {
            $decl = [];
        }
        $declSet = array_fill_keys(array_map('strval', $decl), true);
        $ordered = self::$current['planner_plan']['tool_ids_ordered'] ?? [];
        if (!is_array($ordered)) {
            self::$current['gaps'] = [];

            return;
        }
        $gaps = [];
        foreach ($ordered as $toolId) {
            if (!is_string($toolId)) {
                continue;
            }
            if (!isset($declSet[$toolId])) {
                $gaps[] = $toolId;
            }
        }
        self::$current['gaps'] = $gaps;
    }
}
