<?php

namespace common\components\Domain\Clinical\CarePlan\Reminder;

final class CarePlanReminderItemDto
{
    public int $carePlanId;
    public int $activityId;
    public string $kind;
    public int $resourceId;
    public string $title;
    public string $subtitle;
    public string $planStatus;
    /** Texto corto para notificación local (ej. "Medicación", "Recordatorio de estudio"). */
    public string $notificationLabel = 'Medicación';
    public bool $requiresPatientSetup = false;

    /** @var array<string, mixed>|null */
    public ?array $schedule = null;

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'carePlanId' => $this->carePlanId,
            'activityId' => $this->activityId,
            'kind' => $this->kind,
            'resourceId' => $this->resourceId,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'notificationLabel' => $this->notificationLabel,
            'planStatus' => $this->planStatus,
            'requiresPatientSetup' => $this->requiresPatientSetup,
            'schedule' => $this->schedule,
        ];
    }
}
