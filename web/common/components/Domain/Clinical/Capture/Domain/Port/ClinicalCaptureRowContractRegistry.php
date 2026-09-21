<?php

namespace common\components\Domain\Clinical\Capture\Domain\Port;

use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCaptureRowCompleteness;

/**
 * Contratos de fila extraída (completitud, issues, aplicación de resoluciones).
 *
 * Domain no conoce `*Input` ni AR: el adapter resuelve tipologías de captura.
 */
interface ClinicalCaptureRowContractRegistry
{
    public function supports(string $modelo): bool;

    /**
     * @param array<string, mixed>|string $row
     */
    public function assess(string $modelo, $row, string $categoryTitle, int $index): ?ClinicalCaptureRowCompleteness;

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null fila transformada, o null si no hay contrato
     */
    public function applyResolution(string $modelo, array $row, string $field, mixed $value): ?array;
}
