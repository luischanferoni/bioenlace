<?php

namespace common\components\Domain\Clinical\Access;

/**
 * Pedido incompleto o resoluble: línea asistencial (quién) × acto clínico (qué).
 */
final class PedidoAtencion
{
    public const MODO_CONSULTA = 'consulta';
    public const MODO_INTERCONSULTA = 'interconsulta';
    public const MODO_PRACTICA = 'practica';
    public const MODO_ESTUDIO = 'estudio';

    /** @var int|null */
    public $lineaId;

    /** @var string|null SCTID / LOINC / etc. */
    public $actoCode;

    /** @var string|null */
    public $actoSystem;

    /** @var string|null */
    public $actoDisplay;

    /** @var string */
    public $modo;

    /** @var string|null */
    public $razon;

    /** @var int|null */
    public $efectorId;

    public function __construct(
        ?int $lineaId = null,
        ?string $actoCode = null,
        ?string $actoSystem = null,
        string $modo = self::MODO_INTERCONSULTA,
        ?string $razon = null,
        ?int $efectorId = null,
        ?string $actoDisplay = null
    ) {
        $this->lineaId = $lineaId !== null && $lineaId > 0 ? $lineaId : null;
        $this->actoCode = $actoCode !== null && trim($actoCode) !== '' ? trim($actoCode) : null;
        $this->actoSystem = $actoSystem !== null && trim($actoSystem) !== '' ? trim($actoSystem) : null;
        $this->actoDisplay = $actoDisplay !== null && trim($actoDisplay) !== '' ? trim($actoDisplay) : null;
        $this->modo = strtolower(trim($modo)) !== '' ? strtolower(trim($modo)) : self::MODO_INTERCONSULTA;
        $this->razon = $razon !== null && trim($razon) !== '' ? trim($razon) : null;
        $this->efectorId = $efectorId !== null && $efectorId > 0 ? $efectorId : null;
    }

    public function hasLinea(): bool
    {
        return $this->lineaId !== null && $this->lineaId > 0;
    }

    public function hasActo(): bool
    {
        return $this->actoCode !== null && $this->actoCode !== ''
            && $this->actoSystem !== null && $this->actoSystem !== '';
    }

    /**
     * Texto de acto sin código tipado (no debe taparse con default de modo).
     */
    public function hasActoDisplayWithoutCode(): bool
    {
        return !$this->hasActo()
            && $this->actoDisplay !== null
            && trim($this->actoDisplay) !== '';
    }

    public function withLinea(int $lineaId): self
    {
        $clone = clone $this;
        $clone->lineaId = $lineaId > 0 ? $lineaId : null;

        return $clone;
    }

    public function withActo(string $code, string $system, ?string $display = null): self
    {
        $clone = clone $this;
        $clone->actoCode = trim($code) !== '' ? trim($code) : null;
        $clone->actoSystem = trim($system) !== '' ? trim($system) : null;
        $clone->actoDisplay = $display !== null && trim($display) !== '' ? trim($display) : null;

        return $clone;
    }

    /**
     * @return list<string>
     */
    public static function modos(): array
    {
        return [
            self::MODO_CONSULTA,
            self::MODO_INTERCONSULTA,
            self::MODO_PRACTICA,
            self::MODO_ESTUDIO,
        ];
    }
}
