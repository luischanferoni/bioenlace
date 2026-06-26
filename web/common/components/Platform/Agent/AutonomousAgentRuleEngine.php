<?php

namespace common\components\Platform\Agent;

/**
 * Evalúa reglas declarativas YAML contra hechos (respuestas de formulario, analitos, etc.).
 */
final class AutonomousAgentRuleEngine
{
    /**
     * @param list<array<string, mixed>> $rules
     * @param array<string, mixed> $facts
     * @return list<array<string, mixed>>
     */
    public static function matchAll(array $rules, array $facts, ?string $formKind = null): array
    {
        $matched = [];
        foreach ($rules as $rule) {
            if (!self::ruleAppliesToFormKind($rule, $formKind)) {
                continue;
            }
            if (self::evaluateWhen($rule['when'] ?? null, $facts)) {
                $matched[] = $rule;
            }
        }

        return $matched;
    }

    /**
     * @param array<string, mixed> $rule
     */
    private static function ruleAppliesToFormKind(array $rule, ?string $formKind): bool
    {
        $kinds = $rule['form_kinds'] ?? null;
        if (!is_array($kinds) || $kinds === []) {
            return true;
        }
        if ($formKind === null || trim($formKind) === '') {
            return false;
        }
        $normalized = strtolower(trim($formKind));

        foreach ($kinds as $kind) {
            if (is_string($kind) && strtolower(trim($kind)) === $normalized) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param mixed $when
     * @param array<string, mixed> $facts
     */
    private static function evaluateWhen($when, array $facts): bool
    {
        if (!is_array($when)) {
            return false;
        }

        if (isset($when['any']) && is_array($when['any'])) {
            foreach ($when['any'] as $clause) {
                if (is_array($clause) && self::evaluateClause($clause, $facts)) {
                    return true;
                }
            }

            return false;
        }

        if (isset($when['all']) && is_array($when['all'])) {
            foreach ($when['all'] as $clause) {
                if (!is_array($clause) || !self::evaluateClause($clause, $facts)) {
                    return false;
                }
            }

            return $when['all'] !== [];
        }

        return self::evaluateClause($when, $facts);
    }

    /**
     * @param array<string, mixed> $clause
     * @param array<string, mixed> $facts
     */
    private static function evaluateClause(array $clause, array $facts): bool
    {
        $field = (string) ($clause['field'] ?? '');
        if ($field === '' || !array_key_exists($field, $facts)) {
            return false;
        }

        $actual = $facts[$field];
        if (array_key_exists('equals', $clause)) {
            return self::normalizeScalar($actual) === self::normalizeScalar($clause['equals']);
        }
        if (array_key_exists('not_equals', $clause)) {
            return self::normalizeScalar($actual) !== self::normalizeScalar($clause['not_equals']);
        }
        if (array_key_exists('gte', $clause)) {
            return is_numeric($actual) && (float) $actual >= (float) $clause['gte'];
        }
        if (array_key_exists('lte', $clause)) {
            return is_numeric($actual) && (float) $actual <= (float) $clause['lte'];
        }
        if (array_key_exists('in', $clause) && is_array($clause['in'])) {
            $needle = self::normalizeScalar($actual);
            foreach ($clause['in'] as $item) {
                if ($needle === self::normalizeScalar($item)) {
                    return true;
                }
            }

            return false;
        }

        return false;
    }

    /**
     * @param mixed $value
     */
    private static function normalizeScalar($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return strtolower(trim((string) $value));
    }
}
