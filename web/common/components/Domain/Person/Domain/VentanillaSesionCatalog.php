<?php

namespace common\components\Domain\Person\Domain;

/**
 * Catálogo de dominio (ex metadata/bioenlace/person/ventanilla-sesion.yaml).
 */
final class VentanillaSesionCatalog
{
    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return [
        'ttl_minutes' => 15,
        'unhide_paciente_intent_ids' => [
            0 => 'turnos.crear-como-paciente',
            1 => 'turnos.ver-mis-turnos-como-paciente',
        ],
    ];
    }
}
