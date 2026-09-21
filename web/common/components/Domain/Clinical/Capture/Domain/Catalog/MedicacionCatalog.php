<?php

namespace common\components\Domain\Clinical\Capture\Domain\Catalog;

/**
 * Catálogos de captura de medicación (frecuencia / duración).
 * Alineados con tipología legacy ConsultaMedicamentos.
 */
final class MedicacionCatalog
{
    public const FRECUENCIA_TIPO_MINUTO = 'MINUTO';
    public const FRECUENCIA_TIPO_HORA = 'HORA';
    public const FRECUENCIA_TIPO_DIA = 'DIA';

    /** @var array<string, string> */
    public const FRECUENCIAS = [
        self::FRECUENCIA_TIPO_MINUTO => 'Minuto',
        self::FRECUENCIA_TIPO_HORA => 'Hora',
        self::FRECUENCIA_TIPO_DIA => 'Día',
    ];

    public const DURANTE_TIPO_DIA = 'DIA';
    public const DURANTE_TIPO_SEMANA = 'SEMANA';
    public const DURANTE_TIPO_MES = 'MES';
    public const DURANTE_TIPO_CRONICO = 'CRONICO';

    /** @var array<string, string> */
    public const DURANTES = [
        self::DURANTE_TIPO_DIA => 'Día',
        self::DURANTE_TIPO_SEMANA => 'Semana',
        self::DURANTE_TIPO_MES => 'Mes',
        self::DURANTE_TIPO_CRONICO => 'Crónico',
    ];
}
