<?php

namespace common\tests\unit\clinical;

use common\components\Domain\Clinical\HistoryExchange\ClinicalHistoryOutboundRetryPolicy;

class ClinicalHistoryOutboundRetryPolicyTest extends \Codeception\Test\Unit
{
    private function retryConfig(): array
    {
        return [
            'max_attempts' => 5,
            'backoff_seconds' => [60, 300, 900, 3600, 14400],
        ];
    }

    public function testMarksDeadWhenNotRetryable(): void
    {
        verify(ClinicalHistoryOutboundRetryPolicy::shouldMarkDead(1, false, $this->retryConfig()))->true();
    }

    public function testMarksDeadWhenAttemptsExhausted(): void
    {
        verify(ClinicalHistoryOutboundRetryPolicy::shouldMarkDead(5, true, $this->retryConfig()))->true();
        verify(ClinicalHistoryOutboundRetryPolicy::shouldMarkDead(4, true, $this->retryConfig()))->false();
    }

    public function testBackoffUsesConfiguredSteps(): void
    {
        $base = strtotime('2026-06-18 12:00:00');
        verify(ClinicalHistoryOutboundRetryPolicy::nextRunAt(1, $this->retryConfig(), $base))
            ->equals('2026-06-18 12:01:00');
        verify(ClinicalHistoryOutboundRetryPolicy::nextRunAt(2, $this->retryConfig(), $base))
            ->equals('2026-06-18 12:05:00');
        verify(ClinicalHistoryOutboundRetryPolicy::nextRunAt(5, $this->retryConfig(), $base))
            ->equals('2026-06-18 16:00:00');
    }
}
