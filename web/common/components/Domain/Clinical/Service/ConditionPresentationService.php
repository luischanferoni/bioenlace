<?php

namespace common\components\Domain\Clinical\Service;

use common\components\Domain\Person\Service\PacienteContextoService;
use common\components\Domain\Scheduling\Service\ControlSeguimientoHubService;
use common\models\Clinical\Condition;

/**
 * Resúmenes de condiciones activas para home / hub paciente.
 */
final class ConditionPresentationService
{
    /**
     * Condiciones deduplicadas (mismo criterio que el hub Control/Seguimiento).
     *
     * @return list<array<string, mixed>>
     */
    public function listPatientSummaries(int $subjectPersonaId, ?int $limit = null): array
    {
        if ($subjectPersonaId <= 0) {
            return [];
        }
        $cap = $limit ?? PatientActiveConditionQuery::DEDUPE_LIMIT;
        $rows = (new PatientActiveConditionQuery())->listActive($subjectPersonaId);
        $matcher = new CareProtocolMatcherService();
        $idProvincia = $this->resolveIdProvincia($subjectPersonaId);
        /** @var array<string, array{score: int, summary: array<string, mixed>}> $byDedupe */
        $byDedupe = [];

        foreach ($rows as $cond) {
            $summary = $this->buildCandidate($cond, $matcher, $idProvincia);
            if ($summary === null) {
                continue;
            }
            $dedupeKey = (string) $summary['_dedupe_key'];
            $score = (int) $summary['_score'];
            unset($summary['_dedupe_key'], $summary['_score']);
            if (isset($byDedupe[$dedupeKey]) && $byDedupe[$dedupeKey]['score'] >= $score) {
                continue;
            }
            $byDedupe[$dedupeKey] = [
                'score' => $score,
                'summary' => $summary,
            ];
        }

        $ranked = array_values($byDedupe);
        usort($ranked, static function (array $a, array $b): int {
            return $b['score'] <=> $a['score'];
        });
        $out = [];
        foreach (array_slice($ranked, 0, $cap) as $row) {
            $out[] = $row['summary'];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function toPatientSummary(Condition $cond, ?array $protocol = null): array
    {
        $code = trim((string) ($cond->code ?? ''));
        $display = trim((string) ($cond->display ?? ''));
        $label = $this->shortLabel($display !== '' ? $display : $code);
        $status = (string) ($cond->clinical_status ?? '');
        $hub = new ControlSeguimientoHubService();
        $acciones = $hub->listConditionActionItems(
            $code !== '' ? $code : null,
            is_array($protocol) ? (string) ($protocol['id'] ?? '') : null
        );

        return [
            'id' => (int) $cond->id,
            'codigo' => $code,
            'display' => $display !== '' ? $display : null,
            'label' => $label !== '' ? $label : ($code !== '' ? $code : 'Condición'),
            'clinical_status' => $status,
            'statusLabel' => $this->statusLabel($status),
            'verification_status' => (string) ($cond->verification_status ?? ''),
            'recorded_date' => $cond->recorded_date,
            'protocol_id' => is_array($protocol) ? (string) ($protocol['id'] ?? '') : null,
            'protocol_title' => is_array($protocol)
                ? (string) ($protocol['title'] ?? $protocol['hub_label'] ?? '')
                : null,
            'control_hub_anchor' => ControlSeguimientoHubService::ANCHOR_PREFIX_CONDITION
                . ($code !== '' ? $code : (string) $cond->id),
            'seguimientoAcciones' => $this->mapAccionesForClient($acciones),
        ];
    }

    /**
     * @param CareProtocolMatcherService $matcher
     * @return array<string, mixed>|null
     */
    private function buildCandidate(Condition $cond, CareProtocolMatcherService $matcher, ?int $idProvincia): ?array
    {
        $code = trim((string) ($cond->code ?? ''));
        $display = trim((string) ($cond->display ?? ''));
        if ($code === '' && $display === '') {
            return null;
        }
        $labelText = $this->shortLabel($display !== '' ? $display : $code);
        if ($labelText === '' || $labelText === '?') {
            return null;
        }
        $dedupeKey = mb_strtolower(preg_replace('/\s+/u', ' ', $labelText) ?? $labelText);
        $protocol = $code !== ''
            ? $matcher->matchByConditionCode($code, $idProvincia, [
                'clinical_status' => (string) $cond->clinical_status,
                'note' => $cond->note !== null ? (string) $cond->note : null,
            ])
            : null;
        $isIcdLike = $code !== '' && (bool) preg_match('/^[A-Za-z]/', $code);
        $score = 0;
        if ($protocol !== null) {
            $score += 100;
        }
        if ($isIcdLike) {
            $score += 10;
        }
        if ($display !== '') {
            $score += 1;
        }
        $summary = $this->toPatientSummary($cond, $protocol);
        $summary['_dedupe_key'] = $dedupeKey;
        $summary['_score'] = $score;

        return $summary;
    }

    /**
     * @param list<array{id: string, label: string, subtitle: string, meta: array<string, mixed>}> $acciones
     * @return list<array<string, mixed>>
     */
    private function mapAccionesForClient(array $acciones): array
    {
        $out = [];
        foreach ($acciones as $row) {
            $meta = is_array($row['meta'] ?? null) ? $row['meta'] : [];
            $draft = is_array($meta['draft'] ?? null) ? $meta['draft'] : [];
            $out[] = [
                'code' => (string) ($row['id'] ?? ''),
                'label' => (string) ($row['label'] ?? ''),
                'description' => (string) ($row['subtitle'] ?? ''),
                'outcome' => (string) ($meta['outcome'] ?? ''),
                'draft' => $draft,
                'protocol_id' => isset($meta['protocol_id']) ? (string) $meta['protocol_id'] : null,
                'source' => (string) ($meta['source'] ?? ''),
            ];
        }

        return $out;
    }

    private function statusLabel(string $status): string
    {
        $map = [
            'ACTIVE' => 'Activa',
            'RECURRENCE' => 'Recurrencia',
            'RELAPSE' => 'Recaída',
            'INACTIVE' => 'Inactiva',
            'REMISSION' => 'Remisión',
            'RESOLVED' => 'Resuelta',
        ];

        return $map[$status] ?? ($status !== '' ? $status : 'Activa');
    }

    private function shortLabel(string $label): string
    {
        $label = trim($label);
        if ($label === '') {
            return '';
        }
        foreach ([' en seguimiento', ' en Seguimiento', ' | '] as $cut) {
            $pos = mb_stripos($label, $cut);
            if ($pos !== false && $pos > 8) {
                $label = trim(mb_substr($label, 0, $pos));
            }
        }
        if (mb_strlen($label) > 64) {
            return mb_substr($label, 0, 61) . '…';
        }

        return $label;
    }

    private function resolveIdProvincia(int $idPersona): ?int
    {
        if ($idPersona <= 0) {
            return null;
        }
        try {
            $ctx = (new PacienteContextoService())->getOrCreate($idPersona);
            $id = $ctx->id_provincia_contexto !== null ? (int) $ctx->id_provincia_contexto : 0;

            return $id > 0 ? $id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
