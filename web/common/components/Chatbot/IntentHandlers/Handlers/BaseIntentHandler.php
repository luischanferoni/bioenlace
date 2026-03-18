<?php

namespace common\components\Chatbot\IntentHandlers\Handlers;

use Yii;
use common\components\Chatbot\ConversationContext;
use common\components\Chatbot\ParameterQuestionRegistry;

abstract class BaseIntentHandler
{
    abstract public function handle($intent, $message, $parameters, $context, $userId = null);

    protected function getMissingRequiredParams($intent, $parameters)
    {
        $intentConfig = require Yii::getAlias('@common/config/chatbot/intent-parameters.php');

        if (!isset($intentConfig[$intent])) {
            return [];
        }

        $requiredParams = $intentConfig[$intent]['required_params'] ?? [];
        $missing = [];

        foreach ($requiredParams as $param) {
            if (!isset($parameters[$param]) || empty($parameters[$param])) {
                $missing[] = $param;
            }
        }

        return $missing;
    }

    protected function generateMissingParamsResponse($missingParams, $intent)
    {
        $questions = $this->getQuestionsForParams($missingParams);

        return [
            'success' => true,
            'needs_more_info' => true,
            'missing_params' => $missingParams,
            'response' => [
                'text' => implode(' ', $questions),
                'awaiting' => $missingParams[0] ?? null,
            ],
            'suggestions' => $this->getSuggestionsForParams($missingParams),
        ];
    }

    protected function getQuestionsForParams($params)
    {
        $questions = ParameterQuestionRegistry::getQuestions($params);
        $result = [];
        foreach ($params as $param) {
            if (isset($questions[$param])) {
                $result[] = $questions[$param];
            } else {
                Yii::debug("No se encontró pregunta para el parámetro: {$param}", 'intent-handler');
            }
        }
        return $result;
    }

    protected function getSuggestionsForParams($params)
    {
        return [];
    }

    protected function generateSuccessResponse($text, $data = [], $actions = [])
    {
        return [
            'success' => true,
            'needs_more_info' => false,
            'response' => [
                'text' => $text,
                'data' => $data,
            ],
            'actions' => $actions,
            'suggestions' => [],
        ];
    }

    protected function generateErrorResponse($message, $details = [])
    {
        return [
            'success' => false,
            'error' => $message,
            'details' => $details,
        ];
    }

    protected function updateContext($userId, $context, $intent, $parameters)
    {
        $context = ConversationContext::merge($context, $intent, $parameters);
        ConversationContext::save($userId, $context);
        return $context;
    }

    protected function buildMinimalPrompt($message, $context)
    {
        return ConversationContext::buildMinimalPrompt($context, $message);
    }

    protected function log($action, $data = [])
    {
        $handlerName = static::class;
        Yii::info("{$handlerName}::{$action} - " . json_encode($data, JSON_UNESCAPED_UNICODE), 'intent-handler');
    }
}

