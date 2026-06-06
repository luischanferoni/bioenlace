<?php

namespace common\components\Scheduling\Service;

/**
 * Política global de acceso del paciente al reservar turno (post-triage).
 */
final class ReservaTriageAccesoConfig
{
    /** Especialistas sin autogestión: teleconsulta solo con derivación del clínico. */
    public static function especialistaSoloTeleconsultaConDerivacion(): bool
    {
        return true;
    }
}
