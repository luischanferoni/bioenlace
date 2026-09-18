<?php

namespace common\components\Domain\Clinical\Inpatient\Application\Dto;

/**
 * Vista de régimen (NutritionOrder FHIR → shape legacy para UI).
 */
final class InpatientRegimenRow
{
    public int $id = 0;
    public int $id_consulta = 0;
    public ?string $concept_id = null;
    public string $indicaciones = '';

    /** @var array<string, mixed> */
    private array $queryExtra = [];

    public function setQueryExtraValue(string $name, mixed $value): void
    {
        $this->queryExtra[$name] = $value;
    }

    public function getQueryExtraData(string $name, mixed $default = null): mixed
    {
        return $this->queryExtra[$name] ?? $default;
    }
}
