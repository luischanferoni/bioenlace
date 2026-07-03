<?php

namespace common\components\Domain\Clinical\Service\EncounterJourney;

/**
 * Evalúa reglas declarativas de elegibilidad por fase del journey.
 */
final class EncounterJourneyEligibilityService
{
    private EncounterPhaseEligibilityCatalogService $catalog;

    public function __construct(?EncounterPhaseEligibilityCatalogService $catalog = null)
    {
        $this->catalog = $catalog ?? new EncounterPhaseEligibilityCatalogService();
    }

    /**
     * @param array<string, mixed> $context
     * @return array{
     *   applies: bool,
     *   skip_reason: string|null,
     *   label: string,
     *   surface: string|null,
     *   intent_id: string|null,
     *   action_id: string|null
     * }
     */
    public function evaluate(string $phaseId, array $context): array
    {
        $def = $this->catalog->phase($phaseId);
        if ($def === null) {
            return [
                'applies' => false,
                'skip_reason' => 'phase_unknown',
                'label' => '',
                'surface' => null,
                'intent_id' => null,
                'action_id' => null,
            ];
        }

        $skipReason = $this->firstSkipReason($def['skip_when_any'] ?? [], $context);
        if ($skipReason !== null) {
            return [
                'applies' => false,
                'skip_reason' => $skipReason,
                'label' => trim((string) ($def['label'] ?? '')),
                'surface' => $this->optionalString($def['surface'] ?? null),
                'intent_id' => $this->optionalString($def['intent_id'] ?? null),
                'action_id' => $this->optionalString($def['action_id'] ?? null),
            ];
        }

        return [
            'applies' => true,
            'skip_reason' => null,
            'label' => trim((string) ($def['label'] ?? '')),
            'surface' => $this->optionalString($def['surface'] ?? null),
            'intent_id' => $this->optionalString($def['intent_id'] ?? null),
            'action_id' => $this->optionalString($def['action_id'] ?? null),
        ];
    }

    /**
     * @param list<mixed> $rules
     * @param array<string, mixed> $context
     */
    private function firstSkipReason(array $rules, array $context): ?string
    {
        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }
            $field = trim((string) ($rule['field'] ?? ''));
            if ($field === '') {
                continue;
            }
            if ($this->ruleMatches($rule, $context[$field] ?? null)) {
                return $field . ':' . $this->ruleTag($rule);
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $rule
     */
    private function ruleMatches(array $rule, mixed $value): bool
    {
        if (!empty($rule['empty'])) {
            return $value === null || $value === '' || $value === 0;
        }
        if (!empty($rule['not_empty'])) {
            return $value !== null && $value !== '' && $value !== 0;
        }
        if (array_key_exists('equals', $rule)) {
            return $this->normalizeScalar($value) === $this->normalizeScalar($rule['equals']);
        }
        if (array_key_exists('not_equals', $rule)) {
            return $this->normalizeScalar($value) !== $this->normalizeScalar($rule['not_equals']);
        }
        if (isset($rule['in']) && is_array($rule['in'])) {
            $needle = $this->normalizeScalar($value);

            return in_array($needle, array_map([$this, 'normalizeScalar'], $rule['in']), true);
        }
        if (isset($rule['not_in']) && is_array($rule['not_in'])) {
            $needle = $this->normalizeScalar($value);

            return !in_array($needle, array_map([$this, 'normalizeScalar'], $rule['not_in']), true);
        }

        return false;
    }

    /**
     * @param array<string, mixed> $rule
     */
    private function ruleTag(array $rule): string
    {
        foreach (['empty', 'not_empty', 'equals', 'not_equals', 'in', 'not_in'] as $key) {
            if (array_key_exists($key, $rule)) {
                return $key;
            }
        }

        return 'match';
    }

    private function normalizeScalar(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return trim((string) $value);
    }

    private function optionalString(mixed $value): ?string
    {
        $s = trim((string) $value);

        return $s !== '' ? $s : null;
    }
}
