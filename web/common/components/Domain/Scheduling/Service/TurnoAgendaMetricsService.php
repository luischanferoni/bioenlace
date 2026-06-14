<?php

namespace common\components\Domain\Scheduling\Service;

use common\models\Scheduling\Turno;
use yii\db\Query;

/**
 * KPIs de agenda: no-show e intervalo reserva → cita.
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

        $base = (new Query())
            ->from(['t' => Turno::tableName()])
            ->where(['t.id_efector' => $idEfector])
            ->andWhere(['between', 't.fecha', $desde, $hasta]);

        if ($idPes > 0) {
            $base->andWhere(['t.id_profesional_efector_servicio' => $idPes]);
        }
        if ($idServicio > 0) {
            $base->andWhere(['t.id_servicio_asignado' => $idServicio]);
        }

        $total = (int) (clone $base)->count('*', Turno::getDb());

        $noShow = (int) (clone $base)
            ->andWhere(['t.estado' => Turno::ESTADO_SIN_ATENDER])
            ->andWhere(['t.estado_motivo' => Turno::ESTADO_MOTIVO_SIN_ATENDER_PACIENTE])
            ->count('*', Turno::getDb());

        $atendidos = (int) (clone $base)
            ->andWhere(['t.estado' => Turno::ESTADO_ATENDIDO])
            ->count('*', Turno::getDb());

        $cerrados = $noShow + $atendidos;
        $noShowRate = $cerrados > 0 ? round(100.0 * $noShow / $cerrados, 1) : null;

        $leadQuery = (clone $base)
            ->select(['t.fecha', 't.hora', 't.fecha_alta'])
            ->andWhere(['not', ['t.fecha_alta' => null]])
            ->andWhere(['<>', 't.fecha_alta', ''])
            ->andWhere(['not in', 't.estado', [Turno::ESTADO_CANCELADO]]);

        $leadDays = [];
        foreach ($leadQuery->all(Turno::getDb()) as $row) {
            $dias = $this->diasHastaCita(
                (string) ($row['fecha'] ?? ''),
                (string) ($row['hora'] ?? ''),
                (string) ($row['fecha_alta'] ?? '')
            );
            if ($dias !== null && $dias >= 0) {
                $leadDays[] = $dias;
            }
        }

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
            'resumen_texto' => $this->formatResumenTexto($desde, $hasta, $total, $noShow, $noShowRate, $leadDays),
        ];
    }

    private function diasHastaCita(string $fechaTurno, string $hora, string $fechaAlta): ?int
    {
        $cita = $this->parseDateTime($fechaTurno, $hora);
        $alta = $this->parseDateTime($fechaAlta, null);
        if ($cita === null || $alta === null) {
            return null;
        }

        return (int) floor(($cita->getTimestamp() - $alta->getTimestamp()) / 86400);
    }

    private function parseDateTime(string $date, ?string $time): ?\DateTimeImmutable
    {
        $date = trim($date);
        if ($date === '') {
            return null;
        }
        $time = $time !== null && trim($time) !== '' ? trim($time) : '00:00:00';
        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'd/m/Y H:i:s', 'd/m/Y H:i', 'Y-m-d', 'd/m/Y'] as $fmt) {
            $raw = str_contains($fmt, 'H') ? "{$date} {$time}" : $date;
            $dt = \DateTimeImmutable::createFromFormat($fmt, $raw);
            if ($dt !== false) {
                return $dt;
            }
        }

        try {
            return new \DateTimeImmutable($date . ' ' . $time);
        } catch (\Exception $e) {
            return null;
        }
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
