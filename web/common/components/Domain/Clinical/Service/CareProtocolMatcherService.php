<?php

namespace common\components\Domain\Clinical\Service;

use common\models\Clinical\CareProtocol;
use common\models\Clinical\Condition;

/**
 * Resuelve protocolos por jurisdicción, código de condición y/o perfil (edad, sexo).
 */
final class CareProtocolMatcherService
{
    private const ACTIVE_STATUSES = ['ACTIVE', 'RECURRENCE', 'RELAPSE'];

    private CareProtocolCatalogService $catalog;

    public function __construct(?CareProtocolCatalogService $catalog = null)
    {
        $this->catalog = $catalog ?? new CareProtocolCatalogService();
    }

    /**
     * Primer protocolo habilitado que matchea el código (prefijo CIE: E11 → E11.9).
     *
     * @param array{clinical_status?: string, note?: string|null}|null $conditionContext
     * @return array<string, mixed>|null
     */
    public function matchByConditionCode(
        string $conditionCode,
        ?int $idProvincia = null,
        ?array $conditionContext = null,
        ?int $idPersona = null
    ): ?array {
        $needle = $this->normalizeCode($conditionCode);
        if ($needle === '') {
            return null;
        }
        $ctx = $conditionContext;
        if ($ctx === null && $idPersona !== null && $idPersona > 0) {
            $ctx = $this->resolveConditionContext($idPersona, $needle);
        }
        foreach ($this->catalog->allProtocols($idProvincia) as $protocol) {
            if (!($protocol['enabled'] ?? true)) {
                continue;
            }
            $codes = $protocol['applies']['condition_codes'] ?? [];
            if ($codes === []) {
                continue;
            }
            $matched = false;
            foreach ($codes as $code) {
                if ($this->codeMatches($needle, (string) $code)) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                continue;
            }
            if (!$this->conditionMatchSatisfied($protocol, $ctx)) {
                continue;
            }

            return $protocol;
        }

        return null;
    }

    /**
     * Protocolos preventivos aplicables solo por perfil (condition_match=none + age/sex).
     *
     * @return list<array<string, mixed>>
     */
    public function matchByProfile(?int $ageYears, ?string $sex, ?int $idProvincia = null): array
    {
        $out = [];
        foreach ($this->catalog->allProtocols($idProvincia) as $protocol) {
            if (!($protocol['enabled'] ?? true)) {
                continue;
            }
            $matchMode = (string) ($protocol['condition_match'] ?? CareProtocol::MATCH_NONE);
            if ($matchMode !== CareProtocol::MATCH_NONE) {
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
    public function actionsForConditionCode(string $conditionCode, ?int $idProvincia = null, ?int $idPersona = null): array
    {
        $protocol = $this->matchByConditionCode($conditionCode, $idProvincia, null, $idPersona);
        if ($protocol === null) {
            return [];
        }

        return $this->flattenActions($protocol);
    }

    /**
     * @return list<array{code: string, label: string, description: string, outcome: string, draft: array<string, string>, protocol_id: string, protocol_title: string}>
     */
    public function actionsForProtocolId(string $protocolId, ?int $idProvincia = null): array
    {
        $protocol = $this->catalog->findById($protocolId, $idProvincia);
        if ($protocol === null || !($protocol['enabled'] ?? true)) {
            return [];
        }

        return $this->flattenActions($protocol);
    }

    public function findAction(string $protocolId, string $actionCode, ?int $idProvincia = null): ?array
    {
        $protocol = $this->catalog->findById($protocolId, $idProvincia);
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
     * @param array{clinical_status?: string, note?: string|null}|null $ctx
     */
    private function conditionMatchSatisfied(array $protocol, ?array $ctx): bool
    {
        $mode = (string) ($protocol['condition_match'] ?? CareProtocol::MATCH_NONE);
        if ($mode === CareProtocol::MATCH_NONE) {
            return false;
        }
        $isActive = $this->isActiveStatus($ctx['clinical_status'] ?? null);
        $isChronic = $this->isChronicMarker($ctx['note'] ?? null);

        if ($mode === CareProtocol::MATCH_ACTIVE) {
            // Sin contexto: el caller ya filtró condiciones activas (hub) o match por código suelto.
            return $ctx === null || $isActive;
        }
        if ($mode === CareProtocol::MATCH_CHRONIC) {
            return $ctx !== null && $isActive && $isChronic;
        }
        if ($mode === CareProtocol::MATCH_ACTIVE_OR_CHRONIC) {
            if ($ctx === null) {
                return true;
            }

            return $isActive || $isChronic;
        }

        return false;
    }

    /**
     * @return array{clinical_status: string, note: string|null}|null
     */
    private function resolveConditionContext(int $idPersona, string $needleCode): ?array
    {
        /** @var list<Condition> $rows */
        $rows = Condition::find()
            ->where([
                'subject_persona_id' => $idPersona,
                'deleted_at' => null,
            ])
            ->andWhere(['clinical_status' => self::ACTIVE_STATUSES])
            ->andWhere(['not in', 'verification_status', ['REFUTED', 'ENTERED_IN_ERROR']])
            ->orderBy(['recorded_date' => SORT_DESC, 'id' => SORT_DESC])
            ->limit(50)
            ->all();
        foreach ($rows as $cond) {
            $code = $this->normalizeCode((string) ($cond->code ?? ''));
            if ($code === '') {
                continue;
            }
            if ($this->codeMatches($needleCode, $code) || $this->codeMatches($code, $needleCode)) {
                return [
                    'clinical_status' => (string) $cond->clinical_status,
                    'note' => $cond->note !== null ? (string) $cond->note : null,
                ];
            }
        }

        return null;
    }

    private function isActiveStatus(?string $status): bool
    {
        $s = strtoupper(trim((string) $status));

        return $s !== '' && in_array($s, self::ACTIVE_STATUSES, true);
    }

    private function isChronicMarker(?string $note): bool
    {
        if ($note === null || trim($note) === '') {
            return false;
        }
        $decoded = json_decode($note, true);
        if (is_array($decoded)) {
            $c = $decoded['cronico'] ?? $decoded['chronic'] ?? null;
            if (is_bool($c)) {
                return $c;
            }
            if (is_string($c) && strtoupper(trim($c)) === 'SI') {
                return true;
            }
            if (is_int($c) && $c === 1) {
                return true;
            }
        }
        if (preg_match('/"cronico"\s*:\s*"SI"/i', $note) === 1) {
            return true;
        }
        if (preg_match('/\bcronico\s*[:=]\s*SI\b/i', $note) === 1) {
            return true;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $protocol
     */
    private function hasProfileRules(array $protocol): bool
    {
        $age = $protocol['applies']['age_years'] ?? null;
        $sex = $protocol['applies']['sex'] ?? [];

        return (is_array($age) && (($age['min'] ?? null) !== null || ($age['max'] ?? null) !== null))
            || $sex !== [];
    }

    /**
     * @param array<string, mixed> $protocol
     */
    private function profileMatches(array $protocol, ?int $ageYears, ?string $sex): bool
    {
        $applies = $protocol['applies'];
        $ageRule = $applies['age_years'] ?? null;
        if (is_array($ageRule) && (($ageRule['min'] ?? null) !== null || ($ageRule['max'] ?? null) !== null)) {
            if ($ageYears === null) {
                return false;
            }
            if (($ageRule['min'] ?? null) !== null && $ageYears < (int) $ageRule['min']) {
                return false;
            }
            if (($ageRule['max'] ?? null) !== null && $ageYears > (int) $ageRule['max']) {
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
        $catalogCode = $this->normalizeCode($catalogCode);
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
