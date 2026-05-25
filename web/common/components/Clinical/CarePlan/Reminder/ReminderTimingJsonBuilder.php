<?php

namespace common\components\Clinical\CarePlan\Reminder;

/**
 * Construye JSON `{"timing":{"repeat":{...}}}` desde API (staff o paciente).
 */
final class ReminderTimingJsonBuilder
{
    /**
     * @param array<string, mixed> $body
     */
    public function fromRequestBody(array $body): ?string
    {
        if (isset($body['timing']) && is_array($body['timing'])) {
            return json_encode(['timing' => $body['timing']], JSON_UNESCAPED_UNICODE);
        }

        $times = $body['time_of_day'] ?? $body['timeOfDay'] ?? null;
        if (!is_array($times) || $times === []) {
            return null;
        }

        $normalized = [];
        $parser = new ActivityReminderTimingParser();
        foreach ($times as $t) {
            $parsed = $parser->parse(json_encode([
                'timing' => ['repeat' => ['timeOfDay' => [(string) $t]]],
            ]));
            if ($parsed !== null && $parsed['timeOfDay'] !== []) {
                $normalized = array_merge($normalized, $parsed['timeOfDay']);
            }
        }

        if ($normalized === []) {
            return null;
        }

        $normalized = array_values(array_unique($normalized));
        sort($normalized);

        return json_encode([
            'timing' => [
                'repeat' => [
                    'period' => (int) ($body['period'] ?? 1),
                    'periodUnit' => (string) ($body['period_unit'] ?? $body['periodUnit'] ?? 'd'),
                    'timeOfDay' => $normalized,
                ],
            ],
        ], JSON_UNESCAPED_UNICODE);
    }
}
