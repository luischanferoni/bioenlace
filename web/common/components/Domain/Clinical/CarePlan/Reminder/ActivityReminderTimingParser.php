<?php

namespace common\components\Domain\Clinical\CarePlan\Reminder;

/**
 * Parsea JSON con `timing.repeat.timeOfDay` (medicación, estudios, etc.).
 */
final class ActivityReminderTimingParser
{
    /**
     * @return array{timeOfDay: list<string>, period: int, periodUnit: string}|null
     */
    public function parse(?string $json): ?array
    {
        if ($json === null || trim($json) === '') {
            return null;
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return null;
        }

        $repeat = $decoded['timing']['repeat'] ?? null;
        if (!is_array($repeat)) {
            return null;
        }

        $rawTimes = $repeat['timeOfDay'] ?? null;
        if (!is_array($rawTimes) || $rawTimes === []) {
            return null;
        }

        $timeOfDay = [];
        foreach ($rawTimes as $t) {
            $normalized = $this->normalizeTime((string) $t);
            if ($normalized !== null) {
                $timeOfDay[] = $normalized;
            }
        }

        if ($timeOfDay === []) {
            return null;
        }

        $timeOfDay = array_values(array_unique($timeOfDay));
        sort($timeOfDay);

        $period = isset($repeat['period']) ? (int) $repeat['period'] : 1;
        if ($period < 1) {
            $period = 1;
        }

        $periodUnit = isset($repeat['periodUnit']) ? strtolower(trim((string) $repeat['periodUnit'])) : 'd';
        if ($periodUnit !== 'd' && $periodUnit !== 'wk') {
            $periodUnit = 'd';
        }

        return [
            'timeOfDay' => $timeOfDay,
            'period' => $period,
            'periodUnit' => $periodUnit,
        ];
    }

    private function normalizeTime(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{1,2}):(\d{2})$/', $value, $m) === 1) {
            $h = (int) $m[1];
            $min = (int) $m[2];
            if ($h >= 0 && $h <= 23 && $min >= 0 && $min <= 59) {
                return sprintf('%02d:%02d', $h, $min);
            }
        }

        return null;
    }
}
