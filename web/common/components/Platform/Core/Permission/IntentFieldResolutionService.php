<?php

namespace common\components\Platform\Core\Permission;

/**
 * Resuelve menciones NL de campos contra fields[].keywords del YAML del intent.
 */
final class IntentFieldResolutionService
{
    public function __construct(
        private ?PermissionCatalogService $catalog = null
    ) {
        $this->catalog = $catalog ?? new PermissionCatalogService();
    }

    /**
     * @return list<string> nombres de campo con keyword coincidente
     */
    public function matchFieldNames(string $intentId, string $message): array
    {
        $manifest = $this->catalog->buildIntentFieldManifest(trim($intentId));
        if ($manifest === null || empty($manifest['fields'])) {
            return [];
        }

        $folded = $this->fold($message);
        if ($folded === '') {
            return [];
        }

        $matched = [];
        foreach ($manifest['fields'] as $field) {
            if (!is_array($field)) {
                continue;
            }
            $name = trim((string) ($field['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            foreach ($field['keywords'] ?? [] as $kw) {
                $kwFolded = $this->fold((string) $kw);
                if ($kwFolded !== '' && str_contains($folded, $kwFolded)) {
                    $matched[] = $name;
                    break;
                }
            }
        }

        return array_values(array_unique($matched));
    }

    /**
     * El mensaje menciona términos declarados como no editables en field_resolution.reject_keywords.
     */
    public function mentionsUnavailableField(string $intentId, string $message): bool
    {
        $manifest = $this->catalog->buildIntentFieldManifest(trim($intentId));
        if ($manifest === null || empty($manifest['fields'])) {
            return false;
        }

        $reject = $this->rejectKeywords($manifest);
        if ($reject === []) {
            return false;
        }

        if ($this->matchFieldNames($intentId, $message) !== []) {
            return false;
        }

        $folded = $this->fold($message);
        if ($folded === '') {
            return false;
        }

        foreach ($reject as $kw) {
            if (str_contains($folded, $kw)) {
                return true;
            }
        }

        return false;
    }

    public function unavailableFieldMessage(string $intentId): string
    {
        $manifest = $this->catalog->buildIntentFieldManifest(trim($intentId));
        if ($manifest === null) {
            return 'Ese dato no está disponible en esta operación.';
        }

        $custom = trim((string) (($manifest['field_resolution']['unavailable_message'] ?? '') ?: ''));
        if ($custom !== '') {
            return $custom;
        }

        $action = trim((string) ($manifest['action_name'] ?? $intentId));
        $labels = $this->fieldLabelsForManifest($manifest);
        if ($labels === []) {
            return 'Ese dato no está disponible en «' . $action . '».';
        }

        return 'Ese dato no está disponible en «' . $action . '». Podés modificar: ' . implode(', ', $labels) . '.';
    }

    /**
     * @param array<string, mixed> $manifest
     * @return list<string>
     */
    private function rejectKeywords(array $manifest): array
    {
        $section = $manifest['field_resolution'] ?? null;
        if (!is_array($section)) {
            return [];
        }
        $raw = $section['reject_keywords'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $kw) {
            $folded = $this->fold((string) $kw);
            if ($folded !== '') {
                $out[] = $folded;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param array<string, mixed> $manifest
     * @return list<string>
     */
    private function fieldLabelsForManifest(array $manifest): array
    {
        $labels = [];
        $groups = is_array($manifest['field_groups'] ?? null) ? $manifest['field_groups'] : [];
        foreach ($groups as $group) {
            if (!is_array($group)) {
                continue;
            }
            $label = trim((string) ($group['label'] ?? ''));
            if ($label !== '') {
                $labels[] = $label;
            }
        }

        if ($labels !== []) {
            return array_values(array_unique($labels));
        }

        foreach ($manifest['fields'] ?? [] as $field) {
            if (!is_array($field)) {
                continue;
            }
            $name = trim((string) ($field['name'] ?? ''));
            if ($name !== '') {
                $labels[] = $name;
            }
        }

        return array_values(array_unique($labels));
    }

    private function fold(string $text): string
    {
        $lower = mb_strtolower(trim($text), 'UTF-8');
        if ($lower === '') {
            return '';
        }

        return strtr($lower, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u',
            'ñ' => 'n',
        ]);
    }
}
