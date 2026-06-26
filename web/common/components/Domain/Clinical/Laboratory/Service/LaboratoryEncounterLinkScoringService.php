<?php

namespace common\components\Domain\Clinical\Laboratory\Service;

use common\models\Clinical\Encounter;
use common\models\Clinical\ServiceRequest;
use Yii;

/**
 * Score de candidatos encounter para un informe de laboratorio (agente E01).
 */
final class LaboratoryEncounterLinkScoringService
{
    public const AGENT_ID = 'lab-encounter-link';

    /**
     * @param array<string, mixed> $fhirReport
     * @param array<string, mixed> $config
     * @return array{encounter_id: int|null, outcome: string, candidates: list<array<string, mixed>>}
     */
    public function resolve(int $subjectPersonaId, array $fhirReport, array $reportMeta, array $config): array
    {
        $fhirRefId = $this->parseFhirEncounterRef($fhirReport, $subjectPersonaId);
        if ($fhirRefId !== null) {
            return [
                'encounter_id' => $fhirRefId,
                'outcome' => 'fhir_ref',
                'candidates' => [
                    ['encounter_id' => $fhirRefId, 'score' => (int) ($config['scoring']['fhir_encounter_ref'] ?? 100), 'reason' => 'fhir_ref'],
                ],
            ];
        }

        $issuedAt = $this->parseIssuedAt($fhirReport, $reportMeta);
        $candidates = $this->collectCandidates($subjectPersonaId, $issuedAt, $config);
        if ($candidates === []) {
            return ['encounter_id' => null, 'outcome' => 'orphan', 'candidates' => []];
        }

        $scored = [];
        foreach ($candidates as $encounter) {
            $scored[] = [
                'encounter_id' => (int) $encounter->id,
                'score' => $this->scoreEncounter($encounter, $issuedAt, $reportMeta, $config),
                'period_start' => (string) ($encounter->period_start ?? ''),
                'reason' => 'scored',
            ];
        }

        usort($scored, static fn (array $a, array $b): int => ($b['score'] <=> $a['score'])
            ?: strcmp((string) ($a['period_start'] ?? ''), (string) ($b['period_start'] ?? '')));

        $winner = $this->pickUnambiguousWinner($scored, $config);
        if ($winner !== null) {
            return [
                'encounter_id' => (int) $winner['encounter_id'],
                'outcome' => 'auto_linked',
                'candidates' => $scored,
            ];
        }

        if (count($scored) >= 2) {
            return [
                'encounter_id' => null,
                'outcome' => 'pending_staff',
                'candidates' => array_slice($scored, 0, 5),
            ];
        }

        $top = $scored[0];
        $minScore = (int) ($config['min_winner_score'] ?? 35);
        if ((int) ($top['score'] ?? 0) >= $minScore) {
            return [
                'encounter_id' => (int) $top['encounter_id'],
                'outcome' => 'auto_linked',
                'candidates' => $scored,
            ];
        }

        return [
            'encounter_id' => null,
            'outcome' => 'orphan',
            'candidates' => $scored,
        ];
    }

    /**
     * @param list<array<string, mixed>> $scored
     * @param array<string, mixed> $config
     * @return array<string, mixed>|null
     */
    public static function pickUnambiguousWinner(array $scored, array $config): ?array
    {
        if ($scored === []) {
            return null;
        }

        $top = $scored[0];
        $second = $scored[1] ?? null;
        $minScore = (int) ($config['min_winner_score'] ?? 35);
        $minGap = (int) ($config['min_score_gap'] ?? 10);

        if ((int) ($top['score'] ?? 0) < $minScore) {
            return null;
        }
        if ($second !== null && ((int) ($top['score'] ?? 0) - (int) ($second['score'] ?? 0)) < $minGap) {
            return null;
        }

        return $top;
    }

    /**
     * @param array<string, mixed> $config
     * @return list<Encounter>
     */
    private function collectCandidates(int $subjectPersonaId, ?string $issuedAt, array $config): array
    {
        $maxBefore = max(1, (int) ($config['search_max_days_before'] ?? 14));
        $maxAfter = max(0, (int) ($config['search_max_days_after'] ?? 3));

        if ($issuedAt !== null && $issuedAt !== '') {
            $issuedDay = substr($issuedAt, 0, 10);
            $from = date('Y-m-d 00:00:00', strtotime($issuedDay . ' -' . $maxBefore . ' days'));
            $to = date('Y-m-d 23:59:59', strtotime($issuedDay . ' +' . $maxAfter . ' days'));
        } else {
            $from = date('Y-m-d 00:00:00', strtotime('-' . $maxBefore . ' days'));
            $to = date('Y-m-d H:i:s');
        }

        return Encounter::find()
            ->where([
                'subject_persona_id' => $subjectPersonaId,
                'deleted_at' => null,
            ])
            ->andWhere(['>=', 'period_start', $from])
            ->andWhere(['<=', 'period_start', $to])
            ->orderBy(['period_start' => SORT_DESC])
            ->limit(20)
            ->all();
    }

