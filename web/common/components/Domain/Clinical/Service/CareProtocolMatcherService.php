<?php

namespace common\components\Domain\Clinical\Service;

/**
 * Resuelve protocolos por código de condición y/o perfil (edad, sexo).
 */
final class CareProtocolMatcherService
{
    private CareProtocolCatalogService $catalog;

    public function __construct(?CareProtocolCatalogService $catalog = null)
    {
        $this->catalog = $catalog ?? new CareProtocolCatalogService();
    }

    /**
     * Primer protocolo habilitado que matchea el código (prefijo CIE: E11 → E11.9).
     *
     * @return array<string, mixed>|null
     */
    public function matchByConditionCode(string $conditionCode): ?array
    {
        $needle = $this->normalizeCode($conditionCode);
        if ($needle === '') {
            return null;
        }
        foreach ($this->catalog->allProtocols() as $protocol) {
            if (!($protocol['enabled'] ?? true)) {
                continue;
            }
            if (($protocol['applies']['condition_codes'] ?? []) === []) {
                continue;
            }
            foreach ($protocol['applies']['condition_codes'] as $code) {
                if ($this->codeMatches($needle, $code)) {
                    return $protocol;
                }
            }
        }

        return null;
    }

    /**
     * Protocolos preventivos aplicables solo por perfil (tienen age/sex y matchean).
     *
     * @return list<array<string, mixed>>
     */
    public function matchByProfile(?int $ageYears, ?string $sex): array
    {
        $out = [];
        foreach ($this->catalog->allProtocols() as $protocol) {
            if (!($protocol['enabled'] ?? true)) {
                continue;
            }
            if (!$this->hasProfileRules($protocol)) {
                continue;
            }
            if ($this->profileMatches($protocol, $ageYears, $sex)) {
                $out[] = $protocol;
            }
        }

        return $out;
    }

    /**
     * @return list<array{code: string, label: string, description: string, outcome: string, draft: array<string, string>, protocol_id: string, protocol_title: string}>
     */
    public function actionsForConditionCode(string $conditionCode): array
    {
        $protocol = $this->matchByConditionCode($conditionCode);
        if ($protocol === null) {
            return [];
        }

        return $this->flattenActions($protocol);
    }

    /**
     * @return list<array{code: string, label: string, description: string, outcome: string, draft: array<string, string>, protocol_id: string, protocol_title: string}>
     */
    public function actionsForProtocolId(string $protocolId): array
    {
        $protocol = $this->catalog->findById($protocolId);
        if ($protocol === null || !($protocol['enabled'] ?? true)) {
            return [];
        }

        return $this->flattenActions($protocol);
    }

    public function findAction(string $protocolId, string $actionCode): ?array
    {
        $protocol = $this->catalog->findById($protocolId);
        if ($protocol === null || !($protocol['enabled'] ?? true)) {
            return null;
        }
        foreach ($protocol['actions'] as $action) {
            if ($action['code'] === $actionCode) {
                return $action + [
                    'protocol_id' => $protocol['id'],
                    'protocol_title' => $protocol['title'],
                ];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $protocol
     * @return list<array{code: string, label: string, description: string, outcome: string, draft: array<string, string>, protocol_id: string, protocol_title: string}>
     */
    private function flattenActions(array $protocol): array
    {
        $out = [];
        foreach ($protocol['actions'] as $action) {
            $out[] = [
                'code' => $action['code'],
                'label' => $action['label'],
                'description' => $action['description'],
                'outcome' => $action['outcome'],
                'draft' => $action['draft'],
                'protocol_id' => $protocol['id'],
                'protocol_title' => $protocol['title'],
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $protocol
     */
    private function hasProfileRules(array $protocol): bool
    {
        $age = $protocol['applies']['age_years'] ?? null;
        $sex = $protocol['applies']['sex'] ?? [];

        return (is_array($age) && ($age['min'] !== null || $age['max'] !== null))
            || $sex !== [];
    }

    /**
     * @param array<string, mixed> $protocol
     */
    private function profileMatches(array $protocol, ?int $ageYears, ?string $sex): bool
    {
        $applies = $protocol['applies'];
        $ageRule = $applies['age_years'] ?? null;
        if (is_array($ageRule) && ($ageRule['min'] !== null || $ageRule['max'] !== null)) {
            if ($ageYears === null) {
                return false;
            }
            if ($ageRule['min'] !== null && $ageYears < (int) $ageRule['min']) {
                return false;
            }
            if ($ageRule['max'] !== null && $ageYears > (int) $ageRule['max']) {
                return false;
            }
        }
        $sexRule = $applies['sex'] ?? [];
        if ($sexRule !== []) {
            $sx = strtoupper(trim((string) $sex));
            if ($sx === '' || !in_array($sx, $sexRule, true)) {
                return false;
            }
        }

        return true;
    }

    private function normalizeCode(string $code): string
    {
        $code = strtoupper(trim($code));
        $code = str_replace(' ', '', $code);

        return $code;
    }

    private function codeMatches(string $needle, string $catalogCode): bool
    {
        if ($needle === $catalogCode) {
            return true;
        }
        if (str_starts_with($needle, $catalogCode)) {
            $rest = substr($needle, strlen($catalogCode));

            return $rest === '' || $rest[0] === '.' || ctype_digit($rest[0] ?? '');
        }

        return false;
    }
}
