<?php

namespace common\components\Emergency;

/**
 * Estados del circuito operativo de guardia (canónico).
 */
final class CircuitoEstado
{
    public const INGRESADO = 'ingresado';
    public const ESPERA_TRIAGE = 'espera_triage';
    public const ESPERA_MEDICO = 'espera_medico';
    public const EN_ATENCION = 'en_atencion';
    public const ATENDIDO = 'atendido';
    public const DERIVADO = 'derivado';
    public const FINALIZADO = 'finalizado';

    /** @var string[] */
    public const ACTIVOS = [
        self::INGRESADO,
        self::ESPERA_TRIAGE,
        self::ESPERA_MEDICO,
        self::EN_ATENCION,
        self::ATENDIDO,
        self::DERIVADO,
    ];

    public static function isActivo(?string $estado): bool
    {
        return $estado !== null && $estado !== '' && in_array($estado, self::ACTIVOS, true);
    }

    public static function label(?string $estado): string
    {
        $map = [
            self::INGRESADO => 'Ingresado',
            self::ESPERA_TRIAGE => 'Espera triage',
            self::ESPERA_MEDICO => 'En cola',
            self::EN_ATENCION => 'En atención',
            self::ATENDIDO => 'Atendido',
            self::DERIVADO => 'Derivado',
            self::FINALIZADO => 'Finalizado',
        ];

        return $map[$estado ?? ''] ?? (string) $estado;
    }
}
