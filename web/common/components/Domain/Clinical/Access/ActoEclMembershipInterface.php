<?php

namespace common\components\Domain\Clinical\Access;

/**
 * ¿El código de acto pertenece a una expresión ECL?
 */
interface ActoEclMembershipInterface
{
    /**
     * Fail-soft: ante error de terminología devolver false (no tumbar el pedido).
     */
    public function matches(string $code, string $system, string $ecl): bool;
}
