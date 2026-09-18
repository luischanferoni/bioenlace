<?php

namespace common\components\Domain\Clinical\Encounter\Domain\Model;

/**
 * Value object: motivo de consulta (Condition con rol chief complaint).
 *
 * Normaliza texto libre o fila tipada sin I/O.
 */
final class ChiefComplaintReason
{
    public const UNCODED_SYSTEM = 'https://bioenlace.local/CodeSystem/encounter-reason-text';
    public const DEFAULT_CODED_SYSTEM = 'http://snomed.info/sct';

    /** @var string|null */
    private $code;

    /** @var string */
    private $codeSystem;

    /** @var string */
    private $display;

    /** @var string|null */
    private $note;

    private function __construct(?string $code, string $codeSystem, string $display, ?string $note)
    {
        $this->code = $code;
        $this->codeSystem = $codeSystem;
        $this->display = $display;
        $this->note = $note;
    }

    /**
     * @param string|array<string, mixed> $row
     */
    public static function fromInput($row): ?self
    {
        if (is_string($row)) {
            $text = trim($row);
            if ($text === '') {
                return null;
            }

            return new self(null, self::UNCODED_SYSTEM, $text, null);
        }
        if (!is_array($row)) {
            return null;
        }

        $display = trim((string) (
            $row['texto']
            ?? $row['termino']
            ?? $row['descripcion']
            ?? $row['label']
            ?? $row['display']
            ?? $row['Motivo']
            ?? ''
        ));
        $code = trim((string) ($row['codigo'] ?? $row['code'] ?? $row['Codigo'] ?? ''));
        if ($display === '' && $code === '') {
            return null;
        }
        if ($display === '') {
            $display = $code;
        }
        if ($code === '') {
            return new self(null, self::UNCODED_SYSTEM, $display, null);
        }
        $system = trim((string) ($row['code_system'] ?? $row['sistema'] ?? ''));
        if ($system === '') {
            $system = self::DEFAULT_CODED_SYSTEM;
        }

        return new self($code, $system, $display, null);
    }

    public function code(): ?string
    {
        return $this->code;
    }

    public function codeSystem(): string
    {
        return $this->codeSystem;
    }

    public function display(): string
    {
        return $this->display;
    }

    public function note(): ?string
    {
        return $this->note;
    }

    /**
     * @return array{code: ?string, code_system: string, display: string, note: ?string}
     */
    public function toPersistenceArray(): array
    {
        return [
            'code' => $this->code,
            'code_system' => $this->codeSystem,
            'display' => $this->display,
            'note' => $this->note,
        ];
    }
}
