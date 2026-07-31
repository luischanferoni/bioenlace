<?php

namespace common\components\Domain\Clinical\Access;

/**
 * Coder en memoria para tests (mapa display → resolved / candidates).
 */
final class InMemoryPedidoAtencionActoCoder implements PedidoAtencionActoCoderInterface
{
    /** @var array<string, array{resolved: ?array{code: string, system: string, display: string}, candidates: list<array{code: string, system: string, display: string}>}> */
    private array $byDisplay;

    /**
     * @param array<string, array{resolved?: ?array{code: string, system: string, display: string}, candidates?: list<array{code: string, system: string, display: string}>}> $byDisplay
     */
    public function __construct(array $byDisplay = [])
    {
        $this->byDisplay = [];
        foreach ($byDisplay as $key => $row) {
            $norm = mb_strtolower(trim((string) $key), 'UTF-8');
            $this->byDisplay[$norm] = [
                'resolved' => $row['resolved'] ?? null,
                'candidates' => $row['candidates'] ?? [],
            ];
        }
    }

    public function code(string $display, ?string $modo = null): array
    {
        $norm = mb_strtolower(trim($display), 'UTF-8');
        if ($norm === '' || !isset($this->byDisplay[$norm])) {
            return ['resolved' => null, 'candidates' => []];
        }

        return $this->byDisplay[$norm];
    }
}
