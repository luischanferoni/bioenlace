<?php

namespace common\components\Domain\Clinical\Access;

/**
 * Catálogo puente línea ↔ acto (inyectable para tests).
 */
interface LineaActoCatalogInterface
{
    /**
     * @return list<array{id: int, label: string, preferente: bool}>
     */
    public function lineasForActo(string $code, string $system, ?int $efectorId): array;

    /**
     * @return list<array{code: string, system: string, display: string, preferente: bool}>
     */
    public function actosForLinea(int $lineaId, ?int $efectorId): array;

    /**
     * @return array{code: string, system: string, display: string}|null
     */
    public function findActo(string $code, string $system): ?array;

    /**
     * @return list<array{code: string, system: string, display: string}>
     */
    public function listActos(): array;
}
