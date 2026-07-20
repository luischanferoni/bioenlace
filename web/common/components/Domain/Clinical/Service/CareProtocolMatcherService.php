<?php

namespace common\components\Domain\Clinical\Service;

/**
 * Resuelve protocolos aplicables a códigos de condición (CIE/SNOMED normalizados).
 */
final class CareProtocolMatcherService
{
    private CareProtocolCatalogService $catalog;

    public function __construct(?CareProtocolCatalogService $catalog = null)
    {
        $this->catalog = $catalog ?? new CareProtocolCatalogService();
    }

    /**
     * Primer protocolo que matchea el código (prefijo CIE permitido: E11 matchea E11.9).
     *
     * @return array{
     *   id: string,
     *   title: string,
     *   fhir_kind: string,
     *   applies: array{condition_codes: list<string>, clinical_status: list<string>},
     *   actions: list<array{code: string, label: string, description: string, outcome: string, draft: array<string, string>}>
     * }|null
     */
    public function matchByConditionCode(string $conditionCode): ?array
    {
        $needle = $this->normalizeCode($conditionCode);
        if ($needle === '') {
            return null;
        }
        foreach ($this->catalog->allProtocols() as $protocol) {
            foreach ($protocol['applies']['condition_codes'] as $code) {
                if ($this->codeMatches($needle, $code)) {
                    return $protocol;
                }
            }
        }

        return null;
    }

    /**
     * Acciones UI del protocolo o lista vacía si no hay match.
     *
     * @return list<array{code: string, label: string, description: string, outcome: string, draft: array<string, string>, protocol_id: string, protocol_title: string}>
     */
    public function actionsForConditionCode(string $conditionCode): array
    {
        $protocol = $this->matchByConditionCode($conditionCode);
        if ($protocol === null) {
            return [];
        }
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

    public function findAction(string $protocolId, string $actionCode): ?array
    {
        $protocol = $this->catalog->findById($protocolId);
        if ($protocol === null) {
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
        // Prefijo CIE: catálogo "E11" matchea "E11.9" / "E119"
        if (str_starts_with($needle, $catalogCode)) {
            $rest = substr($needle, strlen($catalogCode));
            return $rest === '' || $rest[0] === '.' || ctype_digit($rest[0] ?? '');
        }

        return false;
    }
}
