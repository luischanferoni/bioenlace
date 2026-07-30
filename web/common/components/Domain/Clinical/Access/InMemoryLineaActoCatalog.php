<?php

namespace common\components\Domain\Clinical\Access;

/**
 * Catálogo en memoria para tests unitarios.
 */
final class InMemoryLineaActoCatalog implements LineaActoCatalogInterface
{
    /** @var list<array{code: string, system: string, display: string}> */
    private array $actos;

    /** @var list<array{linea_id: int, linea_label: string, code: string, system: string, preferente: bool, efector_id: ?int}> */
    private array $links;

    /**
     * @param list<array{code: string, system: string, display: string}> $actos
     * @param list<array{linea_id: int, linea_label: string, code: string, system: string, preferente?: bool, efector_id?: ?int}> $links
     */
    public function __construct(array $actos = [], array $links = [])
    {
        $this->actos = $actos;
        $this->links = [];
        foreach ($links as $link) {
            $this->links[] = [
                'linea_id' => (int) $link['linea_id'],
                'linea_label' => (string) $link['linea_label'],
                'code' => (string) $link['code'],
                'system' => (string) $link['system'],
                'preferente' => !empty($link['preferente']),
                'efector_id' => isset($link['efector_id']) && $link['efector_id'] !== null
                    ? (int) $link['efector_id']
                    : null,
            ];
        }
    }

    public function lineasForActo(string $code, string $system, ?int $efectorId): array
    {
        $byLinea = [];
        foreach ($this->links as $link) {
            if ($link['code'] !== $code || $link['system'] !== $system) {
                continue;
            }
            if ($efectorId !== null && $efectorId > 0) {
                if ($link['efector_id'] !== null && $link['efector_id'] !== $efectorId) {
                    continue;
                }
            } elseif ($link['efector_id'] !== null) {
                continue;
            }
            $id = $link['linea_id'];
            if (!isset($byLinea[$id]) || $link['preferente']) {
                $byLinea[$id] = [
                    'id' => $id,
                    'label' => $link['linea_label'],
                    'preferente' => $link['preferente'],
                ];
            }
        }

        return array_values($byLinea);
    }

    public function actosForLinea(int $lineaId, ?int $efectorId): array
    {
        $byCode = [];
        foreach ($this->links as $link) {
            if ($link['linea_id'] !== $lineaId) {
                continue;
            }
            if ($efectorId !== null && $efectorId > 0) {
                if ($link['efector_id'] !== null && $link['efector_id'] !== $efectorId) {
                    continue;
                }
            } elseif ($link['efector_id'] !== null) {
                continue;
            }
            $acto = $this->findActo($link['code'], $link['system']);
            if ($acto === null) {
                continue;
            }
            $key = $link['system'] . '|' . $link['code'];
            if (!isset($byCode[$key]) || $link['preferente']) {
                $byCode[$key] = [
                    'code' => $acto['code'],
                    'system' => $acto['system'],
                    'display' => $acto['display'],
                    'preferente' => $link['preferente'],
                ];
            }
        }

        return array_values($byCode);
    }

    public function findActo(string $code, string $system): ?array
    {
        foreach ($this->actos as $acto) {
            if ($acto['code'] === $code && $acto['system'] === $system) {
                return $acto;
            }
        }

        return null;
    }

    public function listActos(): array
    {
        return $this->actos;
    }
}
