<?php

namespace common\components\Platform\Core\Permission;

/**
 * Filtra payload de submit API según fields declarados en el YAML del intent.
 */
final class IntentSubmitFieldFilter
{
    /** @var list<string> */
    private const DEFAULT_STRUCTURAL_KEYS = [
        'id_profesional_efector_servicio',
        'id_efector',
        'id_staff',
        'intent_id',
    ];

    public function __construct(
        private ?PermissionCatalogService $catalog = null
    ) {
        $this->catalog = $catalog ?? new PermissionCatalogService();
    }

    /**
     * @param array<string, mixed> $post
     * @param list<string> $extraStructuralKeys
     * @return array<string, mixed>
     */
    public function filter(string $intentId, array $post, array $extraStructuralKeys = []): array
    {
        $intentId = trim($intentId);
        if ($intentId === '') {
            return $post;
        }

        $manifest = $this->catalog->buildIntentFieldManifest($intentId);
        if ($manifest === null || empty($manifest['uses_extended_contract'])) {
            return $post;
        }

        $allowed = array_fill_keys(
            array_merge(self::DEFAULT_STRUCTURAL_KEYS, $extraStructuralKeys),
            true
        );
        foreach ($manifest['fields'] ?? [] as $field) {
            if (!is_array($field)) {
                continue;
            }
            $name = trim((string) ($field['name'] ?? ''));
            if ($name !== '') {
                $allowed[$name] = true;
            }
        }

        $filtered = [];
        foreach ($post as $key => $value) {
            if (isset($allowed[(string) $key])) {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }

    /**
     * @return list<string>
     */
    public function allowedFieldNames(string $intentId): array
    {
        $manifest = $this->catalog->buildIntentFieldManifest(trim($intentId));
        if ($manifest === null) {
            return [];
        }

        $names = [];
        foreach ($manifest['fields'] ?? [] as $field) {
            if (!is_array($field)) {
                continue;
            }
            $name = trim((string) ($field['name'] ?? ''));
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }
}
