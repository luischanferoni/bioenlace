<?php

namespace console\controllers;

use common\components\Platform\Ai\Cost\AIUsageAggregator;
use common\components\Platform\Ai\Cost\AIUsageRecorder;
use common\models\Platform\AIUsageDaily;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Telemetría de uso de IA: agrega el JSONL a la base, reporta y purga.
 *
 * Uso:
 *   php yii ai-usage/aggregate                 # ayer
 *   php yii ai-usage/aggregate --date=2026-11-12
 *   php yii ai-usage/aggregate --all           # todos los archivos pendientes
 *   php yii ai-usage/report --from=2026-11-01 --to=2026-11-30 --group=context
 *   php yii ai-usage/prune --days=30
 */
class AiUsageController extends Controller
{
    public function actionAggregate($date = null, $all = false): int
    {
        $dates = $all
            ? AIUsageRecorder::availableDates()
            : [(string) ($date ?: date('Y-m-d', strtotime('-1 day')))];

        if ($dates === []) {
            $this->stdout("ai-usage: no hay archivos para agregar.\n");

            return ExitCode::OK;
        }

        foreach ($dates as $day) {
            $result = AIUsageAggregator::aggregate($day);
            $this->stdout(sprintf(
                "ai-usage %s: lines=%d rows=%d persisted=%d\n",
                $result['date'],
                $result['lines'],
                $result['rows'],
                $result['persisted']
            ));
        }

        return ExitCode::OK;
    }

    public function actionReport($from = null, $to = null, $group = 'context'): int
    {
        $from ??= date('Y-m-01');
        $to ??= date('Y-m-d');

        $groupColumn = match ($group) {
            'efector' => 'id_efector',
            'date' => 'usage_date',
            default => 'context',
        };

        $rows = AIUsageDaily::find()
            ->select([
                $groupColumn . ' AS grupo',
                'SUM(calls) AS calls',
                'SUM(errors) AS errors',
                'SUM(prompt_tokens) AS prompt_tokens',
                'SUM(cached_tokens) AS cached_tokens',
                'SUM(output_tokens) AS output_tokens',
                'SUM(thoughts_tokens) AS thoughts_tokens',
                'SUM(latency_ms_sum) AS latency_ms_sum',
                'SUM(cost_usd) AS cost_usd',
            ])
            ->where(['between', 'usage_date', $from, $to])
            ->groupBy($groupColumn)
            ->orderBy(['cost_usd' => SORT_DESC])
            ->asArray()
            ->all();

        $this->stdout(sprintf("Uso de IA %s → %s (agrupado por %s)\n\n", $from, $to, $group));
        $this->stdout(sprintf(
            "%-28s %8s %7s %12s %12s %12s %10s %12s\n",
            'grupo',
            'llamadas',
            'errores',
            'prompt',
            'cached',
            'output',
            'avg_ms',
            'costo_usd'
        ));

        $totalCalls = 0;
        $totalCost = 0.0;
        foreach ($rows as $row) {
            $calls = (int) $row['calls'];
            $totalCalls += $calls;
            $totalCost += (float) $row['cost_usd'];
            $avgMs = $calls > 0 ? (int) round(((int) $row['latency_ms_sum']) / $calls) : 0;
            $this->stdout(sprintf(
                "%-28s %8d %7d %12d %12d %12d %10d %12.6f\n",
                (string) $row['grupo'],
                $calls,
                (int) $row['errors'],
                (int) $row['prompt_tokens'],
                (int) $row['cached_tokens'],
                (int) $row['output_tokens'],
                $avgMs,
                (float) $row['cost_usd']
            ));
        }

        $this->stdout(sprintf("\nTotal: %d llamadas · USD %.6f\n", $totalCalls, $totalCost));

        return ExitCode::OK;
    }

    public function actionPrune($days = 30): int
    {
        $days = max(1, (int) $days);
        $cutoff = time() - ($days * 86400);
        $deleted = 0;

        foreach (glob(AIUsageRecorder::logDir() . '/ai-usage-*.jsonl') ?: [] as $path) {
            if (is_file($path) && filemtime($path) < $cutoff) {
                if (@unlink($path)) {
                    $deleted++;
                }
            }
        }

        $this->stdout("ai-usage prune: {$deleted} archivo(s) con más de {$days} días.\n");

        return ExitCode::OK;
    }

    public function actionStatus(): int
    {
        $this->stdout('Directorio: ' . AIUsageRecorder::logDir() . "\n");
        $this->stdout('Persistencia habilitada: ' . (AIUsageRecorder::enabled() ? 'sí' : 'no') . "\n");
        $dates = AIUsageRecorder::availableDates();
        $this->stdout('Archivos: ' . count($dates) . ($dates === [] ? '' : ' (' . $dates[0] . ' → ' . end($dates) . ')') . "\n");
        $this->stdout('Filas agregadas: ' . AIUsageDaily::find()->count() . "\n");

        return ExitCode::OK;
    }
}
