<?php

namespace common\components\intent_handlers;

use Yii;
use common\components\UniversalQueryAgent;

/**
 * Handler para efectores y ubicaciones
 */
class EfectoresHandler extends BaseIntentHandler
{
    public function handle($intent, $message, $parameters, $context, $userId = null)
    {
        $this->log('handle', ['intent' => $intent]);
        
        $query = $message;
        $actionResult = UniversalQueryAgent::processQuery($query, $userId);
        
        $respuesta = "Aquí está la información que buscás.";
        
        return $this->generateSuccessResponse(
            $respuesta,
            [],
            $actionResult['data']['actions'] ?? []
        );
    }
}
