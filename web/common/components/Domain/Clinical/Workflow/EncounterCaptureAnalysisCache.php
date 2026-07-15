<?php

namespace common\components\Domain\Clinical\Workflow;

use common\models\Clinical\EncounterCaptureAnalysis;
use Yii;

/**
 * Snapshot del análisis IA entre /analizar y /guardar.
 * Persistido en BD (multi-nodo) y replicado en cache local.
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

        $payload = [
            'datosExtraidos' => $datosExtraidos,
            'texto' => self::normalizeText($textoClinico),
            'stored_at' => time(),
        ];

        try {
            Yii::$app->cache->set(self::cacheKey($token), $payload, self::TTL_SECONDS);
        } catch (\Throwable $e) {
            Yii::warning('EncounterCaptureAnalysisCache::store cache: ' . $e->getMessage(), 'encounter-doc');
        }

        self::storeDb($token, $body, $textoClinico, $datosExtraidos);

        return $token;
    }

    /**
     * @param array<string, mixed> $body
     * @return array{extraidos: array<string, mixed>, fuente: string}
     */
    public static function recallWithMeta(array $body, ?string $textoClinico = null): array
    {
        $tokens = self::candidateTokens($body, $textoClinico);

        foreach ($tokens as $token) {
            try {
                $payload = Yii::$app->cache->get(self::cacheKey($token));
            } catch (\Throwable $e) {
                $payload = null;
                Yii::warning('EncounterCaptureAnalysisCache::recall cache: ' . $e->getMessage(), 'encounter-doc');
            }
            if (is_array($payload)) {
                $extraidos = $payload['datosExtraidos'] ?? null;
                if (is_array($extraidos) && self::looksLikeCategories($extraidos)) {
                    return ['extraidos' => $extraidos, 'fuente' => 'cache_local', 'token' => $token];
                }
            }
        }

        foreach ($tokens as $token) {
            $row = self::findDbByToken($token);
            $extraidos = self::decodeRow($row);
            if ($extraidos !== []) {
                return ['extraidos' => $extraidos, 'fuente' => 'db_token', 'token' => $token];
            }
        }

        $byContext = self::findDbByContext($body, $textoClinico);
        $extraidos = self::decodeRow($byContext);
        if ($extraidos !== []) {
            return [
                'extraidos' => $extraidos,
                'fuente' => 'db_context',
                'token' => $byContext instanceof EncounterCaptureAnalysis ? $byContext->token : null,
            ];
        }

        return ['extraidos' => [], 'fuente' => 'none', 'token' => null];
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public static function recall(array $body, ?string $textoClinico = null): array
    {
        return self::recallWithMeta($body, $textoClinico)['extraidos'];
    }

    /**
     * @param array<string, mixed> $body
     * @return list<string>
     */
    private static function candidateTokens(array $body, ?string $textoClinico): array
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

        return array_values(array_unique($candidates));
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, mixed> $datosExtraidos
     */
    private static function storeDb(string $token, array $body, string $textoClinico, array $datosExtraidos): void
    {
        try {
            if (Yii::$app->db->schema->getTableSchema(EncounterCaptureAnalysis::tableName(), true) === null) {
                return;
            }
            self::purgeExpiredDb();

            $json = json_encode($datosExtraidos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                return;
            }

            $row = EncounterCaptureAnalysis::findOne(['token' => $token]) ?? new EncounterCaptureAnalysis();
            $row->token = $token;
            $row->subject_persona_id = ((int) ($body['id_persona'] ?? $body['subject_persona_id'] ?? 0)) ?: null;
            $parent = strtoupper(trim((string) ($body['parent'] ?? '')));
            $row->parent_type = $parent !== '' ? $parent : null;
            $row->parent_id = ((int) ($body['parent_id'] ?? 0)) ?: null;
            $row->encounter_id = ((int) ($body['encounter_id'] ?? $body['id_consulta'] ?? 0)) ?: null;
            $row->texto_hash = hash('sha256', self::normalizeText($textoClinico));
            $row->datos_extraidos_json = $json;
            $row->created_at = date('Y-m-d H:i:s');
            if (!$row->save()) {
                Yii::warning(
                    'EncounterCaptureAnalysisCache::storeDb: ' . json_encode($row->getErrors()),
                    'encounter-doc'
                );
            }
        } catch (\Throwable $e) {
            Yii::warning('EncounterCaptureAnalysisCache::storeDb: ' . $e->getMessage(), 'encounter-doc');
        }
    }

    private static function findDbByToken(string $token): ?EncounterCaptureAnalysis
    {
        try {
            if (Yii::$app->db->schema->getTableSchema(EncounterCaptureAnalysis::tableName(), true) === null) {
                return null;
            }
            $row = EncounterCaptureAnalysis::find()
                ->where(['token' => $token])
                ->andWhere(['>=', 'created_at', date('Y-m-d H:i:s', time() - self::TTL_SECONDS)])
                ->one();

            return $row instanceof EncounterCaptureAnalysis ? $row : null;
        } catch (\Throwable $e) {
            Yii::warning('EncounterCaptureAnalysisCache::findDbByToken: ' . $e->getMessage(), 'encounter-doc');

            return null;
        }
    }

    /**
     * @param array<string, mixed> $body
     */
    private static function findDbByContext(array $body, ?string $textoClinico): ?EncounterCaptureAnalysis
    {
        try {
            if (Yii::$app->db->schema->getTableSchema(EncounterCaptureAnalysis::tableName(), true) === null) {
                return null;
            }

            $encounterId = (int) ($body['encounter_id'] ?? $body['id_consulta'] ?? 0);
            if ($encounterId > 0) {
                $row = EncounterCaptureAnalysis::find()
                    ->where(['encounter_id' => $encounterId])
                    ->andWhere(['>=', 'created_at', date('Y-m-d H:i:s', time() - self::TTL_SECONDS)])
                    ->orderBy(['id' => SORT_DESC])
                    ->one();
                if ($row instanceof EncounterCaptureAnalysis) {
                    return $row;
                }
            }

            $subject = (int) ($body['id_persona'] ?? $body['subject_persona_id'] ?? 0);
            $parent = strtoupper(trim((string) ($body['parent'] ?? '')));
            $parentId = (int) ($body['parent_id'] ?? 0);
            if ($subject <= 0 || $parent === '' || $parentId <= 0) {
                return null;
            }

            $query = EncounterCaptureAnalysis::find()
                ->where([
                    'subject_persona_id' => $subject,
                    'parent_type' => $parent,
                    'parent_id' => $parentId,
                ])
                ->andWhere(['>=', 'created_at', date('Y-m-d H:i:s', time() - self::TTL_SECONDS)])
                ->orderBy(['id' => SORT_DESC]);

            if ($textoClinico !== null && trim($textoClinico) !== '') {
                $query->andWhere(['texto_hash' => hash('sha256', self::normalizeText($textoClinico))]);
            }

            $row = $query->one();

            return $row instanceof EncounterCaptureAnalysis ? $row : null;
        } catch (\Throwable $e) {
            Yii::warning('EncounterCaptureAnalysisCache::findDbByContext: ' . $e->getMessage(), 'encounter-doc');

            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function decodeRow(?EncounterCaptureAnalysis $row): array
    {
        if ($row === null) {
            return [];
        }
        $decoded = json_decode((string) $row->datos_extraidos_json, true);
        if (!is_array($decoded) || !self::looksLikeCategories($decoded)) {
            return [];
        }

        return $decoded;
    }

    private static function purgeExpiredDb(): void
    {
        try {
            EncounterCaptureAnalysis::deleteAll([
                '<',
                'created_at',
                date('Y-m-d H:i:s', time() - self::TTL_SECONDS),
            ]);
        } catch (\Throwable $e) {
            // ignore
        }
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
