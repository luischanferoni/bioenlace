<?php

namespace common\components\Domain\Clinical\CarePlan\Reminder;

/**
 * Infiere tomas diarias desde texto de posología (sin horarios concretos).
 */
final class MedicationReminderFrequencyResolver
{
    /**
     * @return array{dosesPerDay: int, intervalHours: int, period: int, periodUnit: string}
     */
    public function resolveFromDosageText(?string $dosageText): array
    {
        $text = mb_strtolower(trim((string) $dosageText));
        if ($text === '') {
            return $this->defaultPattern();
        }

        if (preg_match('/cada\s+(\d{1,2})\s*h(?:ora)?s?/u', $text, $m) === 1) {
            $intervalHours = max(1, min(24, (int) $m[1]));
            $dosesPerDay = max(1, (int) floor(24 / $intervalHours));

            return $this->pattern($dosesPerDay, $intervalHours);
        }

        if (preg_match('/(\d{1,2})\s*veces?\s+al\s+d[ií]a/u', $text, $m) === 1) {
            $dosesPerDay = max(1, min(12, (int) $m[1]));

            return $this->pattern($dosesPerDay, $this->evenIntervalHours($dosesPerDay));
        }

        $wordDoses = $this->wordDosesPerDay($text);
        if ($wordDoses !== null) {
            return $this->pattern($wordDoses, $this->evenIntervalHours($wordDoses));
        }

        if (preg_match('/(una\s+vez|1\s+vez|al\s+d[ií]a|por\s+d[ií]a|diari[ao])/u', $text) === 1) {
            return $this->pattern(1, 24);
        }

        return $this->defaultPattern();
    }

    /**
     * @return array{dosesPerDay: int, intervalHours: int, period: int, periodUnit: string}
     */
    private function defaultPattern(): array
    {
        return $this->pattern(1, 24);
    }

    /**
     * @return array{dosesPerDay: int, intervalHours: int, period: int, periodUnit: string}
     */
    private function pattern(int $dosesPerDay, int $intervalHours): array
    {
        $dosesPerDay = max(1, min(12, $dosesPerDay));
        $intervalHours = max(1, min(24, $intervalHours));

        return [
            'dosesPerDay' => $dosesPerDay,
            'intervalHours' => $intervalHours,
            'period' => 1,
            'periodUnit' => 'd',
        ];
    }

    private function evenIntervalHours(int $dosesPerDay): int
    {
        if ($dosesPerDay <= 1) {
            return 24;
        }

        return max(1, (int) floor(24 / $dosesPerDay));
    }

    private function wordDosesPerDay(string $text): ?int
    {
        $map = [
            'una vez' => 1,
            'dos veces' => 2,
            'tres veces' => 3,
            'cuatro veces' => 4,
            'cinco veces' => 5,
            'seis veces' => 6,
        ];
        foreach ($map as $phrase => $doses) {
            if (mb_strpos($text, $phrase . ' al d') !== false || mb_strpos($text, $phrase . ' por d') !== false) {
                return $doses;
            }
        }

        return null;
    }
}
