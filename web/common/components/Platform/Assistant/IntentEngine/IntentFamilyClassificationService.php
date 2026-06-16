<?php

namespace common\components\Platform\Assistant\IntentEngine;

use common\components\Platform\Core\Permission\IntentFamilyCatalog;
use common\components\Platform\Core\Permission\IntentFamilyResolutionService;
use common\components\Platform\Core\Permission\IntentManifestIndex;

/**
 * Refina clasificación NL hacia un miembro de familia (o desambiguación) sin hardcode de intent_id.
 */
final class IntentFamilyClassificationService
{
    /**
     * @param array<string, mixed>|null $classification
     * @return array<string, mixed>|null
     */
    public function refine(?array $classification, string $message, int $userId, UiActionCatalog $catalog): ?array
    {
        if ($userId <= 0) {
            return $classification;
        }

        if ($classification === null) {
            return $this->resolveFamilyOperationalFallback($message, $userId, $catalog);
        }

        if (isset($classification['disambiguation']) && is_array($classification['disambiguation'])) {
            return $classification;
        }

        $item = $classification['item'] ?? null;
        if (!$item instanceof UiActionCatalogItem) {
            return $classification;
        }

        $meta = IntentManifestIndex::get($item->action_id);
        $familyId = trim((string) ($meta['intent_family'] ?? ''));
        if ($familyId === '') {
            return $classification;
        }

        $family = IntentFamilyCatalog::get($familyId);
        if ($family === null || count($family['members']) < 2) {
            return $classification;
        }

        $resolver = new IntentFamilyResolutionService();
        $accessible = $resolver->listAccessibleMembers($userId, $familyId);
        if ($accessible === []) {
            return null;
        }

        if (count($accessible) === 1) {
            return $this->swapItem($classification, $accessible[0], $catalog, 'family_single');
        }

        $resolved = $resolver->inferPreferredMemberFromMessage($userId, $familyId, $message);
        if ($resolved !== null && in_array($resolved, $accessible, true)) {
            return $this->swapItem($classification, $resolved, $catalog, 'family_inferred');
        }

        return $this->buildDisambiguationClassification($classification, $familyId, $userId, $resolver, $catalog);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveFamilyOperationalFallback(string $message, int $userId, UiActionCatalog $catalog): ?array
    {
        foreach (IntentClassificationRulesService::familyOperationalFallbacks() as $fb) {
            if (!is_array($fb)) {
                continue;
            }
            if (!empty($fb['requires_staff_data_access'])
                && !IntentClassificationRulesService::isStaffDataAccessOperationalQuery($message)) {
                continue;
            }
            $whenAny = $fb['when_any_rule'] ?? [];
            if (!is_array($whenAny) || !IntentClassificationRulesService::matchesAnyRule($message, $whenAny)) {
                continue;
            }

            $familyId = trim((string) ($fb['intent_family'] ?? ''));
            if ($familyId === '') {
                continue;
            }

            $resolver = new IntentFamilyResolutionService();
            $resolved = $resolver->inferPreferredMemberFromMessage($userId, $familyId, $message);
            if ($resolved !== null) {
                $item = $catalog->byActionId[$resolved] ?? null;
                if ($item instanceof UiActionCatalogItem) {
                    return [
                        'item' => $item,
                        'confidence' => (float) ($fb['confidence'] ?? 0.9),
                        'method' => trim((string) ($fb['method'] ?? 'rules_family_fallback')),
                    ];
                }
            }

            $placeholder = $catalog->byActionId[$resolver->listAccessibleMembers($userId, $familyId)[0] ?? ''] ?? null;
            if (!$placeholder instanceof UiActionCatalogItem) {
                continue;
            }

            $disambiguation = $this->buildDisambiguationPayload($familyId, $userId, $resolver);
            if ($disambiguation === null) {
                continue;
            }

            return [
                'item' => $placeholder,
                'confidence' => (float) ($fb['confidence'] ?? 0.85),
                'method' => trim((string) ($fb['method'] ?? 'rules_family_fallback')),
                'disambiguation' => $disambiguation,
            ];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $classification
     * @return array<string, mixed>
     */
    private function buildDisambiguationClassification(
        array $classification,
        string $familyId,
        int $userId,
        IntentFamilyResolutionService $resolver,
        UiActionCatalog $catalog
    ): array {
        $disambiguation = $this->buildDisambiguationPayload($familyId, $userId, $resolver);
        if ($disambiguation === null) {
            return $classification;
        }

        $accessible = $resolver->listAccessibleMembers($userId, $familyId);
        $placeholderId = $accessible[0] ?? '';
        $placeholder = $catalog->byActionId[$placeholderId] ?? ($classification['item'] ?? null);
        if ($placeholder instanceof UiActionCatalogItem) {
            $classification['item'] = $placeholder;
        }

        $classification['disambiguation'] = $disambiguation;
        $classification['method'] = trim((string) ($classification['method'] ?? 'rules')) . '+family_disambiguation';
        $classification['confidence'] = min((float) ($classification['confidence'] ?? 0.5), 0.75);

        return $classification;
    }

    /**
     * @return array{text: string, remediation: list<array{id: string, label: string, intent_id: string, reset_flow: bool}>}|null
     */
    private function buildDisambiguationPayload(
        string $familyId,
        int $userId,
        IntentFamilyResolutionService $resolver
    ): ?array {
        $options = $resolver->listDisambiguationOptions($userId, $familyId);
        if (count($options) < 2) {
            return null;
        }

        $remediation = [];
        foreach ($options as $i => $opt) {
            $intentId = trim((string) ($opt['intent_id'] ?? ''));
            if ($intentId === '') {
                continue;
            }
            $label = trim((string) ($opt['action_name'] ?? $intentId));
            $goal = trim((string) ($opt['goal'] ?? ''));
            if ($goal !== '') {
                $label .= ' — ' . $goal;
            }
            $remediation[] = [
                'id' => 'family_' . $familyId . '_' . $i,
                'label' => $label,
                'intent_id' => $intentId,
                'reset_flow' => true,
            ];
        }

        if (count($remediation) < 2) {
            return null;
        }

        return [
            'text' => $this->disambiguationPromptText($options),
            'remediation' => $remediation,
        ];
    }

    /**
     * @param list<array{intent_id: string, action_name: string, goal: string, how: string}> $options
     */
    private function disambiguationPromptText(array $options): string
    {
        $parts = [];
        foreach ($options as $opt) {
            $goal = trim((string) ($opt['goal'] ?? ''));
            if ($goal !== '') {
                $parts[] = $goal;
            }
        }
        if ($parts !== []) {
            return '¿Cuál de estas opciones necesitás? ' . implode(' · ', array_slice($parts, 0, 3));
        }

        return 'Hay varias formas de hacer esto según tu rol. Elegí una opción.';
    }

    /**
     * @param array<string, mixed> $classification
     * @return array<string, mixed>
     */
    private function swapItem(array $classification, string $intentId, UiActionCatalog $catalog, string $methodSuffix): array
    {
        $item = $catalog->byActionId[$intentId] ?? null;
        if ($item instanceof UiActionCatalogItem) {
            $classification['item'] = $item;
            $baseMethod = trim((string) ($classification['method'] ?? 'rules'));
            if (!str_contains($baseMethod, $methodSuffix)) {
                $classification['method'] = $baseMethod . '+' . $methodSuffix;
            }
        }

        return $classification;
    }
}
