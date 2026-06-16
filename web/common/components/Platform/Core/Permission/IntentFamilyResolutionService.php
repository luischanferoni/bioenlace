<?php

namespace common\components\Platform\Core\Permission;

/**
 * Elige variantes de intent dentro de una familia NL según RBAC del usuario.
 */
final class IntentFamilyResolutionService
{
    /**
     * @return list<string>
     */
    public function listAccessibleMembers(int $userId, string $familyId): array
    {
        $family = IntentFamilyCatalog::get(trim($familyId));
        if ($family === null) {
            return [];
        }

        $accessible = [];
        foreach ($family['members'] as $memberId) {
            if (BioenlaceAccessChecker::userCanPermissionKey($userId, $memberId)) {
                $accessible[] = $memberId;
            }
        }

        return $accessible;
    }

    /**
     * Resuelve un único intent cuando hay un solo candidato accesible o preferIntentId es válido.
     */
    public function resolveSingle(int $userId, string $familyId, ?string $preferIntentId = null): ?string
    {
        $accessible = $this->listAccessibleMembers($userId, $familyId);
        if ($accessible === []) {
            return null;
        }

        $preferIntentId = trim((string) $preferIntentId);
        if ($preferIntentId !== '' && in_array($preferIntentId, $accessible, true)) {
            return $preferIntentId;
        }

        if (count($accessible) === 1) {
            return $accessible[0];
        }

        return null;
    }

    /**
     * @return list<array{intent_id: string, action_name: string, goal: string, how: string}>
     */
    public function listDisambiguationOptions(int $userId, string $familyId): array
    {
        $catalog = new PermissionCatalogService();
        $options = [];
        foreach ($this->listAccessibleMembers($userId, $familyId) as $intentId) {
            $manifest = $catalog->buildIntentFieldManifest($intentId);
            if ($manifest === null) {
                continue;
            }
            $semantics = is_array($manifest['intent_semantics'] ?? null) ? $manifest['intent_semantics'] : [];
            $options[] = [
                'intent_id' => $intentId,
                'action_name' => trim((string) ($manifest['action_name'] ?? $intentId)),
                'goal' => trim((string) ($semantics['goal'] ?? '')),
                'how' => trim((string) ($semantics['how'] ?? '')),
            ];
        }

        return $options;
    }

    public function resolveByDomainOperation(int $userId, string $domainOperation): ?string
    {
        $domainOperation = trim($domainOperation);
        if ($domainOperation === '') {
            return null;
        }

        $matches = [];
        foreach (IntentManifestIndex::all() as $intentId => $meta) {
            if (trim((string) ($meta['domain_operation'] ?? '')) !== $domainOperation) {
                continue;
            }
            if (BioenlaceAccessChecker::userCanPermissionKey($userId, $intentId)) {
                $matches[] = $intentId;
            }
        }

        if (count($matches) === 1) {
            return $matches[0];
        }

        return null;
    }

    /**
     * Puntúa keywords de cada miembro accesible contra el mensaje NL.
     */
    public function inferPreferredMemberFromMessage(int $userId, string $familyId, string $message): ?string
    {
        $accessible = $this->listAccessibleMembers($userId, $familyId);
        if ($accessible === []) {
            return null;
        }

        $folded = mb_strtolower(trim($message), 'UTF-8');
        if ($folded === '') {
            return $this->resolveSingle($userId, $familyId);
        }

        $catalog = new PermissionCatalogService();
        $bestId = null;
        $bestScore = 0;
        foreach ($accessible as $intentId) {
            $score = $this->scoreIntentMessage($catalog, $intentId, $folded);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestId = $intentId;
            }
        }

        if ($bestScore > 0 && $bestId !== null) {
            return $bestId;
        }

        return $this->resolveSingle($userId, $familyId);
    }

    private function scoreIntentMessage(PermissionCatalogService $catalog, string $intentId, string $foldedMessage): int
    {
        $manifest = $catalog->buildIntentFieldManifest($intentId);
        if ($manifest === null) {
            return 0;
        }

        $score = 0;
        foreach ($manifest['keywords'] ?? [] as $kw) {
            $kw = mb_strtolower(trim((string) $kw), 'UTF-8');
            if ($kw !== '' && str_contains($foldedMessage, $kw)) {
                $score += 10;
            }
        }

        foreach ($manifest['fields'] ?? [] as $field) {
            if (!is_array($field)) {
                continue;
            }
            foreach ($field['keywords'] ?? [] as $kw) {
                $kw = mb_strtolower(trim((string) $kw), 'UTF-8');
                if ($kw !== '' && str_contains($foldedMessage, $kw)) {
                    $score += 4;
                }
            }
        }

        $semantics = is_array($manifest['intent_semantics'] ?? null) ? $manifest['intent_semantics'] : [];
        foreach ($semantics['constraints'] ?? [] as $constraint) {
            $constraint = mb_strtolower(trim((string) $constraint), 'UTF-8');
            if ($constraint !== '' && str_contains($foldedMessage, 'mi ') && str_contains($constraint, 'propia')) {
                $score += 8;
            }
        }

        return $score;
    }
}
