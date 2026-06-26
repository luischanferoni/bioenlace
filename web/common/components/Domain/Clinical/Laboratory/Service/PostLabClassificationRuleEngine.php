<?php

namespace common\components\Domain\Clinical\Laboratory\Service;

use common\components\Platform\Core\Product\AutonomousAgentMetadata;

/**
 * Evalúa reglas LOINC/umbrales para {@see PostLabClassificationAgent}.
 */
final class PostLabClassificationRuleEngine
{
    /** @var list<string> */
    private const DEFAULT_SEVERITY_ORDER = ['normal', 'control', 'critical'];

    /**
     * @param list<array<string, mixed>> $observations Filas normalizadas (loinc, value, unit, display, interpretation)
     * @return array{
     *   severity: string,
     *   matched_rules: list<array<string, mixed>>,
     *   triggering_observation: array<string, mixed>|null
     * }
     */
    public static function classify(array $observations, ?array $config = null): array
    {
        $config = $config ?? AutonomousAgentMetadata::loadAgent(PostLabClassificationAgent::AGENT_ID) ?? [];
        $rules = is_array($config['analyte_rules'] ?? null) ? $config['analyte_rules'] : [];
        $defaultSeverity = (string) ($config['default_severity'] ?? 'normal');
        $order = self::severityOrder($config);

        $matched = [];
        $worstSeverity = $defaultSeverity;
        $worstRank = self::severityRank($worstSeverity, $order);
        $triggering = null;

        foreach ($observations as $obs) {
            $loinc = self::normalizeLoinc((string) ($obs['loinc'] ?? ''));
            if ($loinc === '') {
                continue;
            }
            foreach ($rules as $rule) {
                if (!is_array($rule)) {
                    continue;
                }
                if (self::normalizeLoinc((string) ($rule['loinc'] ?? '')) !== $loinc) {
                    continue;
                }
                if (!self::observationMatchesWhen($obs, $rule['when'] ?? null)) {
                    continue;
                }
                $severity = (string) ($rule['severity'] ?? 'control');
                $rank = self::severityRank($severity, $order);
                $hit = [
                    'rule_id' => (string) ($rule['id'] ?? ''),
                    'severity' => $severity,
                    'loinc' => $loinc,
                    'observation' => $obs,
                ];
                $matched[] = $hit;
                if ($rank >= $worstRank) {
                    $worstRank = $rank;
                    $worstSeverity = $severity;
                    $triggering = $obs;
                }
            }
        }

        return [
            'severity' => $worstSeverity,
            'matched_rules' => $matched,
            'triggering_observation' => $triggering,
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @return list<string>
     */
    private static function severityOrder(array $config): array
    {
        $order = $config['severity_order'] ?? self::DEFAULT_SEVERITY_ORDER;
        if (!is_array($order) || $order === []) {
            return self::DEFAULT_SEVERITY_ORDER;
        }

        return array_values(array_map('strval', $order));
    }

    /**
     * @param list<string> $order
     */
    private static function severityRank(string $severity, array $order): int
    {
        $idx = array_search(strtolower($severity), array_map('strtolower', $order), true);

        return $idx === false ? 0 : (int) $idx;
    }

    private static function normalizeLoinc(string $code): string
    {
        return trim($code);
    }

    /**
     * @param array<string, mixed> $obs
     * @param mixed $when
     */
    private static function observationMatchesWhen(array $obs, $when): bool
    {
        if (!is_array($when)) {
            return true;
        }

        if (isset($when['any']) && is_array($when['any'])) {
            foreach ($when['any'] as $clause) {
                if (is_array($clause) && self::clauseMatches($obs, $clause)) {
                    return true;
                }
            }

            return false;
        }

        if (isset($when['all']) && is_array($when['all'])) {
            foreach ($when['all'] as $clause) {
                if (!is_array($clause) || !self::clauseMatches($obs, $clause)) {
                    return false;
                }
            }

            return $when['all'] !== [];
        }

        return self::clauseMatches($obs, $when);
    }

    /**
     * @param array<string, mixed> $obs
     * @param array<string, mixed> $clause
     */
    private static function clauseMatches(array $obs, array $clause): bool
    {
        if (isset($clause['interpretation_in']) && is_array($clause['interpretation_in'])) {
            $interp = strtoupper(trim((string) ($obs['interpretation'] ?? '')));
            foreach ($clause['interpretation_in'] as $code) {
                if ($interp === strtoupper(trim((string) $code))) {
                    return true;
                }
            }

            return false;
        }

        $value = $obs['value'] ?? null;
        if (!is_numeric($value)) {
            return false;
        }

        if (isset($clause['gte'])) {
            return (float) $value >= (float) $clause['gte'];
        }
        if (isset($clause['lte'])) {
            return (float) $value <= (float) $clause['lte'];
        }
        if (isset($clause['gt'])) {
            return (float) $value > (float) $clause['gt'];
        }
        if (isset($clause['lt'])) {
            return (float) $value < (float) $clause['lt'];
        }

        return false;
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>|null
     */
    public static function outcomeConfig(array $config, string $severity): ?array
    {
        $outcomes = $config['outcomes'] ?? [];
        if (!is_array($outcomes)) {
            return null;
        }
        $key = strtolower($severity);
        $row = $outcomes[$key] ?? $outcomes['normal'] ?? null;

        return is_array($row) ? $row : null;
    }
}
