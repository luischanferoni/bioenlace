<?php

namespace common\components\Domain\Clinical\Access;

/**
 * Completa y valida PedidoAtencion (línea × acto) vía catálogo y metadata.
 *
 * @phpstan-type CandidateLinea array{id: int, label: string, preferente?: bool}
 * @phpstan-type CandidateActo array{code: string, system: string, display: string, preferente?: bool}
 * @phpstan-type ResolveResult array{
 *   complete: bool,
 *   pedido: PedidoAtencion,
 *   missing: list<string>,
 *   candidates: array{lineas: list<CandidateLinea>, actos: list<CandidateActo>}
 * }
 */
final class PedidoAtencionService
{
    private LineaActoCatalogInterface $catalog;

    public function __construct(?LineaActoCatalogInterface $catalog = null)
    {
        $this->catalog = $catalog ?? new DbLineaActoCatalog();
    }

    /**
     * @return list<string> valores: linea | acto
     */
    public function missingSlots(PedidoAtencion $pedido): array
    {
        return $this->resolve($pedido)['missing'];
    }

    public function isComplete(PedidoAtencion $pedido): bool
    {
        return $this->resolve($pedido)['complete'];
    }

    /**
     * @return ResolveResult
     */
    public function resolve(PedidoAtencion $pedido): array
    {
        $pedido = $this->normalizeActoSystem($pedido);
        $candidates = ['lineas' => [], 'actos' => []];

        if ($pedido->hasLinea() && !$pedido->hasActo()) {
            $default = PedidoAtencionMetadata::defaultActoForModo($pedido->modo);
            if ($default !== null) {
                $fromPuente = $this->catalog->actosForLinea((int) $pedido->lineaId, $pedido->efectorId);
                $match = $this->pickActoMatching($fromPuente, $default['code'], $default['code_system']);
                if ($match !== null) {
                    $pedido = $pedido->withActo($match['code'], $match['system'], $match['display']);
                } elseif ($fromPuente === []) {
                    // Sin puente: aún así aplicar default de metadata (interconsulta genérica).
                    $pedido = $pedido->withActo(
                        $default['code'],
                        $default['code_system'],
                        $default['display']
                    );
                } else {
                    $preferente = $this->pickPreferenteActo($fromPuente);
                    if ($preferente !== null) {
                        $pedido = $pedido->withActo(
                            $preferente['code'],
                            $preferente['system'],
                            $preferente['display']
                        );
                    } else {
                        $candidates['actos'] = $fromPuente;
                    }
                }
            } else {
                $fromPuente = $this->catalog->actosForLinea((int) $pedido->lineaId, $pedido->efectorId);
                $preferente = $this->pickPreferenteActo($fromPuente);
                if ($preferente !== null) {
                    $pedido = $pedido->withActo(
                        $preferente['code'],
                        $preferente['system'],
                        $preferente['display']
                    );
                } elseif (count($fromPuente) === 1) {
                    $only = $fromPuente[0];
                    $pedido = $pedido->withActo($only['code'], $only['system'], $only['display']);
                } else {
                    $candidates['actos'] = $fromPuente;
                }
            }
        }

        if ($pedido->hasActo() && !$pedido->hasLinea()) {
            $lineas = $this->catalog->lineasForActo(
                (string) $pedido->actoCode,
                (string) $pedido->actoSystem,
                $pedido->efectorId
            );
            $preferente = $this->pickPreferenteLinea($lineas);
            if ($preferente !== null) {
                $pedido = $pedido->withLinea((int) $preferente['id']);
            } elseif (count($lineas) === 1) {
                $pedido = $pedido->withLinea((int) $lineas[0]['id']);
            } else {
                $candidates['lineas'] = $lineas;
            }
        }

        if ($pedido->hasActo() && $pedido->actoDisplay === null) {
            $found = $this->catalog->findActo((string) $pedido->actoCode, (string) $pedido->actoSystem);
            if ($found !== null) {
                $pedido = $pedido->withActo($found['code'], $found['system'], $found['display']);
            }
        }

        $missing = [];
        if (!$pedido->hasLinea()) {
            $missing[] = 'linea';
        }
        if (!$pedido->hasActo()) {
            $missing[] = 'acto';
        }

        return [
            'complete' => $missing === [],
            'pedido' => $pedido,
            'missing' => $missing,
            'candidates' => $candidates,
        ];
    }

    private function normalizeActoSystem(PedidoAtencion $pedido): PedidoAtencion
    {
        if (!$pedido->hasActo()) {
            if ($pedido->actoCode !== null && $pedido->actoSystem === null) {
                return $pedido->withActo(
                    (string) $pedido->actoCode,
                    CodingSystems::SNOMED,
                    $pedido->actoDisplay
                );
            }

            return $pedido;
        }
        if (!CodingSystems::isAllowed((string) $pedido->actoSystem, PedidoAtencionMetadata::allowedSystems())) {
            // Sistema no admitido: se descarta el acto (no local).
            $clone = clone $pedido;
            $clone->actoCode = null;
            $clone->actoSystem = null;
            $clone->actoDisplay = null;

            return $clone;
        }

        return $pedido;
    }

    /**
     * @param list<array{code: string, system: string, display: string, preferente?: bool}> $actos
     * @return array{code: string, system: string, display: string}|null
     */
    private function pickActoMatching(array $actos, string $code, string $system): ?array
    {
        foreach ($actos as $a) {
            if ($a['code'] === $code && $a['system'] === $system) {
                return $a;
            }
        }

        return null;
    }

    /**
     * @param list<array{code: string, system: string, display: string, preferente?: bool}> $actos
     * @return array{code: string, system: string, display: string}|null
     */
    private function pickPreferenteActo(array $actos): ?array
    {
        $prefs = array_values(array_filter($actos, static fn ($a) => !empty($a['preferente'])));
        if (count($prefs) === 1) {
            return $prefs[0];
        }
        if (count($actos) === 1) {
            return $actos[0];
        }

        return null;
    }

    /**
     * @param list<array{id: int, label: string, preferente?: bool}> $lineas
     * @return array{id: int, label: string}|null
     */
    private function pickPreferenteLinea(array $lineas): ?array
    {
        $prefs = array_values(array_filter($lineas, static fn ($l) => !empty($l['preferente'])));
        if (count($prefs) === 1) {
            return $prefs[0];
        }
        if (count($lineas) === 1) {
            return $lineas[0];
        }

        return null;
    }
}
