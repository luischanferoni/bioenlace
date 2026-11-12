<?php

namespace common\components\Platform\Ai\Cost;

use common\models\Platform\AIUsageDaily;
use Yii;

/**
 * Agrega el JSONL de {@see AIUsageRecorder} en `ai_usage_daily`.
 *
 * El agregado es idempotente: borra las filas del día y las reinserta desde el
 * archivo. Correrlo dos veces no duplica.
 */
final class AIUsageAggregator
{
    /**
     * @return array{date:string,file:string,lines:int,rows:array<string,array<string,mixed>>}
     */
    public static function aggregateFile(string $path, ?string $date = null): array
    {
        $rows = [];
        $lines = 0;
        $date ??= date('Y-m-d');

        if (!is_file($path)) {
            return ['date' => $date, 'file' => $path, 'lines' => 0, 'rows' => []];
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return ['date' => $date, 'file' => $path, 'lines' => 0, 'rows' => []];
        }

        try {
            while (($raw = fgets($handle)) !== false) {
                $raw = trim($raw);
                if ($raw === '') {
                    continue;
                }
                $data = json_decode($raw, true);
                if (!is_array($data)) {
                    continue;
                }
                $lines++;
                $context = (string) ($data['ctx'] ?? 'desconocido');
                $efector = (int) ($data['efector'] ?? 0);
                $key = $context . '|' . $efector;
                if (!isset($rows[$key])) {
                    $rows[$key] = [
                        'usage_date' => (string) ($data['date'] ?? $date),
                        'context' => $context,
                        'id_efector' => $efector,
                        'calls' => 0,
                        'errors' => 0,
                        'prompt_tokens' => 0,
                        'cached_tokens' => 0,
                        'output_tokens' => 0,
                        'thoughts_tokens' => 0,
                        'latency_ms_sum' => 0,
                        'cost_usd' => 0.0,
                    ];
                }
                $row = &$rows[$key];
                $row['calls']++;
                if (empty($data['ok'])) {
                    $row['errors']++;
                }
                $row['prompt_tokens'] += (int) ($data['prompt'] ?? 0);
                $row['cached_tokens'] += (int) ($data['cached'] ?? 0);
                $row['output_tokens'] += (int) ($data['output'] ?? 0);
                $row['thoughts_tokens'] += (int) ($data['thoughts'] ?? 0);
                $row['latency_ms_sum'] += (int) ($data['ms'] ?? 0);
                $row['cost_usd'] += (float) ($data['usd'] ?? 0);
                unset($row);
            }
        } finally {
            fclose($handle);
        }

        return ['date' => $date, 'file' => $path, 'lines' => $lines, 'rows' => $rows];
    }

    /**
     * @return array{date:string,file:string,lines:int,rows:int,persisted:int}
     */
    public static function aggregate(string $date): array
    {
        $parsed = self::aggregateFile(AIUsageRecorder::logPath($date), $date);
        $persisted = self::persist($date, array_values($parsed['rows']));

        return [
            'date' => $date,
            'file' => $parsed['file'],
            'lines' => $parsed['lines'],
            'rows' => count($parsed['rows']),
            'persisted' => $persisted,
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public static function persist(string $date, array $rows): int
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            AIUsageDaily::deleteAll(['usage_date' => $date]);
            foreach ($rows as $row) {
                $model = new AIUsageDaily();
                $model->setAttributes($row, false);
                $model->cost_usd = round((float) ($row['cost_usd'] ?? 0), 8);
                if (!$model->save(false)) {
                    throw new \RuntimeException('No se pudo guardar el agregado de uso de IA.');
                }
            }
            $transaction->commit();

            return count($rows);
        } catch (\Throwable $e) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }
            throw $e;
        }
    }
}
