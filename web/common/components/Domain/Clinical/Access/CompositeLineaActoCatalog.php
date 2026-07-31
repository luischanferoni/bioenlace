<?php

namespace common\components\Domain\Clinical\Access;

/**
 * Une puente explícito (`linea_acto`) + capacidad ECL.
 * Preferente del puente gana; dedupe por id_servicio / code.
 */
final class CompositeLineaActoCatalog implements LineaActoCatalogInterface
{
    private LineaActoCatalogInterface $explicit;
    private EclCapacityCatalog $ecl;

    public function __construct(
        ?LineaActoCatalogInterface $explicit = null,
        ?EclCapacityCatalog $ecl = null
    ) {
        $this->explicit = $explicit ?? new DbLineaActoCatalog();
        $this->ecl = $ecl ?? new EclCapacityCatalog();
    }

    public static function defaultCatalog(): self
    {
        return new self(new DbLineaActoCatalog(), new EclCapacityCatalog(new SnowstormActoEclMembership()));
    }

    public function lineasForActo(string $code, string $system, ?int $efectorId): array
    {
        $fromExplicit = $this->explicit->lineasForActo($code, $system, $efectorId);
        $fromEcl = $this->ecl->lineasForActo($code, $system, $efectorId);

        return $this->mergeLineas($fromExplicit, $fromEcl);
    }

    public function actosForLinea(int $lineaId, ?int $efectorId): array
    {
        $fromExplicit = $this->explicit->actosForLinea($lineaId, $efectorId);
        $fromEcl = $this->ecl->actosForLinea($lineaId, $efectorId);

        return $this->mergeActos($fromExplicit, $fromEcl);
    }

    public function findActo(string $code, string $system): ?array
    {
        return $this->explicit->findActo($code, $system);
    }

    public function listActos(): array
    {
        return $this->explicit->listActos();
    }

    /**
     * @param list<array{id: int, label: string, preferente: bool}> $primary
     * @param list<array{id: int, label: string, preferente: bool}> $secondary
     * @return list<array{id: int, label: string, preferente: bool}>
     */
    private function mergeLineas(array $primary, array $secondary): array
    {
        $byId = [];
        foreach ($primary as $row) {
            $id = (int) $row['id'];
            $byId[$id] = [
                'id' => $id,
                'label' => (string) $row['label'],
                'preferente' => !empty($row['preferente']),
            ];
        }
        foreach ($secondary as $row) {
            $id = (int) $row['id'];
            if (!isset($byId[$id])) {
                $byId[$id] = [
                    'id' => $id,
                    'label' => (string) $row['label'],
                    'preferente' => !empty($row['preferente']),
                ];
            } elseif (!empty($row['preferente'])) {
                $byId[$id]['preferente'] = true;
            }
        }

        return array_values($byId);
    }

    /**
     * @param list<array{code: string, system: string, display: string, preferente: bool}> $primary
     * @param list<array{code: string, system: string, display: string, preferente: bool}> $secondary
     * @return list<array{code: string, system: string, display: string, preferente: bool}>
     */
    private function mergeActos(array $primary, array $secondary): array
    {
        $byKey = [];
        foreach (array_merge($primary, $secondary) as $row) {
            $key = $row['system'] . '|' . $row['code'];
            $preferente = !empty($row['preferente']);
            if (!isset($byKey[$key])) {
                $byKey[$key] = [
                    'code' => (string) $row['code'],
                    'system' => (string) $row['system'],
                    'display' => (string) $row['display'],
                    'preferente' => $preferente,
                ];
            } elseif ($preferente) {
                $byKey[$key]['preferente'] = true;
            }
        }

        return array_values($byKey);
    }
}
