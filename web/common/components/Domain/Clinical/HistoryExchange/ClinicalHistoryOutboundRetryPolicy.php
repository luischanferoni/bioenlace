<?php

namespace common\components\Domain\Clinical\HistoryExchange;

/**
 * Política de reintentos para jobs de export FHIR (params clinicalHistoryExchange.retry).
 */
final class ClinicalHistoryOutboundRetryPolicy
{
    /**
     * @param array<string, mixed> $config
     */
    public static function shouldMarkDead(int $intentos, bool $retryable, array $config): bool
    {
        if (!$retryable) {
            return true;
        }

        $maxAttempts = (int) ($config['max_attempts'] ?? 5);

        return $intentos >= $maxAttempts;
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function nextRunAt(int $intentos, array $config, ?int $now = null): string
    {
        $backoff = $config['backoff_seconds'] ?? [60, 300, 900, 3600, 14400];
        if (!is_array($backoff) || $backoff === []) {
            $backoff = [60, 300, 900, 3600, 14400];
        }

        $idx = min(max(0, $intentos - 1), count($backoff) - 1);
        $wait = max(1, (int) ($backoff[$idx] ?? 300));
        $base = $now ?? time();

        return date('Y-m-d H:i:s', $base + $wait);
    }
}
