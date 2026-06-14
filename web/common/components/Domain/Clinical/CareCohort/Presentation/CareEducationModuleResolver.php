<?php

namespace common\components\Domain\Clinical\CareCohort\Presentation;

/**
 * Resuelve módulos de education_bundle por id (education_refs del touchpoint).
 */
final class CareEducationModuleResolver
{
    /**
     * @param array<string, mixed>|null $educationContent content_json del pack education_bundle
     * @param list<string> $refs
     * @return list<array<string, mixed>>
     */
    public function resolveModules(?array $educationContent, array $refs): array
    {
        if ($educationContent === null) {
            return [];
        }

        $modules = $educationContent['modules'] ?? [];
        if (!is_array($modules)) {
            return [];
        }

        if ($refs === []) {
            return array_values(array_filter($modules, 'is_array'));
        }

        $byId = [];
        foreach ($modules as $module) {
            if (!is_array($module)) {
                continue;
            }
            $id = trim((string) ($module['id'] ?? ''));
            if ($id !== '') {
                $byId[$id] = $module;
            }
        }

        $out = [];
        foreach ($refs as $ref) {
            $ref = trim((string) $ref);
            if ($ref !== '' && isset($byId[$ref])) {
                $out[] = $byId[$ref];
            }
        }

        return $out;
    }
}
