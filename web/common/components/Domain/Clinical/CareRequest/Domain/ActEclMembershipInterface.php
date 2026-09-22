<?php

namespace common\components\Domain\Clinical\CareRequest\Domain;

/**
 * ¿El código de acto pertenece a una expresión ECL?
 */
interface ActEclMembershipInterface
{
    /**
     * Fail-soft: ante error de terminología devolver false (no tumbar el pedido).
     */
    public function matches(string $code, string $system, string $ecl): bool;
}
