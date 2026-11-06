<?php

namespace common\components\Shared\Infrastructure\Http\Auth;

/**
 * No se pudo obtener o renovar un token de acceso.
 *
 * Extiende `\RuntimeException` para que los ACL existentes sigan capturando lo mismo que
 * capturaban con sus propias excepciones de conector.
 */
class OAuthTokenException extends \RuntimeException
{
}
