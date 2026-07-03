<?php

namespace common\components\Domain\Clinical\Service\EncounterJourney;

/**
 * Resuelve definición efectiva de ventana (base + override por efector/servicio).
 */
final class EncounterPhaseWindowResolver
{
    private EncounterPhaseWindowsCatalogService $catalog;
    private EncounterPhaseWindowOverrideCatalogService $overrides;

    public function __construct(
        ?EncounterPhaseWindowsCatalogService $catalog = null,
        ?EncounterPhaseWindowOverrideCatalogService $overrides = null
    ) {
        $this->catalog = $catalog ?? new EncounterPhaseWindowsCatalogService();
        $this->overrides = $overrides ?? new EncounterPhaseWindowOverrideCatalogService();
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>|null
     */
    public function phaseDefinition(string $phaseId, array $context): ?array
    {
        $base = $this->catalog->phase($phaseId);
        if ($base === null) {
            return null;
        }
        $patch = $this->overrides->phaseOverride($phaseId, $context);
        if ($patch === null) {
            return $base;
        }

        return array_replace_recursive($base, $patch);
    }

    /**
     * @param array<string, mixed> $context
     * @return list<array{offset: string, tipo: string, title: string, body: string}>
     */
    public function notifications(string $phaseId, array $context): array
    {
        $def = $this->phaseDefinition($phaseId, $context);
        if ($def === null) {
            return [];
        }
        $raw = $def['notifications'] ?? [];
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $offset = trim((string) ($row['offset'] ?? ''));
            $tipo = trim((string) ($row['tipo'] ?? ''));
            if ($offset === '' || $tipo === '') {
                continue;
            }
            $out[] = [
                'offset' => $offset,
                'tipo' => $tipo,
                'title' => trim((string) ($row['title'] ?? '')),
                'body' => trim((string) ($row['body'] ?? '')),
            ];
        }

        return $out;
    }
}
