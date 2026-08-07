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

    public static function label(?string $tipo): string
    {
        $map = [
            self::INGRESO => 'Ingreso a guardia',
            self::TRIAGE => 'Triage',
            self::RE_TRIAGE => 'Re-triage',
            self::ASIGNACION => 'Asignación de médico',
            self::INICIO_ATENCION => 'Inicio de atención',
            self::FIN_ATENCION => 'Fin de atención',
            self::DERIVACION => 'Derivación',
            self::EGRESO => 'Egreso',
        ];

        return $map[$tipo ?? ''] ?? (($tipo !== null && $tipo !== '') ? $tipo : 'Evento de circuito');
    }
}
