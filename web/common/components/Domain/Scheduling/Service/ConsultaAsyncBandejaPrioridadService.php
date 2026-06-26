<?php

namespace common\components\Domain\Scheduling\Service;

use common\models\ConsultaChatMessage;
use common\models\Clinical\Encounter;

/**
 * Score declarativo para ordenar la bandeja async (agente H01).
 */
final class ConsultaAsyncBandejaPrioridadService
{
    public const AGENT_ID = 'consulta-async-bandeja-prioridad';

    /**
     * @param array<string, mixed> $item Fila de bandeja (buildItem)
     * @param array<string, mixed> $config Metadata del agente
     * @return array{score: int, factors: array<string, int>}
     */
    public function computePrioridad(array $item, ?Encounter $encounter, array $config): array
    {
        $weights = is_array($config['scoring'] ?? null) ? $config['scoring'] : [];
        $factors = [];
        $score = 0;

        $band = strtoupper(trim((string) ($item['urgency_band'] ?? '')));
        $bandWeights = is_array($weights['urgency_band'] ?? null) ? $weights['urgency_band'] : [];
        $bandScore = (int) ($bandWeights[$band] ?? $bandWeights['default'] ?? 10);
        $factors['urgency_band'] = $bandScore;
        $score += $bandScore;

        $sla = is_array($item['sla'] ?? null) ? $item['sla'] : [];
        if (!empty($sla['incumplido'])) {
            $slaScore = (int) ($weights['sla_incumplido'] ?? 50);
            $factors['sla_incumplido'] = $slaScore;
            $score += $slaScore;
        }

        $createdAt = (string) ($item['created_at'] ?? '');
        $createdTs = strtotime($createdAt) ?: 0;
        if ($createdTs > 0) {
            $hours = max(0, (int) floor((time() - $createdTs) / 3600));
            $perHour = (int) ($weights['waiting_points_per_hour'] ?? 3);
            $cap = (int) ($weights['waiting_points_cap'] ?? 36);
            $waitScore = min($cap, $hours * $perHour);
            if ($waitScore > 0) {
                $factors['antiguedad'] = $waitScore;
                $score += $waitScore;
            }
        }

        $encounterId = (int) ($item['encounter_id'] ?? 0);
        if ($encounterId > 0 && $this->pacienteTieneMensajeSinRespuestaStaff($encounterId)) {
            $pendScore = (int) ($weights['paciente_sin_respuesta_staff'] ?? 15);
            $factors['paciente_sin_respuesta_staff'] = $pendScore;
            $score += $pendScore;
        }

        $asignacion = is_array($item['asignacion'] ?? null) ? $item['asignacion'] : [];
        if (($item['status'] ?? '') === 'in-progress' && !empty($asignacion['es_mio'])) {
            $mioScore = (int) ($weights['in_progress_asignado'] ?? 8);
            $factors['in_progress_asignado'] = $mioScore;
            $score += $mioScore;
        }

        return [
            'score' => $score,
            'factors' => $factors,
        ];
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    public function sortItems(array $items): array
    {
        usort($items, static function (array $a, array $b): int {
            $sa = (int) (($a['prioridad']['score'] ?? 0));
            $sb = (int) (($b['prioridad']['score'] ?? 0));
            if ($sa !== $sb) {
                return $sb <=> $sa;
            }

            return strcmp((string) ($a['created_at'] ?? ''), (string) ($b['created_at'] ?? ''));
        });

        foreach ($items as $i => &$row) {
            if (!isset($row['prioridad']) || !is_array($row['prioridad'])) {
                $row['prioridad'] = ['score' => 0, 'factors' => []];
            }
            $row['prioridad']['rank'] = $i + 1;
        }
        unset($row);

        return $items;
    }

    public function pacienteTieneMensajeSinRespuestaStaff(int $encounterId): bool
    {
        if ($encounterId <= 0) {
            return false;
        }

        $lastStaff = ConsultaChatMessage::find()
            ->where(['encounter_id' => $encounterId])
            ->andWhere(['user_role' => ['medico', 'enfermeria']])
            ->orderBy(['created_at' => SORT_DESC])
            ->one();

        $query = ConsultaChatMessage::find()
            ->where(['encounter_id' => $encounterId, 'user_role' => 'paciente']);

        if ($lastStaff !== null) {
            $query->andWhere(['>', 'created_at', $lastStaff->created_at]);
        }

        return $query->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function parseEncounterNote(?Encounter $encounter): array
    {
        if ($encounter === null || $encounter->note === null || trim((string) $encounter->note) === '') {
            return [];
        }
        $decoded = json_decode((string) $encounter->note, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $note
     */
    public function persistSlaEscalationFlag(Encounter $encounter, array $note): void
    {
        if (!isset($note['agent_meta']) || !is_array($note['agent_meta'])) {
            $note['agent_meta'] = [];
        }
        if (!isset($note['agent_meta']['consulta_async_prioridad']) || !is_array($note['agent_meta']['consulta_async_prioridad'])) {
            $note['agent_meta']['consulta_async_prioridad'] = [];
        }
        $note['agent_meta']['consulta_async_prioridad']['sla_escalated_at'] = date('Y-m-d H:i:s');
        $encounter->note = json_encode($note, JSON_UNESCAPED_UNICODE);
        $encounter->save(false, ['note', 'updated_at', 'updated_by']);
    }

    public function wasSlaEscalated(?Encounter $encounter): bool
    {
        $note = $this->parseEncounterNote($encounter);

        return isset($note['agent_meta']['consulta_async_prioridad']['sla_escalated_at']);
    }
}
