<?php

namespace common\components\Shared\Infrastructure\Requests;

use Yii;

/**
 * Deduplicación de requests de IA dentro del mismo request PHP.
 *
 * - `buscarExacto()` es la **única** vía que devuelve una respuesta cacheada: mismo tipo y
 *   prompt normalizado (trim + espacios colapsados). No se normaliza puntuación ni
 *   mayúsculas: un cambio de puntuación puede cambiar el sentido clínico del prompt.
 * - `observarSimilitud()` mide qué habría hecho un match difuso y lo registra para análisis
 *   posterior. **Nunca** devuelve contenido cacheado ni altera el flujo.
 *
 * @see \common\components\Platform\Ai\IAManager::consultarIA
 */
final class RequestDeduplicator
{
    /** Umbral del match difuso. Solo observabilidad: no se usa para responder. */
    public const SIMILITUD_MINIMA = 0.85;

    /** Categoría de log de la observación de similitud. */
    public const LOG_CATEGORY = 'ia-dedupe';

    private const CACHE_TTL = 300;

    private const MAX_ENTRIES = 1000;

    /** @var array<string, array{prompt: string, response: mixed, tipo: string, timestamp: int}> */
    private static array $requestCache = [];

    /**
     * Devuelve la respuesta sólo si el prompt (normalizado) y el tipo coinciden exactamente.
     *
     * @param mixed $prompt
     * @return mixed|null
     */
    public static function buscarExacto($prompt, $tipo = 'general')
    {
        $key = self::cacheKey($prompt, $tipo);
        $cached = self::$requestCache[$key] ?? null;
        if ($cached === null) {
            return null;
        }

        if (time() - $cached['timestamp'] >= self::CACHE_TTL) {
            unset(self::$requestCache[$key]);

            return null;
        }

        return $cached['response'];
    }

    /**
     * @param mixed $prompt
     * @param mixed $respuesta
     */
    public static function guardar($prompt, $respuesta, $tipo = 'general'): void
    {
        self::$requestCache[self::cacheKey($prompt, $tipo)] = [
            'prompt' => (string) $prompt,
            'response' => $respuesta,
            'tipo' => (string) $tipo,
            'timestamp' => time(),
        ];

        if (count(self::$requestCache) > self::MAX_ENTRIES) {
            self::limpiarExpirados();
        }
    }

    /**
     * Mide la similitud del prompt con lo ya guardado en este request y la registra.
     * Solo observabilidad: no devuelve contenido y no cambia ninguna decisión.
     *
     * No se loguea el texto (contenido clínico): van hashes, longitud y similitud.
     *
     * @param mixed $prompt
     * @return array{max: float, candidatos: int} para tests y métricas
     */
    public static function observarSimilitud($prompt, $tipo = 'general'): array
    {
        $max = 0.0;
        $candidatos = 0;
        $candidato = null;

        foreach (self::$requestCache as $cached) {
            if ($cached['tipo'] !== (string) $tipo) {
                continue;
            }
            if (time() - $cached['timestamp'] >= self::CACHE_TTL) {
                continue;
            }

            $candidatos++;
            $similitud = self::calcularSimilitud((string) $prompt, $cached['prompt']);
            if ($similitud > $max) {
                $max = $similitud;
                $candidato = $cached['prompt'];
            }
        }

        if ($candidatos > 0 && $max >= self::SIMILITUD_MINIMA) {
            Yii::info([
                'event' => 'ia_dedupe.similitud_observada',
                'tipo' => (string) $tipo,
                'similitud' => round($max, 4),
                'umbral' => self::SIMILITUD_MINIMA,
                'prompt_hash' => hash('sha256', self::normalizar($prompt)),
                'prompt_len' => strlen((string) $prompt),
                'candidato_hash' => hash('sha256', self::normalizar($candidato ?? '')),
                'candidatos' => $candidatos,
            ], self::LOG_CATEGORY);
        }

        return ['max' => $max, 'candidatos' => $candidatos];
    }

    /** @internal tests */
    public static function resetCacheForTests(): void
    {
        self::$requestCache = [];
    }

    /**
     * @param mixed $prompt
     */
    private static function cacheKey($prompt, $tipo): string
    {
        return (string) $tipo . '_' . md5(self::normalizar($prompt));
    }

    /**
     * @param mixed $prompt
     */
    private static function normalizar($prompt): string
    {
        $texto = trim((string) $prompt);
        $colapsado = preg_replace('/\s+/u', ' ', $texto);

        return $colapsado === null ? $texto : $colapsado;
    }

    private static function calcularSimilitud(string $prompt1, string $prompt2): float
    {
        $p1 = strtolower(trim($prompt1));
        $p2 = strtolower(trim($prompt2));

        if ($p1 === $p2) {
            return 1.0;
        }

        // levenshtein() solo admite hasta 255 caracteres; con -1 la fórmula infla la
        // similitud. Prompts largos (asistente conversacional) comparten prefijo estable:
        // la similitud por palabras reutilizaría la respuesta del turno anterior.
        if (strlen($p1) > 255 || strlen($p2) > 255) {
            return 0.0;
        }

        $maxLen = max(strlen($p1), strlen($p2));
        if ($maxLen === 0) {
            return 1.0;
        }

        $distancia = levenshtein($p1, $p2);
        if ($distancia < 0) {
            return 0.0;
        }
        $similitudLevenshtein = 1 - ($distancia / $maxLen);

        $palabras1 = array_filter(explode(' ', $p1));
        $palabras2 = array_filter(explode(' ', $p2));

        $palabrasComunes = count(array_intersect($palabras1, $palabras2));
        $totalPalabras = count(array_unique(array_merge($palabras1, $palabras2)));

        $similitudPalabras = $totalPalabras > 0 ? ($palabrasComunes / $totalPalabras) : 0;

        return ($similitudLevenshtein * 0.3) + ($similitudPalabras * 0.7);
    }

    private static function limpiarExpirados(): void
    {
        $ahora = time();
        foreach (self::$requestCache as $key => $cached) {
            if ($ahora - $cached['timestamp'] >= self::CACHE_TTL) {
                unset(self::$requestCache[$key]);
            }
        }
    }
}
