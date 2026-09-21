<?php

namespace common\models\Clinical;

use common\components\Domain\Clinical\Capture\Domain\Catalog\MedicacionCaptureCatalog;
use common\components\Domain\Clinical\Capture\Domain\Policy\MedicacionRowContract;
use common\models\Clinical\Input\MedicacionInput;

/**
 * Constantes y helpers de extracción de medicación (legacy name `ConsultaMedicamentos`).
 *
 * La tabla `consultas_medicamentos` ya no existe; la persistencia va a {@see MedicationRequest}.
 * Contrato Domain: {@see MedicacionRowContract}.
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

    public const FRECUENCIA_TIPO_MINUTO = MedicacionCaptureCatalog::FRECUENCIA_TIPO_MINUTO;
    public const FRECUENCIA_TIPO_HORA = MedicacionCaptureCatalog::FRECUENCIA_TIPO_HORA;
    public const FRECUENCIA_TIPO_DIA = MedicacionCaptureCatalog::FRECUENCIA_TIPO_DIA;
    public const FRECUENCIAS = MedicacionCaptureCatalog::FRECUENCIAS;

    public const DURANTE_TIPO_DIA = MedicacionCaptureCatalog::DURANTE_TIPO_DIA;
    public const DURANTE_TIPO_SEMANA = MedicacionCaptureCatalog::DURANTE_TIPO_SEMANA;
    public const DURANTE_TIPO_MES = MedicacionCaptureCatalog::DURANTE_TIPO_MES;
    public const DURANTE_TIPO_CRONICO = MedicacionCaptureCatalog::DURANTE_TIPO_CRONICO;
    public const DURANTES = MedicacionCaptureCatalog::DURANTES;

    /**
     * @return list<string>
     */
    public static function requeridosPrompt(): array
    {
        return MedicacionInput::promptFieldNames();
    }

    /**
     * @param array<string, mixed>|string $row
     * @return array{missing_fields: list<string>, label: string, input: MedicacionInput}
     */
    public static function completenessForExtractedRow($row): array
    {
        $assessment = MedicacionRowContract::assess($row);

        return [
            'missing_fields' => $assessment->missingFields(),
            'label' => $assessment->label(),
            'input' => MedicacionInput::fromExtractedRow($row),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function applyResolutionToRow(array $row, string $field, mixed $value): array
    {
        return MedicacionRowContract::applyResolution($row, $field, $value);
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
