<?php

namespace common\components\Domain\Clinical\Capture\Domain\Port;

/**
 * Puerto: respaldo terminológico de un término extraído.
 * Implementación en Infrastructure (local + Snowstorm).
 */
interface TerminologyLookupPort
{
    /**
     * @param array<string, mixed> $config
     */
    public function matchesClinicalTerm(string $term, array $config = []): bool;

    public function wasTerminologyServiceUnavailable(): bool;
}
