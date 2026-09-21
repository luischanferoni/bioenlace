<?php

namespace common\components\Domain\Clinical\Capture\Application\Presentation;

use common\components\Domain\Clinical\Capture\Application\Definition\EncounterCaptureCategoryResolver;
use common\components\Domain\Clinical\Capture\Application\RowContract\ClinicalCaptureRowContracts;
use common\components\Domain\Clinical\Encounter\Application\EncounterOpenProblemsService;
use common\components\Domain\Clinical\Encounter\Application\EpisodeCaptureDedupService;
use common\components\Domain\Clinical\Encounter\Application\Presentation\EncounterCaptureReviewPresenter;
use common\models\Clinical\EncounterCapture;
use common\models\Clinical\EncounterDefinition;

/**
 * Forma de respuesta API del checkpoint de captura (ok/fail/toApiArray).
 * Gramática CA del repo: Application/Presentation (*Presenter).
 */
final class ClinicalCapturePresenter
{
    /**
     * @param array<string, mixed> $body
     * @return list<array<string, mixed>>
     */
    public function resolveCategoriasForCapture(EncounterCapture $capture, array $body): array
    {
        $analysis = $capture->getAnalysisResponse();
        $fromAnalysis = $analysis['categorias'] ?? null;
        if (is_array($fromAnalysis) && $fromAnalysis !== []) {
            return array_values(array_filter($fromAnalysis, 'is_array'));
        }

        $idConfig = (int) ($body['id_configuracion'] ?? $analysis['id_configuracion'] ?? 0);
        if ($idConfig <= 0) {
            return [];
        }
        $def = EncounterDefinition::findOne($idConfig);

        return $def !== null
            ? (new EncounterCaptureCategoryResolver())->resolve($def, $body)
            : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function ok(EncounterCapture $capture, string $message, bool $includeAnalysis = false): array
    {
        return [
            'success' => true,
            'message' => $message,
            'capture' => $this->toApiArray($capture, $includeAnalysis),
        ];
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    public function fail(int $status, string $message, ?EncounterCapture $capture = null, array $extra = []): array
    {
        $out = array_merge([
            '__statusCode' => $status,
            'success' => false,
            'message' => $message,
        ], $extra);
        if ($capture !== null) {
            $out['capture'] = $this->toApiArray($capture, false);
        }

        return $out;
    }

    /**
     * Serializa captura para API.
     *
     * - `$includeAnalysis = false` (listar): meta + transcript + `has_analysis`.
     * - `$includeAnalysis = true` (ver / analizar / guardar): `capture_review` +
     *   `datosExtraidos` una sola vez (sin reenviar el snapshot crudo del analizar).
     *
     * @return array<string, mixed>
     */
    public function toApiArray(EncounterCapture $capture, bool $includeAnalysis = false): array
    {
        $extraidosStored = $capture->getDatosExtraidos();
        $analysisStored = $capture->getAnalysisResponse();
        $hasAnalysis = $analysisStored !== [] || $extraidosStored !== [];

        $out = [
            'id' => (int) $capture->id,
            'client_capture_id' => $capture->client_capture_id,
            'subject_persona_id' => (int) $capture->subject_persona_id,
            'parent' => $capture->parent_type,
            'parent_id' => $capture->parent_id !== null ? (int) $capture->parent_id : null,
            'encounter_id' => $capture->encounter_id !== null ? (int) $capture->encounter_id : null,
            'stage' => $capture->stage,
            'has_audio' => $capture->hasAudio(),
            'has_analysis' => $hasAnalysis,
            'transcript' => $capture->transcript,
            'texto_procesado' => $capture->texto_procesado,
            'stt' => $capture->getSttMeta(),
            'staged_item_ids' => $capture->getStagedItemIds(),
            'analysis_cache_token' => $capture->analysis_cache_token,
            'last_error' => $capture->last_error,
            'attempts_stt' => (int) $capture->attempts_stt,
            'attempts_analysis' => (int) $capture->attempts_analysis,
            'attempts_save' => (int) $capture->attempts_save,
            'created_at' => $capture->created_at,
            'updated_at' => $capture->updated_at,
        ];

        if (!$includeAnalysis) {
            return $out;
        }

        $extraidos = $extraidosStored;
        if ($extraidos === [] && $analysisStored !== []) {
            $extraidos = $this->extractDatosExtraidosFromAnalizar($analysisStored);
        }

        $review = $analysisStored['capture_review'] ?? null;
        if (!is_array($review)) {
            $review = null;
        }

        if ($review !== null) {
            // Issues/completitud frescos desde el contrato de dominio (no el blob cacheado del analizar).
            $categorias = $this->resolveCategoriasForCapture($capture, []);
            if ($extraidos !== [] && $categorias !== []) {
                $refined = ClinicalCaptureRowContracts::refineDerivaciones($extraidos, $categorias);
                if ($refined !== $extraidos) {
                    $extraidos = $refined;
                    $textoOriginal = trim((string) ($analysisStored['texto_original'] ?? $capture->transcript ?? ''));
                    $textoProcesado = isset($analysisStored['texto_procesado'])
                        ? (string) $analysisStored['texto_procesado']
                        : ($capture->texto_procesado !== null ? (string) $capture->texto_procesado : null);
                    $review = (new EncounterCaptureReviewPresenter())->build(
                        $extraidos,
                        $categorias,
                        $textoOriginal,
                        $textoProcesado,
                        false
                    );
                } else {
                    $review = EncounterCaptureReviewPresenter::withFreshCompleteness(
                        $review,
                        $extraidos,
                        $categorias
                    );
                }
            }
            $review = EncounterCaptureReviewPresenter::slimCaptureReviewForApi($review);
            // open_problems fresco: solo previos, no diagnósticos/planes de esta captura.
            try {
                $categoriasForOps = $categorias !== []
                    ? $categorias
                    : $this->resolveCategoriasForCapture($capture, []);
                $openProblems = (new EncounterOpenProblemsService())->forCaptureReview(
                    (int) $capture->subject_persona_id,
                    is_array($extraidos) ? $extraidos : [],
                    $categoriasForOps,
                    $capture->encounter_id !== null ? (int) $capture->encounter_id : null
                );
                $hasOps = ($openProblems['conditions'] ?? []) !== []
                    || ($openProblems['care_plans'] ?? []) !== [];
                if ($hasOps) {
                    $review['open_problems'] = $openProblems;
                } else {
                    unset($review['open_problems']);
                }
            } catch (\Throwable $e) {
                unset($review['open_problems']);
            }
            $review = $this->applyEpisodeDedupToReview(
                $capture,
                $review,
                trim((string) ($analysisStored['texto_original'] ?? $capture->transcript ?? ''))
            );
            $out['capture_review'] = $review;
        } elseif ($extraidos !== []) {
            // Sin review: solo entonces exponer extracción cruda.
            $out['datosExtraidos'] = $extraidos;
        }

        // id_configuracion / id_consulta: necesarios para guardar; no duplicar textos/flags del review.
        if (array_key_exists('id_configuracion', $analysisStored) && !array_key_exists('id_configuracion', $out)) {
            $out['id_configuracion'] = $analysisStored['id_configuracion'];
        }
        if (array_key_exists('id_consulta', $analysisStored) && !array_key_exists('id_consulta', $out)) {
            $out['id_consulta'] = $analysisStored['id_consulta'];
        }
        if (isset($analysisStored['encounter_id']) && empty($out['encounter_id'])) {
            $out['encounter_id'] = (int) $analysisStored['encounter_id'];
        }
        // texto_procesado solo si aporta algo distinto al transcript.
        $tp = trim((string) ($out['texto_procesado'] ?? ''));
        $tr = trim((string) ($out['transcript'] ?? ''));
        if ($tp === '' || $tp === $tr) {
            unset($out['texto_procesado']);
        }

        return $out;
    }

    /**
     * El analizar legacy expone extracción en datos.datosExtraidos (no top-level).
     *
     * @param array<string, mixed> $out
     * @return array<string, mixed>
     */
    public function extractDatosExtraidosFromAnalizar(array $out): array
    {
        $direct = $out['datosExtraidos'] ?? $out['datos_extraidos'] ?? null;
        if (is_array($direct) && $direct !== []) {
            return $direct;
        }
        $datos = $out['datos'] ?? null;
        if (!is_array($datos)) {
            return [];
        }
        $nested = $datos['datosExtraidos'] ?? null;
        if (is_array($nested)) {
            return $nested;
        }
        if (is_string($nested) && $nested !== '') {
            $decoded = json_decode($nested, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * @param array<string, mixed> $review
     * @return array<string, mixed>
     */
    public function applyEpisodeDedupToReview(
        EncounterCapture $capture,
        array $review,
        string $texto
    ): array {
        $parent = strtoupper(trim((string) ($capture->parent ?? '')));
        $parentId = (int) ($capture->parent_id ?? 0);
        $subjectId = (int) ($capture->subject_persona_id ?? 0);
        if ($parentId <= 0 || $subjectId <= 0) {
            return $review;
        }
        $dedupSvc = new EpisodeCaptureDedupService();
        $dedup = $dedupSvc->analyze(
            $parent,
            $parentId,
            $subjectId,
            $texto,
            is_array($review['categories'] ?? null) ? $review['categories'] : []
        );

        return $dedupSvc->applyToReview($review, $dedup);
    }

}
