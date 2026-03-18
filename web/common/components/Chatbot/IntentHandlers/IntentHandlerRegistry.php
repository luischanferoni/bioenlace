<?php

namespace common\components\Chatbot\IntentHandlers;

use Yii;

final class IntentHandlerRegistry
{
    /**
     * @param string $category
     * @param string $intent
     * @return object|null
     */
    public static function getHandler($category, $intent)
    {
        $categories = require Yii::getAlias('@common/config/chatbot/intent-categories.php');

        if (!isset($categories[$category]['intents'][$intent])) {
            return null;
        }

        $intentConfig = $categories[$category]['intents'][$intent];
        $handlerName = $intentConfig['handler'] ?? null;
        if (!$handlerName) {
            return null;
        }

        // Preferir el namespace nuevo; fallback al viejo.
        $handlerClassNew = "common\\components\\Chatbot\\IntentHandlers\\Handlers\\{$handlerName}";
        $handlerClassOld = "common\\components\\intent_handlers\\{$handlerName}";
        $handlerClass = class_exists($handlerClassNew) ? $handlerClassNew : $handlerClassOld;
        if (!class_exists($handlerClass)) {
            Yii::warning("IntentHandlerRegistry: Handler class '{$handlerClassNew}'/'{$handlerClassOld}' no existe", 'consulta-intent-router');
            return null;
        }

        return new $handlerClass();
    }
}

