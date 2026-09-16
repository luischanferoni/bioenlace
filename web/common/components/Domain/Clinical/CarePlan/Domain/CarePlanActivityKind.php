<?php

namespace common\components\Domain\Clinical\CarePlan\Domain;

final class CarePlanActivityKind
{
    public const MEDICATION_REQUEST = 'medication-request';
    public const SERVICE_REQUEST = 'service-request';
    public const DEVICE_REQUEST = 'device-request';
    public const NUTRITION_ORDER = 'nutrition-order';
    public const PROCEDURE = 'procedure';
    public const GOAL = 'goal';
}
