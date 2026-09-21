<?php

namespace common\components\Domain\Clinical\Capture\Domain\Port;

/**
 * Soporte de I/O para completitud/resoluciones de derivación (línea × acto).
 * Domain no conoce AR Servicio ni PedidoAtencionService.
 */
interface DerivacionRowSupportPort
{
    public function resolveLineaIdByName(string $servicioNombre): ?int;

    public function resolveLineaNameById(int $idServicio): ?string;

    /**
     * @return array{id: int, nombre: string}|null
     */
    public function resolveLineaBySpecialtyNl(string $nl): ?array;

    /**
     * @return array{
     *   resolved: array{code: string, system: string, display: string}|null,
     *   candidates: list<array{code: string, system: string, display: string}>
     * }
     */
    public function codeActo(string $display, string $modo): array;

    /**
     * @return array{
     *   complete: bool,
     *   missing: list<string>,
     *   lineas: list<array{id: int, label: string}>,
     *   actos: list<array{code: string, system: string, display: string}>
     * }
     */
    public function resolvePedidoCompleteness(
        ?int $lineaId,
        ?string $actoCode,
        ?string $actoSystem,
        string $modo,
        ?string $indicaciones,
        ?int $efectorId,
        ?string $actoDisplay
    ): array;

    /**
     * @return list<array{value: int, label: string}>
     */
    public function servicioOptions(): array;
}
