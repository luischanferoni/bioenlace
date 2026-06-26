<?php

namespace common\components\Platform\Core\Product;

use Symfony\Component\Yaml\Yaml;
use Yii;

/**
 * Carga políticas de agentes autónomos desde {@see ProductMetadataPaths::autonomousAgentsDir()}.
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

        $path = ProductMetadataPaths::autonomousAgentFile($agentId);
        if (!is_file($path)) {
            self::$cache[$agentId] = [];

            return null;
        }

        try {
            $data = Yaml::parseFile($path);
        } catch (\Throwable $e) {
            Yii::warning('AutonomousAgentMetadata: YAML inválido (' . $agentId . '): ' . $e->getMessage(), __METHOD__);
            self::$cache[$agentId] = [];

            return null;
        }

        if (!is_array($data)) {
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