    /**
     * @param array<string, mixed> $reportMeta code, display, code_system
     */
    private function scoreEncounter(Encounter $encounter, ?string $issuedAt, array $reportMeta, array $config): int
    {
        $weights = is_array($config['scoring'] ?? null) ? $config['scoring'] : [];
        $score = 0;

        if ($issuedAt !== null && $encounter->period_start !== null) {
            $issuedDay = substr($issuedAt, 0, 10);
            $encDay = substr((string) $encounter->period_start, 0, 10);
            if ($issuedDay !== '' && $encDay === $issuedDay) {
                $score += (int) ($weights['same_day_as_issued'] ?? 40);
            }

            $days = $this->daysBetween($encDay, $issuedDay);
            $maxDays = max(1, (int) ($weights['max_proximity_days'] ?? 14));
            $perDay = (int) ($weights['proximity_per_day'] ?? 3);
            if ($days >= 0 && $days <= $maxDays) {
                $score += ($maxDays - $days) * $perDay;
            }
        }

        if ($this->hasMatchingLabRequest((int) $encounter->id, $reportMeta, $config)) {
            $score += (int) ($weights['service_request_lab_match'] ?? 35);
            $pes = (int) ($encounter->id_profesional_efector_servicio ?? 0);
            $requestPes = $this->labRequestPes((int) $encounter->id, $reportMeta, $config);
            if ($pes > 0 && $requestPes > 0 && $pes === $requestPes) {
                $score += (int) ($weights['same_pes_as_request'] ?? 20);
            }
        }

        return $score;
    }

    /**
     * @param array<string, mixed> $reportMeta
     * @param array<string, mixed> $config
     */
    private function hasMatchingLabRequest(int $encounterId, array $reportMeta, array $config): bool
    {
        return $this->findMatchingLabRequest($encounterId, $reportMeta, $config) !== null;
    }

    /**
     * @param array<string, mixed> $reportMeta
     * @param array<string, mixed> $config
     */
    private function labRequestPes(int $encounterId, array $reportMeta, array $config): int
    {
        $sr = $this->findMatchingLabRequest($encounterId, $reportMeta, $config);

        return $sr !== null ? (int) ($sr->id_profesional_efector_servicio ?? 0) : 0;
    }

    /**
     * @param array<string, mixed> $reportMeta
     * @param array<string, mixed> $config
     */
    private function findMatchingLabRequest(int $encounterId, array $reportMeta, array $config): ?ServiceRequest
    {
        $categories = is_array($config['service_request_categories'] ?? null)
            ? $config['service_request_categories']
            : ['laboratory', 'lab', 'procedure'];

        $reportCode = trim((string) ($reportMeta['code'] ?? ''));
        $reportDisplay = mb_strtolower(trim((string) ($reportMeta['display'] ?? '')));

        $requests = ServiceRequest::find()
            ->where(['encounter_id' => $encounterId, 'deleted_at' => null])
            ->andWhere(['category' => $categories])
            ->orderBy(['id' => SORT_DESC])
            ->all();

        foreach ($requests as $sr) {
            $code = trim((string) ($sr->code ?? ''));
            if ($reportCode !== '' && $code !== '' && $code === $reportCode) {
                return $sr;
            }
            $display = mb_strtolower(trim((string) ($sr->display ?? '')));
            if ($reportDisplay !== '' && $display !== '' && (
                str_contains($display, $reportDisplay) || str_contains($reportDisplay, $display)
            )) {
                return $sr;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $fhirReport
     */
    private function parseFhirEncounterRef(array $fhirReport, int $subjectPersonaId): ?int
    {
        $encRef = $fhirReport['encounter']['reference'] ?? $fhirReport['context']['reference'] ?? null;
        if (!is_string($encRef) || !preg_match('/Encounter\/(\d+)/', $encRef, $m)) {
            return null;
        }

        $id = (int) $m[1];
        $enc = Encounter::findOne(['id' => $id, 'subject_persona_id' => $subjectPersonaId, 'deleted_at' => null]);

        return $enc !== null ? (int) $enc->id : null;
    }

    /**
     * @param array<string, mixed> $fhirReport
     * @param array<string, mixed> $reportMeta
     */
    private function parseIssuedAt(array $fhirReport, array $reportMeta): ?string
    {
        $issued = $fhirReport['issued'] ?? $fhirReport['effectiveDateTime'] ?? $reportMeta['issued_at'] ?? null;

        return is_string($issued) && $issued !== '' ? $issued : null;
    }

    private function daysBetween(string $encDay, string $issuedDay): int
    {
        try {
            $a = new \DateTimeImmutable($encDay);
            $b = new \DateTimeImmutable($issuedDay);

            return (int) abs(floor(($b->getTimestamp() - $a->getTimestamp()) / 86400));
        } catch (\Throwable $e) {
            return 999;
        }
    }
}
