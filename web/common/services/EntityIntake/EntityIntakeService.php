<?php

namespace common\services\EntityIntake;

use Yii;

final class EntityIntakeService
{
    /**
     * @return array{
     *   success: bool,
     *   entity: string,
     *   intent: string|null,
     *   prefill: array,
     *   missing_required: array,
     *   confidence: float,
     *   parse_error?: string,
     *   raw?: mixed
     * }
     */
    public static function analyze(string $text, string $entity, ?string $intent = null): array
    {
        $schema = EntitySchemaRegistry::getSchema($entity, $intent);
        if ($schema === null) {
            return [
                'success' => false,
                'entity' => $entity,
                'intent' => $intent,
                'prefill' => [],
                'missing_required' => [],
                'confidence' => 0.0,
                'parse_error' => 'schema_not_found',
            ];
        }

        $prompt = PromptBuilder::build($text, $entity, $intent, $schema);

        $raw = null;
        try {
            $raw = Yii::$app->iamanager->consultar($prompt, 'entity-intake', 'analysis');
        } catch (\Throwable $e) {
            Yii::error('EntityIntakeService error: ' . $e->getMessage(), 'entity-intake');
            return [
                'success' => false,
                'entity' => $entity,
                'intent' => $intent,
                'prefill' => [],
                'missing_required' => [],
                'confidence' => 0.0,
                'parse_error' => 'ia_error: ' . $e->getMessage(),
            ];
        }

        $parsed = ResponseParser::parse($raw);

        // Si el modelo no llenó missing_required, calcularlo desde schema.required + prefill
        $required = $schema['required'] ?? [];
        $prefill = $parsed['prefill'] ?? [];
        if (empty($parsed['missing_required']) && !empty($required)) {
            $missing = [];
            foreach ($required as $field) {
                if (!array_key_exists($field, $prefill) || $prefill[$field] === null || $prefill[$field] === '') {
                    $missing[] = $field;
                }
            }
            $parsed['missing_required'] = $missing;
        }

        $result = [
            'success' => true,
            'entity' => $entity,
            'intent' => $intent,
            'prefill' => $parsed['prefill'] ?? [],
            'missing_required' => $parsed['missing_required'] ?? [],
            'confidence' => (float)($parsed['confidence'] ?? 0.0),
            'raw' => $parsed['raw'] ?? null,
            'parse_error' => $parsed['parse_error'] ?? null,
        ];

        Yii::info(
            'EntityIntake analyzed: ' . json_encode([
                'entity' => $entity,
                'intent' => $intent,
                'missing_required_count' => count($result['missing_required'] ?? []),
            ], JSON_UNESCAPED_UNICODE),
            'entity-intake'
        );

        return $result;
    }
}

