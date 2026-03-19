<?php

namespace common\components\Services\Turnos;

/**
 * Oferta de múltiples slots alternativos.
 */
class TurnoSlotOfferService
{
    /**
     * @param array $criteria mismo contrato que TurnoSlotFinder::findFirstAvailable
     * @param int $limit
     * @return array[] lista de slots
     */
    public function findSlots(array $criteria, $limit = 10)
    {
        return TurnoSlotFinder::findAvailableSlots($criteria, (int) $limit);
    }
}
