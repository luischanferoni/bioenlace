<?php

namespace common\components\Domain\Scheduling\Service;

use common\models\TurnoEventoAudit;
use yii\db\Query;

/**
 * KPIs de agenda a partir del stream canónico (misma semántica factual que el perfil).
 */
final class TurnoAgendaMetricsService
{
    /**
     * @param array<string, mixed> $filters id_efector (requerido), fecha_desde, fecha_hasta, id_profesional_efector_servicio, id_servicio
     * @return array<string, mixed>
     */
    public function resumen(array $filters): array
    {
        $idEfector = (int) ($filters['id_efector'] ?? 0);
        if ($idEfector <= 0) {
            throw new \InvalidArgumentException('Se requiere id_efector.');
        }

        $hasta = isset($filters['fecha_hasta']) ? (string) $filters['fecha_hasta'] : date('Y-m-d');
        $desde = isset($filters['fecha_desde'])
            ? (string) $filters['fecha_desde']
            : date('Y-m-d', strtotime($hasta . ' -30 days'));

        $idPes = isset($filters['id_profesional_efector_servicio'])
            ? (int) $filters['id_profesional_efector_servicio']
            : 0;
        $idServicio = isset($filters['id_servicio']) ? (int) $filters['id_servicio'] : 0;

        $events = $this->loadEvents($idEfector, $desde, $hasta, $idPes, $idServicio);
        $byTurno = $this->reduceByTurno($events);

        $total = count($byTurno);
        $noShow = 0;
        $atendidos = 0;
        $coverageNative = 0;
        $leadDays = [];

        foreach ($byTurno as $facts) {
            if ($facts['attended']) {
                $atendidos++;
            }
            if ($facts['no_show_attributable']) {
                $noShow++;
            }
            if ($facts['closed_eligible']) {
                $coverageNative++;
            }
            if ($facts['lead_days'] !== null && $facts['lead_days'] >= 0) {
                $leadDays[] = $facts['lead_days'];
            }
        }

        $cerrados = $noShow + $atendidos;
        $noShowRate = $cerrados > 0 ? round(100.0 * $noShow / $cerrados, 1) : null;
        $coverageDenom = $cerrados;
        $coverageRate = $coverageDenom > 0
            ? round($coverageNative / $coverageDenom, 6)
            : null;

        return [
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'id_efector' => $idEfector,
            'turnos_total' => $total,
            'no_show' => $noShow,
            'atendidos' => $atendidos,
            'no_show_rate_pct' => $noShowRate,
            'dias_hasta_cita_mediana' => $this->median($leadDays),
            'dias_hasta_cita_promedio' => $leadDays !== [] ? round(array_sum($leadDays) / count($leadDays), 1) : null,
            'coverage_native' => $coverageNative,
            'coverage_rate' => $coverageRate,
            'resumen_texto' => $this->formatResumenTexto($desde, $hasta, $total, $noShow, $noShowRate, $leadDays),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadEvents(
        int $idEfector,
        string $desde,
        string $hasta,
        int $idPes,
        int $idServicio
    ): array {
        $q = (new Query())
            ->from(['e' => TurnoEventoAudit::tableName()])
            ->select([
                'e.id',
                'e.id_turno',
                'e.id_persona',
                'e.event_code',
                'e.tipo_evento',
                'e.actor_type',
                'e.attribution_quality',
                'e.occurred_at',
                'e.created_at',
                'e.appointment_at',
                'e.corrected_event_id',
                'e.id_efector',
                'e.id_servicio',
                'e.id_profesional_efector_servicio',
            ])
            ->where(['e.id_efector' => $idEfector])
            ->andWhere(['not', ['e.appointment_at' => null]])
            ->andWhere(['>=', 'e.appointment_at', $desde . ' 00:00:00'])
            ->andWhere(['<=', 'e.appointment_at', $hasta . ' 23:59:59'])
            ->orderBy(['e.id' => SORT_ASC]);

        if ($idPes > 0) {
            $q->andWhere(['e.id_profesional_efector_servicio' => $idPes]);
        }
        if ($idServicio > 0) {
            $q->andWhere(['e.id_servicio' => $idServicio]);
        }

        return $q->all(TurnoEventoAudit::getDb());
    }

    /**
     * @param list<array<string, mixed>> $events
     * @return array<int, array<string, mixed>>
     */
    private function reduceByTurno(array $events): array
    {
        $patientActors = [
            TurnoEventoAudit::ACTOR_PACIENTE,
            TurnoEventoAudit::ACTOR_REPRESENTANTE,
        ];
        $byTurno = [];

        foreach ($events as $raw) {
            $idTurno = (int) ($raw['id_turno'] ?? 0);
            if ($idTurno <= 0) {
                continue;
            }
            if (!isset($byTurno[$idTurno])) {
                $byTurno[$idTurno] = [
                    'attended' => false,
                    'no_show_attributable' => false,
                    'closed_eligible' => false,
                    'quality' => TurnoEventoAudit::QUALITY_NATIVE,
                    'created_ts' => null,
                    'appointment_ts' => null,
                    'lead_days' => null,
                    'corrected_no_show_ids' => [],
                ];
            }
            $code = (string) ($raw['event_code'] ?: $raw['tipo_evento']);
            $actor = (string) ($raw['actor_type'] ?? '');
            $quality = (string) ($raw['attribution_quality'] ?? TurnoEventoAudit::QUALITY_NATIVE);
            if ($quality !== '' && $quality !== TurnoEventoAudit::QUALITY_NATIVE) {
                continue;
            }
            $occurred = (string) ($raw['occurred_at'] ?: $raw['created_at']);
            $appointment = (string) ($raw['appointment_at'] ?? '');
            $occurredTs = $occurred !== '' ? (strtotime($occurred) ?: null) : null;
            $appointmentTs = $appointment !== '' ? (strtotime($appointment) ?: null) : null;

            if ($appointmentTs !== null) {
                $byTurno[$idTurno]['appointment_ts'] = $appointmentTs;
            }

            if ($code === TurnoEventoAudit::EVENT_APPOINTMENT_CREATED
                || $code === TurnoEventoAudit::TIPO_CREATE
            ) {
                $byTurno[$idTurno]['created_ts'] = $occurredTs;
            } elseif ($code === TurnoEventoAudit::EVENT_ATTENDED) {
                $byTurno[$idTurno]['attended'] = true;
                $byTurno[$idTurno]['no_show_attributable'] = false;
            } elseif ($code === TurnoEventoAudit::EVENT_NO_SHOW_RECORDED
                || $code === TurnoEventoAudit::TIPO_NO_SHOW
            ) {
                if (in_array($actor, $patientActors, true)) {
                    $byTurno[$idTurno]['no_show_attributable'] = true;
                }
            } elseif ($code === TurnoEventoAudit::EVENT_NO_SHOW_CORRECTED) {
                $byTurno[$idTurno]['no_show_attributable'] = false;
                $correctedId = (int) ($raw['corrected_event_id'] ?? 0);
                if ($correctedId > 0) {
                    $byTurno[$idTurno]['corrected_no_show_ids'][$correctedId] = true;
                }
            }
        }

        foreach ($byTurno as &$facts) {
            $facts['closed_eligible'] = $facts['attended'] || $facts['no_show_attributable'];
            if ($facts['created_ts'] !== null && $facts['appointment_ts'] !== null) {
                $facts['lead_days'] = (int) floor(
                    ($facts['appointment_ts'] - $facts['created_ts']) / 86400
                );
            }
        }
        unset($facts);

        return $byTurno;
    }

    /**
     * @param int[] $values
     */
    private function median(array $values): ?float
    {
        if ($values === []) {
            return null;
        }
        sort($values);
        $n = count($values);
        $mid = (int) floor($n / 2);

        return $n % 2 === 0
            ? round(($values[$mid - 1] + $values[$mid]) / 2, 1)
            : (float) $values[$mid];
    }

    /**
     * @param int[] $leadDays
     */
    private function formatResumenTexto(
        string $desde,
        string $hasta,
        int $total,
        int $noShow,
        ?float $noShowRate,
        array $leadDays
    ): string {
        $lines = [
            "Período: {$desde} a {$hasta}",
            "Turnos en el período: {$total}",
            "No-show (paciente): {$noShow}" . ($noShowRate !== null ? " ({$noShowRate}% sobre atendidos+no-show)" : ''),
        ];
        $med = $this->median($leadDays);
        if ($med !== null) {
            $lines[] = "Días hasta la cita (mediana desde reserva): {$med}";
        }

        return implode("\n", $lines);
    }
}
