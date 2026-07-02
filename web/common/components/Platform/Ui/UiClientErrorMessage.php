<?php

namespace common\components\Platform\Ui;

use common\components\Domain\Scheduling\Service\PolicyModeradaException;
use common\components\Platform\Core\Permission\Domain\DomainOperationForbiddenException;
use Yii;
use yii\base\UnknownPropertyException;
use yii\web\HttpException;

/**
 * Mensajes seguros para respuestas UI JSON (sin filtrar errores técnicos al paciente/staff).
 */
final class UiClientErrorMessage
{
    private const GENERIC = 'No pudimos completar la operación. Intentá de nuevo en unos instantes.';

    public static function fromThrowable(\Throwable $e): string
    {
        if ($e instanceof UnknownPropertyException) {
            self::logTechnical($e);

            return self::GENERIC;
        }

        if ($e instanceof \InvalidArgumentException) {
            return $e->getMessage();
        }

        if ($e instanceof HttpException) {
            return $e->getMessage();
        }

        if ($e instanceof PolicyModeradaException || $e instanceof DomainOperationForbiddenException) {
            return $e->getMessage();
        }

        $msg = trim($e->getMessage());
        if ($msg !== '' && !self::looksTechnical($msg)) {
            return $msg;
        }

        self::logTechnical($e);

        return self::GENERIC;
    }

    private static function looksTechnical(string $msg): bool
    {
        foreach ([
            'Getting unknown property',
            'Unknown Property',
            'SQLSTATE',
            'yii\\',
            'PDO',
            'Stack trace',
            'Fatal error',
            '::findOne',
            'json_encode',
            'No se pudo crear el encounter',
        ] as $needle) {
            if (stripos($msg, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private static function logTechnical(\Throwable $e): void
    {
        Yii::warning(
            get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString(),
            'ui-client-error'
        );
    }
}
