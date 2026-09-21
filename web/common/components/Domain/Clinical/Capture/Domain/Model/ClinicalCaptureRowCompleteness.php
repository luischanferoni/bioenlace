<?php

namespace common\components\Domain\Clinical\Capture\Domain\Model;

/**
 * Resultado tipado de completitud de una fila extraída.
 */
final class ClinicalCaptureRowCompleteness
{
    /** @var list<string> */
    private array $missingFields;

    private string $label;

    /**
     * @var list<array{id: string, field: string, options: list<array{value: mixed, label: string}>, allow_custom: bool}>
     */
    private array $issues;

    /**
     * @param list<string> $missingFields
     * @param list<array{id: string, field: string, options: list<array{value: mixed, label: string}>, allow_custom: bool}> $issues
     */
    public function __construct(array $missingFields, string $label, array $issues = [])
    {
        $this->missingFields = array_values($missingFields);
        $this->label = $label !== '' ? $label : 'ítem';
        $this->issues = array_values($issues);
    }

    /**
     * @return list<string>
     */
    public function missingFields(): array
    {
        return $this->missingFields;
    }

    public function label(): string
    {
        return $this->label;
    }

    /**
     * @return list<array{id: string, field: string, options: list<array{value: mixed, label: string}>, allow_custom: bool}>
     */
    public function issues(): array
    {
        return $this->issues;
    }

    public function isComplete(): bool
    {
        return $this->missingFields === [];
    }
}
