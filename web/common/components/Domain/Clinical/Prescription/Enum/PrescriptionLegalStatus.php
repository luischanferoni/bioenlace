<?php

namespace common\components\Domain\Clinical\Prescription\Enum;

/** Estado del documento legal de receta electrónica (no confundir con RequestStatus de órdenes). */
final class PrescriptionLegalStatus
{
    public const DRAFT = 'draft';
    public const ISSUED = 'issued';
    public const CANCELLED = 'cancelled';
    public const EXPIRED = 'expired';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::DRAFT, self::ISSUED, self::CANCELLED, self::EXPIRED];
    }
}
