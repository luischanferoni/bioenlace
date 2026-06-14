<?php

namespace common\components\Domain\Clinical\CarePlan\Reminder;

/**
 * Normaliza {@see \common\models\Clinical\MedicationRequest::dosage_json} (timing v1).
 */
final class MedicationDosageTimingParser
{
    private ActivityReminderTimingParser $parser;

    public function __construct(?ActivityReminderTimingParser $parser = null)
    {
        $this->parser = $parser ?? new ActivityReminderTimingParser();
    }

    /**
     * @return array{timeOfDay: list<string>, period: int, periodUnit: string}|null
     */
    public function parse(?string $dosageJson): ?array
    {
        return $this->parser->parse($dosageJson);
    }
}
