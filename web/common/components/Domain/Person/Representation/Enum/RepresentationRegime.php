<?php

namespace common\components\Domain\Person\Representation\Enum;

final class RepresentationRegime
{
    public const VERIFIED_GUARDIANSHIP = 'verified_guardianship';
    public const PATIENT_DELEGATION = 'patient_delegation';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::VERIFIED_GUARDIANSHIP, self::PATIENT_DELEGATION];
    }
}
