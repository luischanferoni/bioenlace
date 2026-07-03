<?php

namespace common\components\Domain\Clinical\Service\EncounterJourney;

use common\models\Clinical\CareFollowupResponse;
use common\models\Clinical\CareFollowupTouchpointQueue;

/**
 * Touchpoints de seguimiento post-consulta para el journey (sin hardcode en app).
 */
final class EncounterJourneyFollowupStateService
{
    /**
     * @return array{
     *   touchpoint_count: int,
     *   open_count: int,
     *   actionable_count: int,
     *   next_touchpoint_id: int|null,
     *   next_touchpoint_title: string|null,
     *   items: list<array{
     *     id: int,
     *     title: string,
     *     estado: string,
     *     run_at: string|null,
     *     actionable: bool,
     *     completed: bool
     *   }>
     * }
     */
    public function stateForEncounter(int $encounterId): array
    {
        if ($encounterId <= 0) {
            return $this->emptyState();
        }

        $rows = CareFollowupTouchpointQueue::find()
            ->where(['encounter_id' => $encounterId])
            ->andWhere(['not in', 'estado', [
                CareFollowupTouchpointQueue::ESTADO_CANCELADA,
                CareFollowupTouchpointQueue::ESTADO_FALLIDA,
            ]])
            ->orderBy(['run_at' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        if ($rows === []) {
            return $this->emptyState();
        }

        $now = date('Y-m-d H:i:s');
        $items = [];
        $openCount = 0;
        $actionableCount = 0;
        $nextId = null;
        $nextTitle = null;

        foreach ($rows as $row) {
            /** @var CareFollowupTouchpointQueue $row */
            $completed = $row->estado === CareFollowupTouchpointQueue::ESTADO_COMPLETADA
                || CareFollowupResponse::find()
                    ->where(['touchpoint_queue_id' => (int) $row->id])
                    ->exists();

            $actionable = false;
            if (!$completed) {
                $openCount++;
                $actionable = $row->estado === CareFollowupTouchpointQueue::ESTADO_NOTIFICADA
                    || ($row->estado === CareFollowupTouchpointQueue::ESTADO_PENDIENTE
                        && (string) $row->run_at !== ''
                        && $row->run_at <= $now);
                if ($actionable) {
                    $actionableCount++;
                    if ($nextId === null) {
                        $nextId = (int) $row->id;
                        $nextTitle = trim((string) $row->title) ?: 'Seguimiento post-consulta';
                    }
                }
            }

            $items[] = [
                'id' => (int) $row->id,
                'title' => trim((string) $row->title) ?: 'Seguimiento',
                'estado' => (string) $row->estado,
                'run_at' => $row->run_at !== null ? (string) $row->run_at : null,
                'actionable' => $actionable,
                'completed' => $completed,
            ];
        }

        return [
            'touchpoint_count' => count($items),
            'open_count' => $openCount,
            'actionable_count' => $actionableCount,
            'next_touchpoint_id' => $nextId,
            'next_touchpoint_title' => $nextTitle,
            'items' => $items,
        ];
    }

    public function isPhaseCompleted(int $encounterId): bool
    {
        $state = $this->stateForEncounter($encounterId);

        return $state['touchpoint_count'] > 0 && $state['open_count'] === 0;
    }

    /**
     * @return array{
     *   touchpoint_count: int,
     *   open_count: int,
     *   actionable_count: int,
     *   next_touchpoint_id: int|null,
     *   next_touchpoint_title: string|null,
     *   items: list<array<string, mixed>>
     * }
     */
    private function emptyState(): array
    {
        return [
            'touchpoint_count' => 0,
            'open_count' => 0,
            'actionable_count' => 0,
            'next_touchpoint_id' => null,
            'next_touchpoint_title' => null,
            'items' => [],
        ];
    }
}
