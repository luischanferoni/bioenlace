<?php

namespace common\components\intent_handlers;

use Yii;
use common\components\UniversalQueryAgent;

/**
 * Handler para historia clínica
 */
class HistoriaClinicaHandler extends BaseIntentHandler
{
    public function handle($intent, $message, $parameters, $context, $userId = null)
    {
        $this->log('handle', ['intent' => $intent]);
        
        // Usar UniversalQueryAgent para buscar acciones relacionadas
        $query = "ver {$intent}";
        $actionResult = UniversalQueryAgent::processQuery($query, $userId);
        
        $respuesta = "Aquí está tu información de historia clínica.";
        
        return $this->generateSuccessResponse(
            $respuesta,
            [],
            $actionResult['data']['actions'] ?? []
        );
    }
}
