<?php

namespace common\components\Domain\Scheduling\BehaviorProfile\Application\Service;

use common\components\Domain\Scheduling\BehaviorProfile\Domain\Catalog\TurnoBehaviorProfileContract;
use common\models\Scheduling\TurnoEventoAudit;
use yii\db\Query;

/**
 * Reportes de cobertura y anomalías del stream canónico (solo NATIVE).
 */
final class TurnoBehaviorStreamCoverageService
{
    private TurnoBehaviorProfileContract $contract;

    public function __construct(?TurnoBehaviorProfileContract $contract = null)
    {
        $this->contract = $contract ?? new TurnoBehaviorProfileContract();
    }

    /**
     * @return array<string, mixed>
     */
    public function summarize(?string $since = null): array
    {
        $base = (new Query())->from(['e' => TurnoEventoAudit::tableName()]);
        if ($since !== null && $since !== '') {
            $base->andWhere(['>=', 'e.occurred_at', $since]);
        }

        $total = (int) (clone $base)->count('*');
        $withActor = (int) (clone $base)
            ->andWhere(['not', ['e.actor_type' => null]])
            ->andWhere(['<>', 'e.actor_type', ''])
            ->count('*');
        $withOrigin = (int) (clone $base)
            ->andWhere(['not', ['e.origin' => null]])
            ->andWhere(['<>', 'e.origin', ''])
            ->count('*');
        $native = (int) (clone $base)
            ->andWhere(['e.attribution_quality' => TurnoEventoAudit::QUALITY_NATIVE])
            ->count('*');
        $nonNative = (int) (clone $base)
            ->andWhere(['or',
                ['e.attribution_quality' => null],
                ['<>', 'e.attribution_quality', TurnoEventoAudit::QUALITY_NATIVE],
            ])
            ->count('*');
        $missingActor = (int) (clone $base)
            ->andWhere(['or', ['e.actor_type' => null], ['e.actor_type' => '']])
            ->count('*');

        $byEvent = (new Query())
            ->select(['event_code' => 'e.event_code', 'cnt' => 'COUNT(*)'])
            ->from(['e' => TurnoEventoAudit::tableName()])
            ->groupBy(['e.event_code'])
            ->orderBy(['cnt' => SORT_DESC]);
        if ($since !== null && $since !== '') {
            $byEvent->andWhere(['>=', 'e.occurred_at', $since]);
        }
        $eventRows = $byEvent->all();

        $knownCodes = $this->contract->eventCodes();
        $unknownCodes = [];
        foreach ($eventRows as $row) {
            $code = (string) ($row['event_code'] ?? '');
            if ($code !== '' && !in_array($code, $knownCodes, true)) {
                $unknownCodes[] = $code;
            }
        }

        $first = (new Query())
            ->from(TurnoEventoAudit::tableName())
            ->min('occurred_at');
        $last = (new Query())
            ->from(TurnoEventoAudit::tableName())
            ->max('occurred_at');

        return [
            'contract_version' => $this->contract->version(),
            'since' => $since,
            'stream_from' => $first !== false && $first !== null ? (string) $first : null,
            'stream_to' => $last !== false && $last !== null ? (string) $last : null,
            'events_total' => $total,
            'events_native' => $native,
            'events_non_native' => $nonNative,
            'with_actor_type' => $withActor,
            'with_origin' => $withOrigin,
            'missing_actor_type' => $missingActor,
            'actor_coverage_rate' => $total > 0 ? round($withActor / $total, 4) : null,
            'origin_coverage_rate' => $total > 0 ? round($withOrigin / $total, 4) : null,
            'native_coverage_rate' => $total > 0 ? round($native / $total, 4) : null,
            'by_event_code' => array_map(static function (array $row): array {
                return [
                    'event_code' => (string) ($row['event_code'] ?? ''),
                    'count' => (int) ($row['cnt'] ?? 0),
                ];
            }, $eventRows),
            'anomalies' => [
                'unknown_event_codes' => $unknownCodes,
                'non_native_count' => $nonNative,
                'missing_actor_count' => $missingActor,
            ],
        ];
    }
}
