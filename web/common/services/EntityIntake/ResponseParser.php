<?php

namespace common\services\EntityIntake;

final class ResponseParser
{
    /**
     * Intenta parsear JSON aunque venga con fences/ruido.
     *
     * @return array{prefill: array, missing_required: array, confidence: float, raw?: mixed, parse_error?: string}
     */
    public static function parse($raw): array
    {
        if (is_array($raw)) {
            return self::normalize($raw);
        }

        if (!is_string($raw)) {
            return [
                'prefill' => [],
                'missing_required' => [],
                'confidence' => 0.0,
                'parse_error' => 'raw_not_string',
                'raw' => $raw,
            ];
        }

        $candidate = trim($raw);

        // Remover fences ```json ... ```
        $candidate = preg_replace('/^```(?:json)?\s*/i', '', $candidate) ?? $candidate;
        $candidate = preg_replace('/\s*```$/', '', $candidate) ?? $candidate;

        // Recortar a primer { ... último } si hay texto extra.
        $first = strpos($candidate, '{');
        $last = strrpos($candidate, '}');
        if ($first !== false && $last !== false && $last > $first) {
            $candidate = substr($candidate, $first, $last - $first + 1);
        }

        $decoded = json_decode($candidate, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return [
                'prefill' => [],
                'missing_required' => [],
                'confidence' => 0.0,
                'parse_error' => json_last_error_msg(),
                'raw' => $raw,
            ];
        }

        return self::normalize($decoded);
    }

    private static function normalize(array $decoded): array
    {
        $prefill = $decoded['prefill'] ?? [];
        if (!is_array($prefill)) {
            $prefill = [];
        }

        $missing = $decoded['missing_required'] ?? [];
        if (!is_array($missing)) {
            $missing = [];
        }

        $confidence = $decoded['confidence'] ?? 0.0;
        if (!is_numeric($confidence)) {
            $confidence = 0.0;
        }

        return [
            'prefill' => $prefill,
            'missing_required' => array_values($missing),
            'confidence' => (float)$confidence,
            'raw' => $decoded,
        ];
    }
}

