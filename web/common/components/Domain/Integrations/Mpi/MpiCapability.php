<?php

namespace common\components\Domain\Integrations\Mpi;

use Yii;

/**
 * Capacidades habilitadas del gateway MPI/SEIPA (declarativas vía params).
 */
final class MpiCapability
{
    public const RENAPER = 'renaper';

    public const COBERTURAS = 'coberturas';

    public const TRAER_PACIENTE = 'traer_paciente';

    public const EMPADRONAR = 'empadronar';

    public const ASOCIAR = 'asociar';

    public const CANDIDATOS = 'candidatos';

    /**
     * @return list<string>
     */
    public static function defaultEnabled(): array
    {
        return [self::RENAPER, self::COBERTURAS];
    }

    public static function isEnabled(string $capability): bool
    {
        $configured = Yii::$app->params['mpiCapabilities'] ?? self::defaultEnabled();
        if (!is_array($configured)) {
            return in_array($capability, self::defaultEnabled(), true);
        }

        return in_array($capability, $configured, true);
    }

    public static function assertEnabled(string $capability): void
    {
        if (!self::isEnabled($capability)) {
            throw new \RuntimeException('Capacidad MPI deshabilitada: ' . $capability);
        }
    }
}
