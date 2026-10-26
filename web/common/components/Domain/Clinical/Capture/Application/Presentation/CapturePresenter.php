<?php

namespace common\components\Domain\Clinical\Capture\Application\Presentation;

use common\components\Domain\Clinical\Capture\Application\Service\CaptureCategoryResolver;
use common\components\Domain\Clinical\Capture\Application\Service\RowContractService;
use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCaptureStage;
use common\components\Domain\Clinical\Encounter\Application\Service\EncounterOpenProblemsService;
use common\components\Domain\Clinical\Encounter\Application\Service\EpisodeCaptureDedupService;
use common\components\Platform\Core\Http\Contract\ApiException;
use common\components\Domain\Clinical\Encounter\Application\Presentation\EncounterCaptureReviewPresenter;
use common\models\Clinical\EncounterCapture;
use common\models\Clinical\EncounterDefinition;

/**
 * Forma el payload del checkpoint (ok / fail / toApiArray) para que el controller HTTP lo serialice.
 * No es controller ni vista Yii — rol CA Presentation (*Presenter).
 */
final class CapturePresenter
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
            ? (new CaptureCategoryResolver())->resolve($def, $body)
            : [];
    }

    /**
     * Datos de éxito: la captura serializada.
     *
     * @return array<string, mixed>
     */
    public function ok(EncounterCapture $capture, bool $includeAnalysis = false): array
    {
        return ['capture' => $this->toApiArray($capture, $includeAnalysis)];
    }

    /**
     * Error del contrato con el estado de la captura como contexto (`meta.capture`).
     *
     * @param array<string, scalar> $params
     * @param array<string, mixed> $meta
     */
    public function failure(
        string $code,
        ?EncounterCapture $capture = null,
        array $params = [],
        array $meta = []
    ): ApiException {
        if ($capture !== null) {
            $meta['capture'] = $this->toApiArray($capture, false);
        }

        return new ApiException($code, $params, [], null, $meta);
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
            'stage_metadata' => ClinicalCaptureStage::metadata((string) $capture->stage),
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
            $extraidos = $this->extractDatosExtraidos($analysisStored);
        }

        $review = $analysisStored['capture_review'] ?? null;
        if (!is_array($review)) {
            $review = null;
        }

        if ($review !== null) {
            // Issues/completitud frescos desde el contrato de dominio (no el blob cacheado del analizar).
            $categorias = $this->resolveCategoriasForCapture($capture, []);
            if ($extraidos !== [] && $categorias !== []) {
                $refined = RowContractService::refineDerivaciones($extraidos, $categorias);
                if ($refined !== $extraidos) {
                    $extraidos = $refined;
                    $textoProcesado = isset($analysisStored['texto_procesado'])
                        ? (string) $analysisStored['texto_procesado']
                        : ($capture->texto_procesado !== null ? (string) $capture->texto_procesado : null);
                    $review = (new EncounterCaptureReviewPresenter())->build(
                        $extraidos,
                        $categorias,
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

        // IDs necesarios para guardar; no duplicar textos/flags del review.
        if (array_key_exists('id_configuracion', $analysisStored) && !array_key_exists('id_configuracion', $out)) {
            $out['id_configuracion'] = $analysisStored['id_configuracion'];
        }
        $analysisEncounterId = $analysisStored['encounter_id'] ?? null;
        if ($analysisEncounterId !== null && empty($out['encounter_id'])) {
            $out['encounter_id'] = (int) $analysisEncounterId;
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
    public function extractDatosExtraidos(array $out): array
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
