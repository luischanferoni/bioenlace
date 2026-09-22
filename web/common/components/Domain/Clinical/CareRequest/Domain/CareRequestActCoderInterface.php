<?php

namespace common\components\Domain\Clinical\CareRequest\Domain;

/**
 * Codifica display de acto → SCTID (inyectable para tests).
 */
interface CareRequestActCoderInterface
{
    /**
     * @return array{
     *   resolved: array{code: string, system: string, display: string}|null,
     *   candidates: list<array{code: string, system: string, display: string}>
     * }
     */
    public function code(string $display, ?string $modo = null): array;
}
