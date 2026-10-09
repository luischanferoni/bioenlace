<?php

namespace common\components\Platform\Assistant\Chat\Channels\Guide;

/**
 * Parsea la salida JSON de la 2ª IA Guide ({@see channels/Guide/prompt.yaml}).
 *
 * Contrato canónico:
 * { "mensaje": string, "botones_considerados": [ { "intent_id": string, "params"?: object } ] }
 *
 * Compat: "botones" como alias de "botones_considerados"; "id" como alias de "intent_id".
 */
final class GuideIaResponseParser
{
    /**
     * @return array{
     *   mensaje: string,
     *   botones: list<array{intent_id: string, params: array<string, mixed>}>
     * }|null null si no hay JSON válido con campo mensaje
     */
    public static function parse(?string $raw): ?array
    {
        if ($raw === null) {
            return null;
        }
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $json = self::extractJsonObject($raw);
        if ($json === null) {
            return null;
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded) || !array_key_exists('mensaje', $decoded)) {
            return null;
        }

        $mensaje = trim((string) $decoded['mensaje']);
        $botones = [];
        $rawButtons = self::rawButtonsList($decoded);
        foreach ($rawButtons as $row) {
            if (!is_array($row)) {
                continue;
            }
            $intentId = trim((string) ($row['intent_id'] ?? $row['id'] ?? ''));
            if ($intentId === '') {
                continue;
            }
            $params = [];
            if (isset($row['params']) && is_array($row['params'])) {
                $params = $row['params'];
            }
            $botones[] = [
                'intent_id' => $intentId,
                'params' => $params,
            ];
        }

        return [
            'mensaje' => $mensaje,
            'botones' => $botones,
        ];
    }

    /**
     * @param array<string, mixed> $decoded
     * @return list<mixed>
     */
    private static function rawButtonsList(array $decoded): array
    {
        foreach (['botones_considerados', 'botones'] as $key) {
            if (isset($decoded[$key]) && is_array($decoded[$key])) {
                return $decoded[$key];
            }
        }

        return [];
    }

    private static function extractJsonObject(string $raw): ?string
    {
        if (preg_match('/```(?:json)?\s*(\{[\s\S]*?\})\s*```/u', $raw, $m) === 1) {
            $candidate = trim((string) $m[1]);
            if (self::isJsonObject($candidate)) {
                return $candidate;
            }
        }

        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $candidate = trim(substr($raw, $start, $end - $start + 1));
        if (!self::isJsonObject($candidate)) {
            return null;
        }

        return $candidate;
    }

    private static function isJsonObject(string $candidate): bool
    {
        if ($candidate === '' || $candidate[0] !== '{') {
            return false;
        }
        $decoded = json_decode($candidate, true);

        return is_array($decoded) && json_last_error() === JSON_ERROR_NONE;
    }
}
