<?php

namespace common\components\Scheduling\Service;

/**
 * Resultado de resolver triage → servicio(s) reservables por el paciente.
 */
final class ReservaTriageServicioResolucion
{
    /** @param list<int> $id_servicios_reservables */
    public function __construct(
        public readonly string $rol_ideal,
        public readonly string $rol_ideal_label,
        public readonly string $triage_codigo_resolutor,
        public readonly bool $autogestion_disponible,
        public readonly array $id_servicios_reservables,
        public readonly ?string $mensaje_orientacion,
        public readonly ?string $mensaje_lista,
    ) {
    }
}
