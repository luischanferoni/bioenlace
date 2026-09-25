<?php

namespace common\components\Platform\Assistant\SubIntentEngine;

/**
 * Lee un intent statechart (`states`, `always`, `context`, `meta`).
 *
 * Cada estado se presenta con `id`, `label` como texto del paso y las
 * claves de `meta` en el primer nivel, para el motor y el manifiesto de UI.
 */
final class FlowStatechart
{
    /**
     * @param array<string, mixed> $intent
     * @return list<array<string, mixed>>
     */
    public static function ordered(array $intent): array
    {
        $map = self::map($intent);
        if ($map === []) {
            return [];
        }
        $initial = self::initialId($intent, $map);
        $out = [];
        if ($initial !== '' && isset($map[$initial])) {
            $out[] = self::present($initial, $map[$initial]);
        }
        foreach ($map as $id => $state) {
            if ($id === $initial) {
                continue;
            }
            $out[] = self::present($id, $state);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $intent
     * @return array<string, mixed>|null
     */
    public static function find(array $intent, string $id): ?array
    {
        $id = trim($id);
        $map = self::map($intent);
        if ($id === '' || !isset($map[$id]) || !is_array($map[$id])) {
            return null;
        }

        return self::present($id, $map[$id]);
    }

    /**
     * @param array<string, mixed> $intent
     * @return list<string>
     */
    public static function contextKeys(array $intent): array
    {
        $context = $intent['context'] ?? null;
        if (!is_array($context)) {
            return [];
        }
        $keys = [];
        foreach ($context as $key => $value) {
            if (is_int($key) && is_string($value) && trim($value) !== '') {
                $keys[] = trim($value);
                continue;
            }
            if (is_string($key) && $key !== '') {
                $keys[] = $key;
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * Siguiente estado según `always` y el contexto (draft).
     *
     * @param array<string, mixed> $state estado ya presentado
     * @param array<string, mixed> $draft
     */
    public static function resolveNext(array $state, array $draft): string
    {
        if (self::isFinalType($state)) {
            return '';
        }
        $always = $state['always'] ?? null;
        if (is_string($always)) {
            return trim($always);
        }
        if (!is_array($always) || $always === []) {
            return '';
        }
        if (self::isTransitionRow($always)) {
            $always = [$always];
        }

        $fallback = '';
        $hasFallback = false;
        foreach ($always as $row) {
            if (!is_array($row)) {
                continue;
            }
            $guard = isset($row['guard']) && is_array($row['guard']) ? $row['guard'] : [];
            $hasTarget = array_key_exists('target', $row);
            $target = $hasTarget ? trim((string) $row['target']) : '';
            if ($guard === [] && !$hasTarget) {
                continue;
            }
            if ($guard !== []) {
                if (self::draftEquals($draft, $guard)) {
                    return $target;
                }
                continue;
            }
            $fallback = $target;
            $hasFallback = true;
        }

        return $hasFallback ? $fallback : '';
    }

    /**
     * @param array<string, mixed> $state
     */
    public static function hasOutgoing(array $state): bool
    {
        if (self::isFinalType($state)) {
            return false;
        }
        $always = $state['always'] ?? null;
        if (is_string($always)) {
            return trim($always) !== '';
        }

        return is_array($always) && $always !== [];
    }

    /**
     * Destino lineal para el manifiesto de UI: `always` string, el comodín, o el primer target.
     *
     * @param array<string, mixed> $state
     */
    public static function linearTarget(array $state): string
    {
        if (self::isFinalType($state)) {
            return '';
        }
        $always = $state['always'] ?? null;
        if (is_string($always)) {
            return trim($always);
        }
        if (!is_array($always) || $always === []) {
            return '';
        }
        if (self::isTransitionRow($always)) {
            $always = [$always];
        }
        $first = '';
        foreach ($always as $row) {
            if (!is_array($row)) {
                continue;
            }
            $target = trim((string) ($row['target'] ?? ''));
            $guard = isset($row['guard']) && is_array($row['guard']) ? $row['guard'] : [];
            if ($guard === [] && $target !== '') {
                return $target;
            }
            if ($first === '' && $target !== '') {
                $first = $target;
            }
        }

        return $first;
    }

    /**
     * @param array<string, mixed> $intent
     * @return array<string, array<string, mixed>>
     */
    private static function map(array $intent): array
    {
        $states = $intent['states'] ?? null;
        if (!is_array($states)) {
            return [];
        }
        $map = [];
        foreach ($states as $id => $state) {
            if (!is_string($id) || !is_array($state)) {
                continue;
            }
            $id = trim($id);
            if ($id === '') {
                continue;
            }
            $map[$id] = $state;
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $intent
     * @param array<string, array<string, mixed>> $map
     */
    private static function initialId(array $intent, array $map): string
    {
        $initial = trim((string) ($intent['initial'] ?? ''));
        if ($initial !== '' && isset($map[$initial])) {
            return $initial;
        }
        foreach ($map as $id => $state) {
            unset($state);

            return $id;
        }

        return '';
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private static function present(string $id, array $state): array
    {
        $view = $state;
        $view['id'] = $id;
        $label = trim((string) ($state['label'] ?? ''));
        if ($label === '') {
            $label = trim((string) ($state['description'] ?? ''));
        }
        if ($label !== '') {
            $view['label'] = $label;
            $view['assistant_text'] = $label;
        }
        $meta = isset($state['meta']) && is_array($state['meta']) ? $state['meta'] : [];
        foreach ($meta as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }
            $view[$key] = $value;
        }

        return $view;
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function isFinalType(array $state): bool
    {
        return trim((string) ($state['type'] ?? '')) === 'final';
    }

    /**
     * @param array<mixed, mixed> $row
     */
    private static function isTransitionRow(array $row): bool
    {
        return array_key_exists('target', $row) || array_key_exists('guard', $row);
    }

    /**
     * @param array<string, mixed> $draft
     * @param array<mixed, mixed> $expected
     */
    private static function draftEquals(array $draft, array $expected): bool
    {
        foreach ($expected as $field => $value) {
            $key = is_string($field) ? trim($field) : '';
            if ($key === '') {
                continue;
            }
            $current = isset($draft[$key]) ? trim((string) $draft[$key]) : '';
            $wanted = '';
            if (is_string($value)) {
                $wanted = trim($value);
            } elseif (is_int($value) || is_float($value)) {
                $wanted = trim((string) $value);
            } elseif ($value === true) {
                $wanted = '1';
            } elseif ($value === false) {
                $wanted = '0';
            }
            if ($current !== $wanted) {
                return false;
            }
        }

        return true;
    }
}
