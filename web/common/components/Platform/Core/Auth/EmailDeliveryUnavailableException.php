<?php

namespace common\components\Platform\Core\Auth;

/**
 * El envío de e-mails no está disponible en este entorno (sin componente
 * `mailer` o SMTP caído).
 */
final class EmailDeliveryUnavailableException extends \RuntimeException
{
}
