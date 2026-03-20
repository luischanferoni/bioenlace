<?php

namespace common\components\Chatbot\IntentHandlers\Handlers;

use common\components\Actions\UniversalQueryAgent;

class ProgramasHandler extends BaseIntentHandler
{
    public function handle($intent, $message, $parameters, $context, $userId = null)
    {
        $this->log('handle', ['intent' => $intent]);

        $query = $message;
        $actionResult = UniversalQueryAgent::processQuery($query, $userId);

        $respuesta = "Aquí está la información sobre programas de salud.";

        return $this->generateSuccessResponse(
            $respuesta,
            [],
            $actionResult['data']['actions'] ?? []
        );
    }
}

