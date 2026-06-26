<?php

namespace common\components\Domain\Scheduling\Service;

use common\components\Platform\Core\Product\AutonomousAgentMetadata;
use common\models\Scheduling\Turno;
use common\models\TurnoResolucion;
use Yii;

/**
 * Recolecta candidatos y aplica score declarativo para shortlist A01 v1.
 */
final class TurnoResolucionShortlistService
{
    public const AGENT_ID = 'turno-resolucion-shortlist';

    /**
     * @return list<array<string, mixed>>
     */
    public function buildTopOptions(Turno $turno, TurnoResolucion $res, ?array $config = null): array
    {
        $config = $config ?? AutonomousAgentMetadata::loadAgent(self::AGENT_ID) ?? [];
        $maxOptions = max(1, (int) ($config['max_options'] ?? 3));

        $candidates = $this->collectCandidates($turno, $res, $config);
        if ($candidates === []) {
            return [];
        }

        $scored = [];
        foreach ($candidates as $candidate) {
            $scored[] = array_merge($candidate, [
                'score' => $this->scoreCandidate($turno, $candidate, $config),
            ]);
        }

        usort($scored, static function (array $a, array $b): int {
            return ($b['score'] <=> $a['score'])
                ?: strcmp((string) ($a['fecha'] ?? ''), (string) ($b['fecha'] ?? ''))
                ?: strcmp((string) ($a['hora'] ?? ''), (string) ($b['hora'] ?? ''));
        });

        $top = array_slice($scored, 0, $maxOptions);
        $out = [];
        foreach ($top as $i => $row) {
            $out[] = $this->normalizeOption($row, $i);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $config
     * @return list<array<string, mixed>>
     */
    private function collectCandidates(Turno $turno, TurnoResolucion $res, array $config): array
    {
        $out = [];
        $idPes = (int) ($turno->id_profesional_efector_servicio ?? 0);
        $fecha = (string) $turno->fecha;

        if ($res->opcion_hora_antes !== null && trim((string) $res->opcion_hora_antes) !== '') {
            $hora = substr(TurnoResolucion::normalizarHora((string) $res->opcion_hora_antes), 0, 5);
            if ($this->isSlotAvailable($idPes, $fecha, $hora, (int) $turno->id_turnos)) {
                $out[] = [
                    'kind' => 'neighbor',
                    'eleccion' => 'antes',
                    'fecha' => $fecha,
                    'hora' => $hora,
                    'id_profesional_efector_servicio' => $idPes,
                    'id_efector' => (int) $turno->id_efector,
                    'id_servicio' => (int) ($turno->id_servicio_asignado ?? 0),
                ];
            }
        }

        if ($res->opcion_hora_despues !== null && trim((string) $res->opcion_hora_despues) !== '') {
            $hora = substr(TurnoResolucion::normalizarHora((string) $res->opcion_hora_despues), 0, 5);
            if ($this->isSlotAvailable($idPes, $fecha, $hora, (int) $turno->id_turnos)) {
                $out[] = [
                    'kind' => 'neighbor',
                    'eleccion' => 'despues',
                    'fecha' => $fecha,
                    'hora' => $hora,
                    'id_profesional_efector_servicio' => $idPes,
                    'id_efector' => (int) $turno->id_efector,
                    'id_servicio' => (int) ($turno->id_servicio_asignado ?? 0),
                ];
            }
        }

        $poolMax = max(5, (int) ($config['candidate_pool_max'] ?? 40));
        $maxDias = max(1, (int) ($config['search_max_dias'] ?? 14));
        $pacienteCfg = Yii::$app->params['turnosPaciente'] ?? [];

        $criteria = [
            'id_servicio' => (int) ($turno->id_servicio_asignado ?? 0),
            'id_efector' => (int) $turno->id_efector,
            'fecha_desde' => date('Y-m-d'),
            'max_dias' => $maxDias,
            'min_minutos_desde_ahora' => (int) ($pacienteCfg['slots_min_minutos_desde_ahora'] ?? 15),
        ];

        if (!$res->permitir_otro_pes && $idPes > 0) {
            $criteria['id_profesional_efector_servicio'] = $idPes;
        }

        if ((int) ($criteria['id_servicio'] ?? 0) <= 0) {
            return $this->dedupeCandidates($out);
        }

        try {
            $slots = TurnoSlotFinder::findAvailableSlots($criteria, $poolMax);
        } catch (\Throwable $e) {
            Yii::warning('Shortlist slot search: ' . $e->getMessage(), 'turno-resolucion-shortlist');

            return $this->dedupeCandidates($out);
        }

        foreach ($slots as $slot) {
            if (!is_array($slot)) {
                continue;
            }
            $slotPes = (int) ($slot['id_profesional_efector_servicio'] ?? 0);
            $slotEfector = (int) ($slot['id_efector'] ?? 0);
            if (!$res->permitir_otro_efector && $slotEfector > 0 && $slotEfector !== (int) $turno->id_efector) {
                continue;
            }
            if (!$res->permitir_otro_pes && $slotPes > 0 && $slotPes !== $idPes) {
                continue;
            }
            $out[] = [
                'kind' => 'slot',
                'fecha' => (string) ($slot['fecha'] ?? ''),
                'hora' => substr((string) ($slot['hora'] ?? ''), 0, 5),
                'id_profesional_efector_servicio' => $slotPes,
                'id_efector' => $slotEfector > 0 ? $slotEfector : (int) $turno->id_efector,
                'id_servicio' => (int) ($slot['id_servicio'] ?? $criteria['id_servicio']),
                'profesional' => isset($slot['profesional']) ? (string) $slot['profesional'] : null,
            ];
        }

        return $this->dedupeCandidates($out);
    }

    /**
     * @param array<string, mixed> $candidate
     * @param array<string, mixed> $config
     */
    public static function scoreCandidate(Turno $turno, array $candidate, array $config): int
    {
        $weights = is_array($config['scoring'] ?? null) ? $config['scoring'] : [];
        $score = 0;

        $turnoPes = (int) ($turno->id_profesional_efector_servicio ?? 0);
        $candPes = (int) ($candidate['id_profesional_efector_servicio'] ?? 0);
        if ($turnoPes > 0 && $candPes === $turnoPes) {
            $score += (int) ($weights['same_pes'] ?? 30);
        }

        if (($candidate['kind'] ?? '') === 'neighbor') {
            $score += (int) ($weights['neighbor_option'] ?? 25);
        }

        $fechaCand = (string) ($candidate['fecha'] ?? '');
        if ($fechaCand !== '' && $fechaCand === (string) $turno->fecha) {
            $score += (int) ($weights['same_date_as_original'] ?? 15);
        }

        $days = self::daysUntil($fechaCand);
        $maxDays = max(1, (int) ($weights['max_proximity_days'] ?? 14));
        $perDay = (int) ($weights['proximity_per_day'] ?? 2);
        if ($days >= 0 && $days <= $maxDays) {
            $score += ($maxDays - $days) * $perDay;
        }

        return $score;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeOption(array $row, int $index): array
    {
        $fecha = (string) ($row['fecha'] ?? '');
        $hora = substr((string) ($row['hora'] ?? ''), 0, 5);
        $label = $fecha . ' ' . $hora;
        if (($row['kind'] ?? '') === 'neighbor') {
            $label = ($row['eleccion'] === 'antes' ? 'Antes' : 'Después') . ' · ' . $label;
        }

        return [
            'option_id' => 'sl_' . $index,
            'score' => (int) ($row['score'] ?? 0),
            'kind' => (string) ($row['kind'] ?? 'slot'),
            'eleccion' => isset($row['eleccion']) ? (string) $row['eleccion'] : null,
            'fecha' => $fecha,
            'hora' => $hora,
            'id_profesional_efector_servicio' => (int) ($row['id_profesional_efector_servicio'] ?? 0),
            'id_efector' => (int) ($row['id_efector'] ?? 0),
            'id_servicio' => (int) ($row['id_servicio'] ?? 0),
            'label' => $label,
        ];
    }

    /**
     * @param list<array<string, mixed>> $candidates
     * @return list<array<string, mixed>>
     */
    private function dedupeCandidates(array $candidates): array
    {
        $seen = [];
        $out = [];
        foreach ($candidates as $c) {
            $key = ($c['fecha'] ?? '') . '|' . ($c['hora'] ?? '') . '|' . ($c['id_profesional_efector_servicio'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $c;
        }

        return $out;
    }

    private function isSlotAvailable(int $idPes, string $fecha, string $hora, int $excludeTurnoId): bool
    {
        if ($idPes <= 0 || $fecha === '' || $hora === '') {
            return false;
        }

        return TurnoSlotOccupancyService::estaDisponibleSlot($idPes, $fecha, $hora, $excludeTurnoId);
    }

    private static function daysUntil(string $fecha): int
    {
        if ($fecha === '') {
            return 999;
        }
        try {
            $target = new \DateTimeImmutable($fecha);
            $today = new \DateTimeImmutable('today');

            return (int) floor(($target->getTimestamp() - $today->getTimestamp()) / 86400);
        } catch (\Throwable $e) {
            return 999;
        }
    }
}
