<?php

namespace common\components\Platform\Core\Permission\Domain;

/**
 * El usuario no puede ejecutar la operación sobre el recurso indicado (capa dominio, sin HTTP).
 */
final class DomainOperationForbiddenException extends \RuntimeException
{
}
