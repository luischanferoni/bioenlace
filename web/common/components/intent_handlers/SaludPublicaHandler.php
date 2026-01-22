<?php

namespace common\components\intent_handlers;

use Yii;
use common\components\UniversalQueryAgent;

/**
 * Handler para salud pública y epidemiología
 */
class SaludPublicaHandler extends BaseIntentHandler
{
    public function handle($intent, $message, $parameters, $context, $userId = null)
    {
        $this->log('handle', ['intent' => $intent]);
        
        $query = $message;
        $actionResult = UniversalQueryAgent::processQuery($query, $userId);
        
        $respuesta = "Aquí está la información sobre salud pública.";
        
        return $this->generateSuccessResponse(
            $respuesta,
            [],
            $actionResult['data']['actions'] ?? []
        );
    }
}
