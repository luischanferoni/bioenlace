<?php

namespace common\components\Domain\Scheduling\Agenda\Domain\Catalog;

/**
 * Política global de acceso del paciente al reservar turno (post-triage).
 */
final class ReservaTriageAccessCatalog
{
    /** Especialistas sin autogestión: teleconsulta solo con derivación del clínico. */
    public static function especialistaSoloTeleconsultaConDerivacion(): bool
    {
        return true;
    }
}
