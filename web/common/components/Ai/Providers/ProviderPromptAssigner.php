<?php

namespace common\components\Ai\Providers;

final class ProviderPromptAssigner
{
    /**
     * @param array $proveedorIA
     * @param string $prompt
     * @return void
     */
    public static function assignPrompt(&$proveedorIA, $prompt)
    {
        switch ($proveedorIA['tipo']) {
            case 'ollama':
                $proveedorIA['payload']['prompt'] = $prompt;
                break;
            case 'openai':
            case 'groq':
            case 'huggingface':
                $proveedorIA['payload']['messages'][] = ['role' => 'user', 'content' => $prompt];
                break;
            case 'google':
                $proveedorIA['payload']['contents'][] = [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ];
                break;
        }
    }
}

