<?php

namespace common\components\Domain\Scheduling\Agenda\Application\Service;

/**
 * Lanzada cuando la política de autogestión bloquea cancelación/reserva vía app.
 */
class PolicyModeradaException extends \yii\base\UserException
{
}
