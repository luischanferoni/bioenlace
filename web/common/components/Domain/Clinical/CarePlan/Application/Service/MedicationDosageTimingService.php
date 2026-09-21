<?php

namespace common\components\Domain\Clinical\CarePlan\Application\Service;

/**
 * Normaliza {@see \common\models\Clinical\MedicationRequest::dosage_json} (timing v1).
 */
final class MedicationDosageTimingService
{
    private ActivityReminderTimingService $parser;

    public function __construct(?ActivityReminderTimingService $parser = null)
    {
        $this->parser = $parser ?? new ActivityReminderTimingService();
    }

    /**
     * @return array{timeOfDay: list<string>, period: int, periodUnit: string}|null
     */
    public function parse(?string $dosageJson): ?array
    {
        return $this->parser->parse($dosageJson);
    }
}
