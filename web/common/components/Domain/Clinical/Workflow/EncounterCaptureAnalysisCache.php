<?php

namespace common\components\Domain\Clinical\Workflow;

use Yii;

/**
 * Snapshot corto del análisis IA entre /analizar y /guardar.
 * Evita perder medicación/indicaciones/motivos si el cliente envía un stage incompleto.
 */
final class EncounterCaptureAnalysisCache
{
    private const TTL_SECONDS = 7200;

    /**
     * @param array<string, mixed> $body
     * @param array<string, mixed> $datosExtraidos
     */
    public static function store(array $body, array $datosExtraidos, string $textoClinico): ?string
    {
        if ($datosExtraidos === [] || !self::looksLikeCategories($datosExtraidos)) {
            return null;
        }
        $token = self::buildToken($body, $textoClinico);
        if ($token === null) {
            return null;
        }
        try {
            Yii::$app->cache->set(self::cacheKey($token), [
                'datosExtraidos' => $datosExtraidos,
                'texto' => self::normalizeText($textoClinico),
                'stored_at' => time(),
            ], self::TTL_SECONDS);
        } catch (\Throwable $e) {
            Yii::warning('EncounterCaptureAnalysisCache::store: ' . $e->getMessage(), 'encounter-doc');

            return null;
        }

        return $token;
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public static function recall(array $body, ?string $textoClinico = null): array
    {
        $candidates = [];
        $explicit = trim((string) ($body['analysis_cache_token'] ?? $body['analisis_cache_token'] ?? ''));
        if ($explicit !== '') {
            $candidates[] = $explicit;
        }
        if ($textoClinico !== null && trim($textoClinico) !== '') {
            $fromText = self::buildToken($body, $textoClinico);
            if ($fromText !== null) {
                $candidates[] = $fromText;
            }
        }
        foreach (['texto_procesado', 'texto_original', 'consulta', 'note'] as $key) {
            $text = trim((string) ($body[$key] ?? ''));
            if ($text === '') {
                continue;
            }
            $fromBody = self::buildToken($body, $text);
            if ($fromBody !== null) {
                $candidates[] = $fromBody;
            }
        }

        foreach (array_values(array_unique($candidates)) as $token) {
            try {
                $payload = Yii::$app->cache->get(self::cacheKey($token));
            } catch (\Throwable $e) {
                Yii::warning('EncounterCaptureAnalysisCache::recall: ' . $e->getMessage(), 'encounter-doc');
                continue;
            }
            if (!is_array($payload)) {
                continue;
            }
            $extraidos = $payload['datosExtraidos'] ?? null;
            if (is_array($extraidos) && self::looksLikeCategories($extraidos)) {
                return $extraidos;
            }
        }

        return [];
    }

    /**
     * @param array<string, mixed> $body
     */
    private static function buildToken(array $body, string $textoClinico): ?string
    {
        $texto = self::normalizeText($textoClinico);
        if ($texto === '') {
            return null;
        }
        $subject = (int) ($body['id_persona'] ?? $body['subject_persona_id'] ?? 0);
        $parent = strtoupper(trim((string) ($body['parent'] ?? '')));
        $parentId = (int) ($body['parent_id'] ?? 0);
        $encounterId = (int) ($body['encounter_id'] ?? $body['id_consulta'] ?? 0);

        return hash(
            'sha256',
            implode('|', [
                'v1',
                (string) $subject,
                $parent,
                (string) $parentId,
                (string) $encounterId,
                hash('sha256', $texto),
            ])
        );
    }

    private static function cacheKey(string $token): string
    {
        return 'encounter_capture_analysis:' . $token;
    }

    private static function normalizeText(string $texto): string
    {
        $folded = strtr(mb_strtolower(trim($texto), 'UTF-8'), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);
        $folded = preg_replace('/\s+/u', ' ', $folded) ?? $folded;

        return trim($folded);
    }

    /**
     * @param array<string, mixed> $datosExtraidos
     */
    private static function looksLikeCategories(array $datosExtraidos): bool
    {
        foreach ($datosExtraidos as $key => $value) {
            if (!is_string($key) || $key === '' || $key === 'Error') {
                continue;
            }
            if (is_array($value)) {
                return true;
            }
        }

        return false;
    }
}
