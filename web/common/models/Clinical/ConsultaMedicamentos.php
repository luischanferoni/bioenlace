<?php

namespace common\models\Clinical;

/**
 * Constantes y helpers de extracción de medicación (legacy name `ConsultaMedicamentos`).
 *
 * La tabla `consultas_medicamentos` ya no existe; la persistencia va a {@see MedicationRequest}.
 * El string de categoría de captura sigue siendo `ConsultaMedicamentos`.
 */
final class ConsultaMedicamentos
{
    public const ESTADO_SUSPENDIDO = 'SUSPENDIDO';
    public const ESTADO_INGRESADO_POR_ERROR = 'INGRESADO_POR_ERROR';
    public const ESTADO_ACTIVO = 'ACTIVO';

    public const ESTADOS = [
        self::ESTADO_ACTIVO => 'Activo',
        self::ESTADO_SUSPENDIDO => 'Suspendido',
        self::ESTADO_INGRESADO_POR_ERROR => 'Ingresado por Error',
    ];

    public const FRECUENCIA_TIPO_MINUTO = 'MINUTO';
    public const FRECUENCIA_TIPO_HORA = 'HORA';
    public const FRECUENCIA_TIPO_DIA = 'DIA';
    public const FRECUENCIAS = [
        self::FRECUENCIA_TIPO_MINUTO => 'Minuto',
        self::FRECUENCIA_TIPO_HORA => 'Hora',
        self::FRECUENCIA_TIPO_DIA => 'Día',
    ];

    public const DURANTE_TIPO_DIA = 'DIA';
    public const DURANTE_TIPO_SEMANA = 'SEMANA';
    public const DURANTE_TIPO_MES = 'MES';
    public const DURANTE_TIPO_CRONICO = 'CRONICO';
    public const DURANTES = [
        self::DURANTE_TIPO_DIA => 'Día',
        self::DURANTE_TIPO_SEMANA => 'Semana',
        self::DURANTE_TIPO_MES => 'Mes',
        self::DURANTE_TIPO_CRONICO => 'Crónico',
    ];

    /**
     * @return list<string>
     */
    public static function requeridosPrompt(): array
    {
        return Input\MedicacionInput::promptFieldNames();
    }

    /**
     * @param array<string, mixed>|string $row
     * @return array{missing_fields: list<string>, label: string, input: Input\MedicacionInput}
     */
    public static function completenessForExtractedRow($row): array
    {
        $input = Input\MedicacionInput::fromExtractedRow($row);
        $label = trim((string) ($input->nombre ?? ''));

        return [
            'missing_fields' => $input->missingFieldsForCompleteness(),
            'label' => $label !== '' ? $label : 'ítem',
            'input' => $input,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function applyResolutionToRow(array $row, string $field, mixed $value): array
    {
        return Input\MedicacionInput::applyResolutionToRow($row, $field, $value);
    }

    /**
     * @return list<string>
     */
    public static function camposPromptExtraccion(): array
    {
        return self::requeridosPrompt();
    }

    /**
     * @return array{frecuencia: list<string>, duracion: list<string>}
     */
    public static function tiposPromptExtraccion(): array
    {
        return [
            'frecuencia' => array_keys(self::FRECUENCIAS),
            'duracion' => array_keys(self::DURANTES),
        ];
    }
}
