<?php

namespace common\components\Platform\Assistant\SubIntentEngine;

/**
 * Carga un intent declarado como statechart (`states`, `always`, `context`, `meta`).
 *
 * Durante la migración arma en memoria el recorrido que los lectores ya ejecutan
 * (`subintents`, `next`, `next_routing`). El YAML migrado no escribe esas claves.
 * Al cerrar la migración este compilador se borra y los lectores leen `states`.
 */
final class StatechartManifest
{
    /** @var list<string> */
    private const META_KEYS = [
        'provides',
        'requires',
        'review_prefilled',
        'open_ui',
        'open_ui_routing',
        'chooser',
        'hint',
        'flow_submit',
        'composer_capture',
        'terminal_without_submit',
        'flow_dismiss',
        'flow_actions',
    ];

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function apply(array $data): array
    {
        $states = $data['states'] ?? null;
        if (!is_array($states) || $states === []) {
            return $data;
        }

        $compiled = self::compileStates($states, self::initialId($data));
        if ($compiled === []) {
            return $data;
        }

        $data['subintents'] = $compiled;
        $contextKeys = self::contextKeys($data['context'] ?? null);
        if ($contextKeys !== []) {
            $extra = is_array($data['draft_keys_extra'] ?? null) ? $data['draft_keys_extra'] : [];
            foreach ($contextKeys as $key) {
                if (!in_array($key, $extra, true)) {
                    $extra[] = $key;
                }
            }
            $data['draft_keys_extra'] = $extra;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function initialId(array $data): string
    {
        $initial = $data['initial'] ?? '';

        return is_string($initial) ? trim($initial) : '';
    }

    /**
     * @param array<mixed, mixed> $states
     * @return list<array<string, mixed>>
     */
    private static function compileStates(array $states, string $initial): array
    {
        $steps = [];
        $initialStep = null;
        foreach ($states as $id => $state) {
            if (!is_string($id) || !is_array($state)) {
                continue;
            }
            $id = trim($id);
            if ($id === '') {
                continue;
            }
            $step = self::compileState($id, $state);
            if ($initial !== '' && $id === $initial) {
                $initialStep = $step;
                continue;
            }
            $steps[] = $step;
        }
        if ($initialStep !== null) {
            array_unshift($steps, $initialStep);
        }

        return $steps;
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private static function compileState(string $id, array $state): array
    {
        $step = ['id' => $id];
        $description = trim((string) ($state['description'] ?? ''));
        if ($description !== '') {
            $step['assistant_text'] = $description;
        }

        $meta = isset($state['meta']) && is_array($state['meta']) ? $state['meta'] : [];
        foreach (self::META_KEYS as $key) {
            if (array_key_exists($key, $meta)) {
                $step[$key] = $meta[$key];
            }
        }

        $isFinal = trim((string) ($state['type'] ?? '')) === 'final';
        if (!$isFinal) {
            self::compileAlways($state['always'] ?? null, $step);
        }

        return $step;
    }

    /**
     * @param mixed $always
     * @param array<string, mixed> $step
     */
    private static function compileAlways($always, array &$step): void
    {
        if (is_string($always)) {
            $target = trim($always);
            if ($target !== '') {
                $step['next'] = $target;
            }

            return;
        }
        if (!is_array($always) || $always === []) {
            return;
        }
        if (self::isTransition($always)) {
            $always = [$always];
        }

        $routing = [];
        foreach ($always as $row) {
            if (!is_array($row)) {
                continue;
            }
            $guard = isset($row['guard']) && is_array($row['guard']) ? $row['guard'] : [];
            $hasTarget = array_key_exists('target', $row);
            $target = $hasTarget ? trim((string) $row['target']) : '';
            if ($guard !== []) {
                $routing[] = [
                    'when' => ['draft_equals' => $guard],
                    'next' => $target,
                ];
                continue;
            }
            if (!$hasTarget) {
                continue;
            }
            $routing[] = [
                'when' => ['default' => true],
                'next' => $target,
            ];
        }
        if ($routing !== []) {
            $step['next_routing'] = $routing;
        }
    }

    /**
     * @param array<mixed, mixed> $row
     */
    private static function isTransition(array $row): bool
    {
        return array_key_exists('target', $row) || array_key_exists('guard', $row);
    }

    /**
     * @param mixed $context
     * @return list<string>
     */
    private static function contextKeys($context): array
    {
        if (!is_array($context)) {
            return [];
        }
        $keys = [];
        foreach ($context as $key => $value) {
            if (is_string($value) && trim($value) !== '' && is_int($key)) {
                $keys[] = trim($value);
                continue;
            }
            if (is_string($key) && $key !== '') {
                $keys[] = $key;
            }
        }

        return array_values(array_unique($keys));
    }
}
