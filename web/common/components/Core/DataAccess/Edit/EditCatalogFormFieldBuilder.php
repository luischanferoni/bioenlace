<?php

namespace common\components\Core\DataAccess\Edit;

use common\components\Core\DataAccess\AttributeGroupCatalog;

/**
 * Convierte definiciones de atributos del catálogo data-access-config en campos ui_json.
 */
final class EditCatalogFormFieldBuilder
{
    private AttributeGroupCatalog $catalog;

    public function __construct(?AttributeGroupCatalog $catalog = null)
    {
        $this->catalog = $catalog ?? new AttributeGroupCatalog();
    }

    /**
     * @param array<string, mixed> $aspectDef
     * @param array<string, string> $prefill
     * @param array<string, int|string> $context
     * @return list<array<string, mixed>>
     */
    public function buildUiFieldsForAspect(
        string $aspectId,
        array $aspectDef,
        array $prefill,
        array $context = []
    ): array {
        $groupKey = trim((string) ($aspectDef['attribute_group'] ?? ''));
        if ($groupKey === '') {
            return [];
        }

        $definitions = $this->catalog->getEntityGroupFieldDefinitions($groupKey);
        if ($definitions === []) {
            return [];
        }

        $filter = $aspectDef['fields'] ?? null;
        if (is_array($filter) && $filter !== []) {
            $allowed = [];
            foreach ($filter as $name) {
                $key = trim((string) $name);
                if ($key !== '' && isset($definitions[$key])) {
                    $allowed[$key] = $definitions[$key];
                }
            }
            $definitions = $allowed;
        }

        $out = [];
        foreach ($definitions as $name => $def) {
            if (!is_string($name) || !is_array($def)) {
                continue;
            }
            if (($def['form'] ?? true) === false) {
                continue;
            }
            $field = $this->toUiJsonField($name, $def, $prefill, $context);
            if ($field !== null) {
                $field['meta'] = [
                    'aspect_id' => $aspectId,
                    'attribute_group' => $groupKey,
                ];
                $out[] = $field;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $def
     * @param array<string, string> $prefill
     * @param array<string, int|string> $context
     * @return array<string, mixed>|null
     */
    private function toUiJsonField(string $name, array $def, array $prefill, array $context): ?array
    {
        $type = mb_strtolower(trim((string) ($def['type'] ?? 'text')), 'UTF-8');
        if ($type === '') {
            $type = 'text';
        }

        $label = trim((string) ($def['label'] ?? ''));
        $field = [
            'name' => $name,
            'label' => $label,
            'type' => $type,
            'required' => (bool) ($def['required'] ?? false),
        ];

        if (isset($def['include_in_submit'])) {
            $field['include_in_submit'] = (bool) $def['include_in_submit'];
        }

        if (isset($def['layout']) && is_array($def['layout'])) {
            $field['layout'] = $def['layout'];
        }

        if ($type === 'hidden' || ($def['source'] ?? '') === 'context') {
            $field['type'] = 'hidden';
            $field['label'] = '';
            $ctxKey = trim((string) ($def['context_key'] ?? $name));
            if ($ctxKey !== '' && array_key_exists($ctxKey, $context)) {
                $field['value'] = (string) $context[$ctxKey];
            }
            $field['include_in_submit'] = $field['include_in_submit'] ?? true;

            return $field;
        }

        if ($type === 'enum') {
            $field['type'] = 'select';
            $field['options'] = $this->normalizeSelectOptions($def['options'] ?? []);
        }

        if ($type === 'custom_widget') {
            $widgetId = trim((string) ($def['widget_id'] ?? ''));
            if ($widgetId === '') {
                return null;
            }
            $field['widget_id'] = $widgetId;
            if (isset($def['value_fields']) && is_array($def['value_fields'])) {
                $field['value_fields'] = array_values(array_map('strval', $def['value_fields']));
                $initial = [];
                foreach ($field['value_fields'] as $vf) {
                    if (array_key_exists($vf, $prefill)) {
                        $initial[$vf] = (string) $prefill[$vf];
                    }
                }
                if ($initial !== []) {
                    $field['initial_values'] = $initial;
                }
            }
            if (isset($def['assets']) && is_array($def['assets'])) {
                $field['assets'] = $def['assets'];
            }
        }

        if (array_key_exists($name, $prefill)) {
            $field['value'] = (string) $prefill[$name];
        }

        if ($type === 'text' || $type === 'date') {
            $field['include_in_submit'] = $field['include_in_submit'] ?? true;
        }

        return $field;
    }

    /**
     * @param array<int|string, mixed>|list<array{value: string, label: string}> $options
     * @return list<array{value: string, label: string}>
     */
    private function normalizeSelectOptions(array $options): array
    {
        $out = [];
        foreach ($options as $key => $opt) {
            if (is_array($opt)) {
                $value = trim((string) ($opt['value'] ?? ''));
                $label = trim((string) ($opt['label'] ?? $value));
                if ($value !== '') {
                    $out[] = ['value' => $value, 'label' => $label !== '' ? $label : $value];
                }
                continue;
            }
            if (is_string($key) || is_int($key)) {
                $value = trim((string) $key);
                $label = trim((string) $opt);
                if ($value !== '') {
                    $out[] = ['value' => $value, 'label' => $label !== '' ? $label : $value];
                }
            }
        }

        return $out;
    }
}
