<?php

namespace common\components\Services\Turnos;

/**
 * Lanzada cuando la política de autogestión bloquea cancelación/reserva vía app.
 */
class PolicyModeradaException extends \yii\base\UserException
{
}
