<?php

namespace common\components\Platform\Core\Product;

/**
 * Dueño (bounded context) de cada agent_id autónomo.
 *
 * Fuente para migrar `platform/agents/{id}.yaml` → Policy PHP en el BC.
 * `platform` solo si el agente fuera transversal de motor (hoy ninguno).
 *
 * @see web/docs/decisions/ddd-bounded-contexts-capas-y-metadata.md
 */
final class AgentBoundedContextMap
{
    /**
     * agent_id → id de BC en minúsculas (carpeta Domain/) o `platform`.
     *
     * @var array<string, string>
     */
    private const OWNER = [
        'turno-antinoshow' => 'scheduling',
        'turno-advance-offer' => 'scheduling',
        'turno-resolucion-loop-close' => 'scheduling',
        'turno-resolucion-shortlist' => 'scheduling',
        'turno-resolucion-auto-reserva' => 'scheduling',
        'turno-resolucion-multicanal' => 'scheduling',
        'consulta-async-bandeja-prioridad' => 'scheduling',
        'reserva-triage-post-cupo-routing' => 'scheduling',
        'lab-encounter-link' => 'clinical',
        'post-lab-classification' => 'clinical',
        'prescription-rdi-pre-submit' => 'clinical',
        'care-followup-branching' => 'clinical',
        'post-discharge-followup' => 'clinical',
        'internacion-cama-sugerencia' => 'clinical',
        'integration-retry' => 'clinical',
    ];

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return self::OWNER;
    }

    public static function ownerFor(string $agentId): ?string
    {
        $id = trim($agentId);

        return $id === '' ? null : (self::OWNER[$id] ?? null);
    }

    public static function isKnown(string $agentId): bool
    {
        return self::ownerFor($agentId) !== null;
    }
}
