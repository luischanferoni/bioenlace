<?php

namespace common\components\Platform\Assistant\Context;

use Yii;

final class AssistantContextFormatter
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $scopeApplied
     */
    public static function formatBlock(array $payload, array $scopeApplied = []): string
    {
        $document = [
            'schema_version' => 1,
            'scope_applied' => $scopeApplied,
        ];
        foreach ($payload as $aspectKey => $data) {
            $document[$aspectKey] = $data;
        }

        $json = json_encode($document, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($json === false) {
            return '';
        }

        $maxChars = max(500, (int) (Yii::$app->params['asistente_context_max_chars'] ?? 8000));
        if (strlen($json) > $maxChars) {
            $json = substr($json, 0, $maxChars) . "\n... [truncado]";
        }

        return "--- context:his ---\n" . $json . "\n--- end context:his ---";
    }
}
