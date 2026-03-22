<?php

namespace common\components\Chatbot;

/**
 * @deprecated Usar {@see MensajeIntentRouter}. El nombre "Consulta" colisiona con la consulta médica.
 */
class ConsultaIntentRouter
{
    public static function process($message, $userId = null, $botId = 'BOT')
    {
        return MensajeIntentRouter::process($message, $userId, $botId);
    }

    public static function getIntentInfo($category, $intent)
    {
        return MensajeIntentRouter::getIntentInfo($category, $intent);
    }
}
