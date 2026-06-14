<?php

namespace common\components\Domain\Clinical\Enum;

/** MedicationRequest, ServiceRequest, DeviceRequest, NutritionOrder. */
final class RequestStatus
{
    public const DRAFT = 'draft';
    public const ACTIVE = 'active';
    public const ON_HOLD = 'on-hold';
    public const REVOKED = 'revoked';
    public const COMPLETED = 'completed';
    public const ENTERED_IN_ERROR = 'entered-in-error';
    public const UNKNOWN = 'unknown';
}
