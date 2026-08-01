<?php

namespace common\components\Domain\Clinical\Service;

use common\models\Clinical\EncounterCapture;
use common\models\Clinical\EncounterCaptureAudit;
use Yii;

/**
 * Escritura del trail de auditoría de captura clínica.
 * Lectura admin: {@see EncounterCaptureAuditQueryService}.
 */
final class EncounterCaptureAuditService
{
    /**
     * @param array<string, mixed>|null $meta
     */
    public function record(
        EncounterCapture $capture,
        string $eventType,
        ?array $meta = null,
        ?int $actorUserId = null
    ): void {
        $captureId = (int) ($capture->id ?? 0);
        if ($captureId <= 0) {
            return;
        }
        if (!in_array($eventType, EncounterCaptureAudit::eventTypeValues(), true)) {
            Yii::warning('EncounterCaptureAudit: event_type inválido ' . $eventType, __METHOD__);

            return;
        }

        if ($actorUserId === null) {
            $actorUserId = (int) (Yii::$app->user->id ?? 0);
            if ($actorUserId <= 0) {
                $actorUserId = (int) ($capture->created_by_user_id ?? 0) ?: null;
            }
        }

        $encounterId = $capture->encounter_id !== null ? (int) $capture->encounter_id : null;
        if ($encounterId !== null && $encounterId <= 0) {
            $encounterId = null;
        }

        try {
            EncounterCaptureAudit::registrar(
                $captureId,
                $eventType,
                $meta,
                $actorUserId > 0 ? $actorUserId : null,
                $encounterId
            );
        } catch (\Throwable $e) {
            Yii::error(
                'EncounterCaptureAudit falló al registrar: ' . $e->getMessage(),
                __METHOD__
            );
        }
    }

    /**
     * Conteos clinical/ai e issues a partir de capture_review.
     *
     * @param array<string, mixed> $captureReview
     * @return array<string, mixed>
     */
    public static function buildAnalyzedMeta(array $captureReview): array
    {
        $clinical = 0;
        $ai = 0;
        foreach ($captureReview['categories'] ?? [] as $category) {
            if (!is_array($category)) {
                continue;
            }
            foreach ($category['items'] ?? [] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                if (($item['source'] ?? 'clinical') === 'ai') {
                    $ai++;
                } else {
                    $clinical++;
                }
            }
        }

        $issues = $captureReview['issues'] ?? [];
        $issuesCount = is_array($issues) ? count($issues) : 0;

        return [
            'item_counts' => [
                'clinical' => $clinical,
                'ai' => $ai,
            ],
            'issues_count' => $issuesCount,
            'puede_confirmar' => ($captureReview['puede_confirmar'] ?? false) === true,
            'default_staged_count' => is_array($captureReview['default_staged_item_ids'] ?? null)
                ? count($captureReview['default_staged_item_ids'])
                : 0,
        ];
    }

    /**
     * Diff default_staged vs staged final + source clinical|ai.
     *
     * @param array<string, mixed> $captureReview
     * @param list<string> $finalStagedIds
     * @param array<string, mixed>|null $resolutions
     * @return array<string, mixed>
     */
    public static function buildAcceptanceMeta(
        array $captureReview,
        array $finalStagedIds,
        ?array $resolutions = null
    ): array {
        $itemsById = [];
        foreach ($captureReview['categories'] ?? [] as $category) {
            if (!is_array($category)) {
                continue;
            }
            $title = trim((string) ($category['title'] ?? ''));
            foreach ($category['items'] ?? [] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $id = trim((string) ($item['id'] ?? ''));
                if ($id === '') {
                    continue;
                }
                $itemsById[$id] = [
                    'source' => (($item['source'] ?? 'clinical') === 'ai') ? 'ai' : 'clinical',
                    'category' => $title !== '' ? $title : 'sin_categoria',
                ];
            }
        }

        $defaultStaged = [];
        foreach ($captureReview['default_staged_item_ids'] ?? [] as $id) {
            $defaultStaged[] = (string) $id;
        }
        $defaultSet = array_fill_keys($defaultStaged, true);

        $finalNormalized = [];
        foreach ($finalStagedIds as $id) {
            $finalNormalized[] = (string) $id;
        }
        $finalSet = array_fill_keys($finalNormalized, true);

        $aiAccepted = [];
        $aiRejected = [];
        $clinicalDeselected = [];
        $countsByCategory = [];

        foreach ($itemsById as $id => $info) {
            $category = $info['category'];
            if (!isset($countsByCategory[$category])) {
                $countsByCategory[$category] = [
                    'ai_accepted' => 0,
                    'ai_rejected' => 0,
                    'clinical_deselected' => 0,
                    'clinical_kept' => 0,
                ];
            }
            $staged = isset($finalSet[$id]);
            if ($info['source'] === 'ai') {
                if ($staged) {
                    $aiAccepted[] = $id;
                    $countsByCategory[$category]['ai_accepted']++;
                } else {
                    $aiRejected[] = $id;
                    $countsByCategory[$category]['ai_rejected']++;
                }
            } elseif (isset($defaultSet[$id]) && !$staged) {
                $clinicalDeselected[] = $id;
                $countsByCategory[$category]['clinical_deselected']++;
            } elseif (isset($defaultSet[$id]) && $staged) {
                $countsByCategory[$category]['clinical_kept']++;
            }
        }

        $meta = [
            'ai_accepted_ids' => $aiAccepted,
            'ai_rejected_ids' => $aiRejected,
            'clinical_deselected_ids' => $clinicalDeselected,
            'final_staged_count' => count($finalNormalized),
            'default_staged_count' => count($defaultStaged),
            'counts_by_category' => $countsByCategory,
            'summary' => [
                'ai_accepted' => count($aiAccepted),
                'ai_rejected' => count($aiRejected),
                'clinical_deselected' => count($clinicalDeselected),
            ],
        ];

        if (is_array($resolutions) && $resolutions !== []) {
            $meta['resolutions'] = $resolutions;
        }

        return $meta;
    }
}
