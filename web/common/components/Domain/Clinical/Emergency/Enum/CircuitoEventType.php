<?php

namespace common\components\Domain\Clinical\Emergency\Enum;

final class CircuitoEventType
{
    public const INGRESO = 'ingreso';
    public const TRIAGE = 'triage';
    public const RE_TRIAGE = 're_triage';
    public const ASIGNACION = 'asignacion';
    public const INICIO_ATENCION = 'inicio_atencion';
    public const FIN_ATENCION = 'fin_atencion';
    public const DERIVACION = 'derivacion';
    public const EGRESO = 'egreso';
}
