<?php

namespace common\components\Clinical\CareCohort\Batch;

use common\components\Ai\Cost\AICostTracker;
use common\components\Clinical\CareCohort\Service\CarePackConfig;

/**
 * Telemetría de inferencias Vertex batch → AICostTracker (contexto único de producción).
 */
final class CarePackVertexBatchTelemetry
{
    public const CONTEXT = 'care-pack-vertex-batch';

    /**
     * Registra tokens (si vienen en la línea JSONL) y cuenta 1 inferencia batch.
     *
     * @param array<string, mixed> $decoded Línea JSONL de salida Vertex
     */
    public static function registrarLineaCompletada(array $decoded): void
    {
        if (!class_exists(AICostTracker::class) || !AICostTracker::trackingHabilitado()) {
            return;
        }

        $usageJson = self::usageMetadataJson($decoded);
        if ($usageJson !== null) {
            AICostTracker::registrarUsoDesdeRespuestaGemini($usageJson, self::CONTEXT);
        } else {
            AICostTracker::registrarLlamadaReal(self::CONTEXT);
        }
    }

    /**
     * @param array<string, mixed> $decoded
     */
    public static function usageMetadataJson(array $decoded): ?string
    {
        $response = $decoded['response'] ?? null;
        if (!is_array($response)) {
            return null;
        }

        $meta = $response['usageMetadata'] ?? null;
        if (!is_array($meta)) {
            return null;
        }

        return json_encode(['usageMetadata' => $meta], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Contexto IAManager para jobs sync (no batch).
     */
    public static function syncContextForPackType(string $packType): string
    {
        return \common\components\Clinical\CareCohort\Enum\CarePackType::iaContext($packType);
    }

    public static function vertexBatchConfigured(): bool
    {
        return CarePackConfig::vertexBatchEnabled();
    }
}
