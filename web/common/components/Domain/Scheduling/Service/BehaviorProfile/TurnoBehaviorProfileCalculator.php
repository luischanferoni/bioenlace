<?php

namespace common\components\Domain\Scheduling\Service\BehaviorProfile;

use common\models\Scheduling\PersonaTurnosPerfil;
use common\models\Scheduling\PersonaTurnosPerfilMetrica;
use common\models\TurnoEventoAudit;

/**
 * Cálculo determinista de métricas a partir de eventos (sin persistencia).
 *
 * No produce risk_level. Denominador 0 → NOT_APPLICABLE. Muestra insuficiente → INSUFFICIENT_DATA.
 */
final class TurnoBehaviorProfileCalculator
{
    private TurnoBehaviorProfileContract $contract;

    public function __construct(?TurnoBehaviorProfileContract $contract = null)
    {
        $this->contract = $contract ?? new TurnoBehaviorProfileContract();
    }

    /**
     * @param list<array<string, mixed>> $events Filas normalizadas (ver {@see normalizeEvent()})
     * @return array{
     *   completeness_status: string,
     *   metrics: list<array<string, mixed>>
     * }
     */
    public function calculate(array $events, string $asOf): array
    {
        $asOfTs = strtotime($asOf) ?: time();
        $patientActors = $this->contract->patientAttributedActors();
        $lateHours = $this->contract->lateCancellationHours();
        $minSample = $this->contract->minSampleSize();

        $byTurno = [];
        foreach ($events as $raw) {
            $e = $this->normalizeEvent($raw);
            if ($e === null) {
                continue;
            }
            $tid = $e['id_turno'];
            if (!isset($byTurno[$tid])) {
                $byTurno[$tid] = [
                    'id_turno' => $tid,
                    'id_efector' => $e['id_efector'],
                    'id_servicio' => $e['id_servicio'],
                    'modalidad' => $e['modalidad'],
                    'cita_ts' => $e['cita_ts'],
                    'events' => [],
                ];
            }
            if ($e['id_efector'] !== null) {
                $byTurno[$tid]['id_efector'] = $e['id_efector'];
            }
            if ($e['id_servicio'] !== null) {
                $byTurno[$tid]['id_servicio'] = $e['id_servicio'];
            }
            if ($e['modalidad'] !== null && $e['modalidad'] !== '') {
                $byTurno[$tid]['modalidad'] = $e['modalidad'];
            }
            if ($e['cita_ts'] !== null) {
                $byTurno[$tid]['cita_ts'] = $e['cita_ts'];
            }
            $byTurno[$tid]['events'][] = $e;
        }

        foreach ($byTurno as &$t) {
            usort($t['events'], static function (array $a, array $b): int {
                $c = ($a['id'] ?? 0) <=> ($b['id'] ?? 0);
                if ($c !== 0) {
                    return $c;
                }

                return ($a['occurred_ts'] ?? 0) <=> ($b['occurred_ts'] ?? 0);
            });
            $t['facts'] = $this->resolveTurnoFacts($t['events'], $patientActors, $lateHours);
        }
        unset($t);

        $scopeKeys = $this->buildScopeKeys($byTurno);
        $metricsOut = [];
        $hasInferred = false;
        $hasAnyOutcome = false;

        foreach ($this->contract->windowsDays() as $windowDays) {
            $windowStart = $asOfTs - ($windowDays * 86400);
            foreach ($scopeKeys as $scope) {
                $counts = $this->emptyCounts();
                foreach ($byTurno as $turno) {
                    $citaTs = $turno['cita_ts'];
                    if ($citaTs === null || $citaTs < $windowStart || $citaTs > $asOfTs) {
                        continue;
                    }
                    if (!$this->matchesScope($turno, $scope)) {
                        continue;
                    }
                    $facts = $turno['facts'];
                    if ($facts['has_inferred_event']) {
                        $hasInferred = true;
                    }
                    $this->accumulate($counts, $facts);
                    if ($facts['closed_eligible'] || $facts['cancel_patient'] || $facts['rescheduled']) {
                        $hasAnyOutcome = true;
                    }
                }
                foreach ($this->contract->metrics() as $def) {
                    $metricsOut[] = $this->buildMetricRow($def, $counts, $scope, $windowDays, $minSample);
                }
            }
        }

        $completeness = PersonaTurnosPerfil::COMPLETENESS_EMPTY;
        if ($hasAnyOutcome || $byTurno !== []) {
            $completeness = $hasInferred
                ? PersonaTurnosPerfil::COMPLETENESS_PARTIAL
                : PersonaTurnosPerfil::COMPLETENESS_COMPLETE;
        }

        return [
            'completeness_status' => $completeness,
            'metrics' => $metricsOut,
        ];
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>|null
     */
    public function normalizeEvent(array $raw): ?array
    {
        $idTurno = (int) ($raw['id_turno'] ?? 0);
        if ($idTurno <= 0) {
            return null;
        }
        $code = (string) ($raw['event_code'] ?? $raw['tipo_evento'] ?? '');
        if ($code === '') {
            return null;
        }
        $occurred = (string) ($raw['occurred_at'] ?? $raw['created_at'] ?? '');
        $occurredTs = $occurred !== '' ? (strtotime($occurred) ?: null) : null;
        $cita = (string) ($raw['cita_at'] ?? '');
        $citaTs = $cita !== '' ? (strtotime($cita) ?: null) : null;

        return [
            'id' => (int) ($raw['id'] ?? 0),
            'id_turno' => $idTurno,
            'event_code' => $code,
            'actor_type' => (string) ($raw['actor_type'] ?? ''),
            'attribution_quality' => (string) ($raw['attribution_quality'] ?? TurnoEventoAudit::QUALITY_NATIVE),
            'occurred_ts' => $occurredTs,
            'cita_ts' => $citaTs,
            'id_efector' => isset($raw['id_efector']) ? (int) $raw['id_efector'] : null,
            'id_servicio' => isset($raw['id_servicio']) ? (int) $raw['id_servicio'] : null,
            'modalidad' => isset($raw['modalidad']) ? (string) $raw['modalidad'] : null,
            'corrected_event_id' => isset($raw['corrected_event_id']) ? (int) $raw['corrected_event_id'] : null,
        ];
    }

    /**
     * @param list<array<string, mixed>> $events
     * @param list<string> $patientActors
     * @return array<string, mixed>
     */
    private function resolveTurnoFacts(array $events, array $patientActors, int $lateHours): array
    {
        $attended = false;
        $noShow = false;
        $noShowCorrected = false;
        $cancelPatient = false;
        $cancelLate = false;
        $rescheduled = false;
        $confirmRequested = false;
        $confirmDelivered = false;
        $confirmResponded = false;
        $quality = TurnoEventoAudit::QUALITY_NATIVE;
        $hasInferredEvent = false;
        $citaTs = null;

        foreach ($events as $e) {
            $eventQuality = ($e['attribution_quality'] ?? '') === TurnoEventoAudit::QUALITY_LEGACY_INFERRED
                ? TurnoEventoAudit::QUALITY_LEGACY_INFERRED
                : TurnoEventoAudit::QUALITY_NATIVE;
            $hasInferredEvent = $hasInferredEvent
                || $eventQuality === TurnoEventoAudit::QUALITY_LEGACY_INFERRED;
            if ($e['cita_ts'] !== null) {
                $citaTs = $e['cita_ts'];
            }
            $code = $e['event_code'];
            $actor = $e['actor_type'];
            if ($code === TurnoEventoAudit::EVENT_ATTENDED || $code === 'ATTENDED') {
                $attended = true;
                $noShow = false;
                $quality = $eventQuality;
            } elseif ($code === TurnoEventoAudit::EVENT_NO_SHOW_RECORDED || $code === TurnoEventoAudit::TIPO_NO_SHOW) {
                if (in_array($actor, $patientActors, true)) {
                    $noShow = true;
                    $quality = $eventQuality;
                }
            } elseif ($code === TurnoEventoAudit::EVENT_NO_SHOW_CORRECTED) {
                $noShowCorrected = true;
                $noShow = false;
            } elseif ($code === TurnoEventoAudit::EVENT_APPOINTMENT_CANCELLED
                || $code === TurnoEventoAudit::TIPO_CANCEL_PAC
                || $code === TurnoEventoAudit::TIPO_CANCEL_MED
                || $code === TurnoEventoAudit::TIPO_BULK_DAY_CANCEL
            ) {
                if (in_array($actor, $patientActors, true)) {
                    $cancelPatient = true;
                    $quality = $eventQuality;
                    $occ = $e['occurred_ts'];
                    if ($citaTs !== null && $occ !== null && $lateHours >= 0) {
                        $hoursBefore = ($citaTs - $occ) / 3600;
                        $cancelLate = $hoursBefore < $lateHours;
                    }
                }
            } elseif ($code === TurnoEventoAudit::EVENT_APPOINTMENT_RESCHEDULED) {
                $rescheduled = true;
            } elseif ($code === TurnoEventoAudit::EVENT_CONFIRMATION_REQUESTED) {
                $confirmRequested = true;
            } elseif ($code === TurnoEventoAudit::EVENT_CONFIRMATION_DELIVERY_CONFIRMED) {
                $confirmDelivered = true;
            } elseif ($code === TurnoEventoAudit::EVENT_CONFIRMED || $code === TurnoEventoAudit::TIPO_CONFIRMED) {
                $confirmResponded = true;
            }
        }

        if ($noShowCorrected) {
            $noShow = false;
        }
        $noShowAttributable = $noShow;
        $closedEligible = $attended || $noShowAttributable;
        $confirmedClosed = $confirmResponded && $closedEligible;
        $attendedAfterConfirm = $confirmResponded && $attended;

        return [
            'attended' => $attended,
            'no_show_attributable' => $noShowAttributable,
            'closed_eligible' => $closedEligible,
            'cancel_patient' => $cancelPatient,
            'cancel_late' => $cancelPatient && $cancelLate,
            'cancel_early' => $cancelPatient && !$cancelLate,
            'rescheduled' => $rescheduled,
            'confirm_requested' => $confirmRequested,
            'confirm_delivered' => $confirmDelivered,
            'confirm_responded' => $confirmResponded,
            'confirmed_closed' => $confirmedClosed,
            'attended_after_confirm' => $attendedAfterConfirm,
            'quality' => $quality,
            'has_inferred_event' => $hasInferredEvent,
            'coverage_native' => $closedEligible && $quality === TurnoEventoAudit::QUALITY_NATIVE,
            'coverage_inferred' => $closedEligible && $quality === TurnoEventoAudit::QUALITY_LEGACY_INFERRED,
        ];
    }

    /**
     * @param array<string, mixed> $turno
     * @return list<array{scope_type: string, scope_id: string|null}>
     */
    private function buildScopeKeys(array $byTurno): array
    {
        $keys = [['scope_type' => PersonaTurnosPerfilMetrica::SCOPE_GLOBAL, 'scope_id' => '']];
        $efectores = [];
        $servicios = [];
        $modalidades = [];
        foreach ($byTurno as $t) {
            if (!empty($t['id_efector'])) {
                $efectores[(string) $t['id_efector']] = true;
            }
            if (!empty($t['id_servicio'])) {
                $servicios[(string) $t['id_servicio']] = true;
            }
            if (!empty($t['modalidad'])) {
                $modalidades[(string) $t['modalidad']] = true;
            }
        }
        foreach (array_keys($efectores) as $id) {
            $keys[] = ['scope_type' => PersonaTurnosPerfilMetrica::SCOPE_EFECTOR, 'scope_id' => $id];
        }
        foreach (array_keys($servicios) as $id) {
            $keys[] = ['scope_type' => PersonaTurnosPerfilMetrica::SCOPE_SERVICIO, 'scope_id' => $id];
        }
        foreach (array_keys($modalidades) as $id) {
            $keys[] = ['scope_type' => PersonaTurnosPerfilMetrica::SCOPE_MODALIDAD, 'scope_id' => $id];
        }

        $allowed = $this->contract->scopes();
        return array_values(array_filter($keys, static function (array $k) use ($allowed): bool {
            return in_array($k['scope_type'], $allowed, true);
        }));
    }

    /**
     * @param array<string, mixed> $turno
     * @param array{scope_type: string, scope_id: string|null} $scope
     */
    private function matchesScope(array $turno, array $scope): bool
    {
        switch ($scope['scope_type']) {
            case PersonaTurnosPerfilMetrica::SCOPE_GLOBAL:
                return true;
            case PersonaTurnosPerfilMetrica::SCOPE_EFECTOR:
                return (string) ($turno['id_efector'] ?? '') === (string) $scope['scope_id'];
            case PersonaTurnosPerfilMetrica::SCOPE_SERVICIO:
                return (string) ($turno['id_servicio'] ?? '') === (string) $scope['scope_id'];
            case PersonaTurnosPerfilMetrica::SCOPE_MODALIDAD:
                return (string) ($turno['modalidad'] ?? '') === (string) $scope['scope_id'];
            default:
                return false;
        }
    }

    /** @return array<string, int> */
    private function emptyCounts(): array
    {
        return [
            'CLOSED_ELIGIBLE' => 0,
            'ATTENDED' => 0,
            'NO_SHOW_ATTRIBUTABLE' => 0,
            'CANCEL_PATIENT' => 0,
            'CANCEL_EARLY' => 0,
            'CANCEL_LATE' => 0,
            'RESCHEDULED' => 0,
            'CONFIRMATION_REQUESTED' => 0,
            'CONFIRMATION_DELIVERED' => 0,
            'CONFIRMATION_RESPONDED' => 0,
            'ATTENDED_AFTER_CONFIRM' => 0,
            'CONFIRMED_CLOSED' => 0,
            'COVERAGE_NATIVE' => 0,
            'COVERAGE_INFERRED' => 0,
        ];
    }

    /**
     * @param array<string, int> $counts
     * @param array<string, mixed> $facts
     */
    private function accumulate(array &$counts, array $facts): void
    {
        if ($facts['closed_eligible']) {
            $counts['CLOSED_ELIGIBLE']++;
        }
        if ($facts['attended']) {
            $counts['ATTENDED']++;
        }
        if ($facts['no_show_attributable']) {
            $counts['NO_SHOW_ATTRIBUTABLE']++;
        }
        if ($facts['cancel_patient']) {
            $counts['CANCEL_PATIENT']++;
        }
        if ($facts['cancel_early']) {
            $counts['CANCEL_EARLY']++;
        }
        if ($facts['cancel_late']) {
            $counts['CANCEL_LATE']++;
        }
        if ($facts['rescheduled']) {
            $counts['RESCHEDULED']++;
        }
        if ($facts['confirm_requested']) {
            $counts['CONFIRMATION_REQUESTED']++;
        }
        if ($facts['confirm_delivered']) {
            $counts['CONFIRMATION_DELIVERED']++;
        }
        if ($facts['confirm_responded']) {
            $counts['CONFIRMATION_RESPONDED']++;
        }
        if ($facts['attended_after_confirm']) {
            $counts['ATTENDED_AFTER_CONFIRM']++;
        }
        if ($facts['confirmed_closed']) {
            $counts['CONFIRMED_CLOSED']++;
        }
        if ($facts['coverage_native']) {
            $counts['COVERAGE_NATIVE']++;
        }
        if ($facts['coverage_inferred']) {
            $counts['COVERAGE_INFERRED']++;
        }
    }

    /**
     * @param array{code: string, kind: string, numerator?: string|null, denominator?: string|null} $def
     * @param array<string, int> $counts
     * @param array{scope_type: string, scope_id: string|null} $scope
     * @return array<string, mixed>
     */
    private function buildMetricRow(array $def, array $counts, array $scope, int $windowDays, int $minSample): array
    {
        $code = $def['code'];
        $kind = $def['kind'];
        if ($kind === 'rate') {
            $numKey = (string) ($def['numerator'] ?? '');
            $denKey = (string) ($def['denominator'] ?? '');
            $numerator = (int) ($counts[$numKey] ?? 0);
            $denominator = (int) ($counts[$denKey] ?? 0);
            $sample = $denominator;
            if ($denominator <= 0) {
                return [
                    'scope_type' => $scope['scope_type'],
                    'scope_id' => $scope['scope_id'],
                    'window_days' => $windowDays,
                    'metric_code' => $code,
                    'numerator' => $numerator,
                    'denominator' => $denominator,
                    'value' => null,
                    'sample_size' => $sample,
                    'confidence_status' => PersonaTurnosPerfilMetrica::CONFIDENCE_NOT_APPLICABLE,
                ];
            }
            if ($sample < $minSample) {
                return [
                    'scope_type' => $scope['scope_type'],
                    'scope_id' => $scope['scope_id'],
                    'window_days' => $windowDays,
                    'metric_code' => $code,
                    'numerator' => $numerator,
                    'denominator' => $denominator,
                    'value' => null,
                    'sample_size' => $sample,
                    'confidence_status' => PersonaTurnosPerfilMetrica::CONFIDENCE_INSUFFICIENT_DATA,
                ];
            }

            return [
                'scope_type' => $scope['scope_type'],
                'scope_id' => $scope['scope_id'],
                'window_days' => $windowDays,
                'metric_code' => $code,
                'numerator' => $numerator,
                'denominator' => $denominator,
                'value' => round($numerator / $denominator, 6),
                'sample_size' => $sample,
                'confidence_status' => PersonaTurnosPerfilMetrica::CONFIDENCE_OK,
            ];
        }

        $numerator = (int) ($counts[$code] ?? 0);

        return [
            'scope_type' => $scope['scope_type'],
            'scope_id' => $scope['scope_id'],
            'window_days' => $windowDays,
            'metric_code' => $code,
            'numerator' => $numerator,
            'denominator' => null,
            'value' => (float) $numerator,
            'sample_size' => $numerator,
            'confidence_status' => PersonaTurnosPerfilMetrica::CONFIDENCE_OK,
        ];
    }
}
