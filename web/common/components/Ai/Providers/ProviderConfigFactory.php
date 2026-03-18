<?php

namespace common\components\Ai\Providers;

use Yii;
use common\components\Ai\Providers\Google\GoogleAuth;

final class ProviderConfigFactory
{
    /**
     * @param string|null $tipoModelo Para HF: 'text-generation'|'text-correction'|'analysis'
     * @return array
     */
    public static function forConfiguredProvider($tipoModelo = null)
    {
        $provider = Yii::$app->params['ia_proveedor'] ?? 'huggingface';

        switch ($provider) {
            case 'openai':
                return self::openai();
            case 'groq':
                return self::groq();
            case 'ollama':
                return self::ollama();
            case 'google':
                return self::google();
            case 'huggingface':
            default:
                return self::huggingface($tipoModelo);
        }
    }

    public static function openai()
    {
        return [
            'tipo' => 'openai',
            'endpoint' => 'https://api.openai.com/v1/chat/completions',
            'headers' => [
                'Authorization' => 'Bearer ' . (Yii::$app->params['openai_api_key'] ?? ''),
                'OpenAI-Organization' => 'org-E9vasCzjdBU9rnnizXrWV032',
                'OpenAI-Project' => 'proj_PVE3UFOdCED5T55jhxToQD2R',
                'Content-Type' => 'application/json',
            ],
            'payload' => [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'user', 'content' => ''],
                ],
                'max_tokens' => 1000,
            ],
        ];
    }

    public static function groq()
    {
        return [
            'tipo' => 'groq',
            'endpoint' => 'https://api.groq.com/openai/v1/chat/completions',
            'headers' => [
                'Authorization' => 'Bearer ' . (Yii::$app->params['groq_api_key'] ?? ''),
                'Content-Type' => 'application/json',
            ],
            'payload' => [
                'model' => 'llama3-70b-8192',
                'messages' => [
                    ['role' => 'user', 'content' => ''],
                ],
                'max_completion_tokens' => 8192,
                'temperature' => 0.3,
                'top_p' => 0.9,
            ],
        ];
    }

    public static function ollama()
    {
        return [
            'tipo' => 'ollama',
            'endpoint' => 'http://192.168.1.11:11434/api/generate',
            'headers' => ['Content-Type' => 'application/json'],
            'payload' => [
                'model' => 'llama3.1:70b',
                'prompt' => '',
                'stream' => false,
                'options' => [
                    'temperature' => 0.0,
                    'top_p' => 0.9,
                    'top_k' => 40,
                    'num_predict' => 4096,
                    'repeat_penalty' => 1.1,
                ],
            ],
        ];
    }

    public static function huggingface($tipoModelo = 'text-generation')
    {
        $modelos = [
            'text-generation' => Yii::$app->params['hf_model_text_gen'] ?? 'HuggingFaceH4/zephyr-7b-beta',
            'text-correction' => Yii::$app->params['hf_model_correction'] ?? 'PlanTL-GOB-ES/roberta-base-biomedical-clinical-es',
            'analysis' => Yii::$app->params['hf_model_analysis'] ?? 'microsoft/DialoGPT-small',
        ];
        $modelo = $modelos[$tipoModelo] ?? $modelos['text-generation'];

        return [
            'tipo' => 'huggingface',
            'endpoint' => "https://router.huggingface.co/v1/chat/completions",
            'headers' => [
                'Authorization' => 'Bearer ' . (Yii::$app->params['hf_api_key'] ?? ''),
                'Content-Type' => 'application/json',
            ],
            'payload' => [
                'model' => $modelo,
                'messages' => [],
                'stream' => false,
                'max_tokens' => (int)(Yii::$app->params['hf_max_length'] ?? 2000),
                'temperature' => (float)(Yii::$app->params['hf_temperature'] ?? 0.2),
            ],
            'modelo' => $modelo,
            'tipo_modelo' => $tipoModelo,
        ];
    }

    public static function google()
    {
        $projectId = Yii::$app->params['google_cloud_project_id'] ?? '';
        $location = Yii::$app->params['google_cloud_region'] ?? 'us-central1';
        $model = Yii::$app->params['vertex_ai_model'] ?? 'gemini-1.5-pro';

        $apiKey = Yii::$app->params['google_cloud_api_key'] ?? '';
        $useGenerativeAi = !empty($apiKey);

        if ($useGenerativeAi) {
            $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";
        } else {
            $endpoint = "https://{$location}-aiplatform.googleapis.com/v1/projects/{$projectId}/locations/{$location}/publishers/google/models/{$model}:generateContent";
        }

        $headers = ['Content-Type' => 'application/json'];

        if ($useGenerativeAi) {
            $endpoint .= "?key={$apiKey}";
        } else {
            $token = GoogleAuth::getAccessToken();
            if (empty($token)) {
                Yii::error('No se pudo obtener token de Google Cloud. Verifique las credenciales configuradas.', 'ia-manager');
                throw new \Exception(
                    'Error de autenticación con Google Cloud: No se pudo obtener token OAuth2. Configure google_cloud_credentials_path o google_cloud_api_key en frontend/config/params-local.php'
                );
            }
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        $maxOutputTokens = (int)(
            Yii::$app->params['google_max_output_tokens'] ??
            Yii::$app->params['vertex_ai_max_tokens'] ??
            8192
        );
        $maxOutputTokens = min($maxOutputTokens, 8192);
        if ($maxOutputTokens < 2000) {
            $maxOutputTokens = 8192;
            Yii::warning("IAManager: maxOutputTokens estaba muy bajo, aumentado a 8192 para evitar truncamiento", 'ia-manager');
        }

        $payload = [
            'contents' => [],
            'generationConfig' => [
                'maxOutputTokens' => $maxOutputTokens,
                'temperature' => (float)(Yii::$app->params['hf_temperature'] ?? 0.3),
            ],
        ];

        Yii::info("IAManager: Configuración Google con maxOutputTokens: {$maxOutputTokens}, Modelo: {$model}", 'ia-manager');

        return [
            'tipo' => 'google',
            'endpoint' => $endpoint,
            'headers' => $headers,
            'usar_generative_ai' => $useGenerativeAi,
            'payload' => $payload,
        ];
    }
}

