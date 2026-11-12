<?php

namespace common\components\Domain\Clinical\Encounter\Application\Coding;

use common\components\Domain\Clinical\CarePlan\Application\UseCase\CodeTreatmentRequestSnomed;
use common\models\Clinical\Encounter;

/**
 * Medicación y pedidos/prácticas → SNOMED.
 *
 * Delega en el codificador existente, que recorre las filas persistidas.
 */
final class TreatmentRequestSnomedCodingTarget implements EncounterCodingTargetInterface
{
    public function key(): string
    {
        return 'treatment_requests';
    }

    public function code(Encounter $encounter, array $context = []): int
    {
        return (new CodeTreatmentRequestSnomed())->codeAndPersistForEncounter($encounter);
    }
}
