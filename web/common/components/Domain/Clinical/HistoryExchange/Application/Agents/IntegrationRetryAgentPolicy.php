<?php

namespace common\components\Domain\Clinical\HistoryExchange\Application\Agents;

/**
 * Política operativa del agente `integration-retry` (ex YAML platform/agents).
 */
final class IntegrationRetryAgentPolicy
{
    public const AGENT_ID = 'integration-retry';

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return [
        'version' => 1,
        'agent_id' => 'integration-retry',
        'connectors' => [
            'clinical_history_exchange' => [
                'dead_letter_notify' => true,
            ],
        ],
    ];
    }
}
