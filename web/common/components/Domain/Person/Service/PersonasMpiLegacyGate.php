<?php

namespace common\components\Domain\Person\Service;

use yii\web\GoneHttpException;

/**
 * El alta/búsqueda MPI legacy (candidatos, empadronar, seleccionar-persona) está retirada.
 */
final class PersonasMpiLegacyGate
{
    public const MENSAJE = 'El flujo MPI de búsqueda y alta fue reemplazado. Usá «Registrar paciente» (lector DNI o Didit).';

    public static function deny(): void
    {
        throw new GoneHttpException(self::MENSAJE);
    }
}
