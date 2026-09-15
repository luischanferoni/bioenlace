<?php

namespace common\components\Platform\Core\Product;

/**
 * Carga políticas (knobs) de agentes desde {@see AgentPolicyRegistry} (PHP en Application del BC).
 * Gates hard e integridad siguen en el dominio; la ausencia de policy no los desactiva.
 *
 * @see web/docs/decisions/ddd-bounded-contexts-capas-y-metadata.md
 */
final class AutonomousAgentMetadata
{
    /** @var array<string, array<string, mixed>>|null */
    private static ?array $cache = null;

    public static function resetCacheForTests(): void
    {
        self::$cache = null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function loadAgent(string $agentId): ?array
    {
        if (self::$cache === null) {
            self::$cache = [];
        }
        if (array_key_exists($agentId, self::$cache)) {
            $cached = self::$cache[$agentId];

            return $cached === [] ? null : $cached;
        }

        $data = AgentPolicyRegistry::config($agentId);
        if ($data === null) {
            self::$cache[$agentId] = [];

            return null;
        }

        self::$cache[$agentId] = $data;

        return $data;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function rulesForAgent(string $agentId): array
    {
        $config = self::loadAgent($agentId);
        if ($config === null) {
            return [];
        }

        $rules = [];
        foreach ($config['rules'] ?? [] as $rule) {
            if (is_array($rule) && isset($rule['id'], $rule['action'])) {
                $rules[] = $rule;
            }
        }

        return $rules;
    }
}
