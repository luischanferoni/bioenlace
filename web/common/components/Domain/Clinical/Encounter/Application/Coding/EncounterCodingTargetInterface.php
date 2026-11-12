<?php

namespace common\components\Domain\Clinical\Encounter\Application\Coding;

use common\models\Clinical\Encounter;

/**
 * Un tipo de dato clínico codificable de un encounter.
 *
 * Agregar un tipo nuevo = agregar un target al registro, sin tocar el job ni
 * el orquestador.
 */
interface EncounterCodingTargetInterface
{
    public function key(): string;

    /**
     * @param array<string, mixed> $context
     * @return int cantidad de códigos persistidos
     */
    public function code(Encounter $encounter, array $context = []): int;
}
