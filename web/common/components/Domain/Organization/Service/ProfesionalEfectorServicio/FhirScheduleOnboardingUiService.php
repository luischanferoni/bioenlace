<?php

namespace common\components\Domain\Organization\Service\ProfesionalEfectorServicio;

use common\components\Domain\Integrations\Scheduling\FhirSchedulingConnectorRegistry;
use common\components\Domain\Integrations\Scheduling\Service\IntegrationScheduleLinkService;
use common\components\Domain\Integrations\Scheduling\Util\FhirBundleHelper;
use Yii;

/**
 * UI staff: onboarding Schedule HAPI → PES.
 */
final class FhirScheduleOnboardingUiService
{
    /**
     * @param array<string, mixed> $fromClient
     * @return array<string, mixed>
     */
    public static function buildPreviewValues(int $idEfector, array $fromClient): array
    {
        $scheduleId = trim((string) ($fromClient['external_schedule_id'] ?? ''));
        $source = trim((string) ($fromClient['source_system'] ?? 'msal-nis'));
        $preview = null;
        $error = null;

        if ($scheduleId !== '') {
            try {
                $connector = FhirSchedulingConnectorRegistry::get($source);
                $bundle = $connector->readSchedule($scheduleId, ['Schedule:actor']);
                $preview = (new IntegrationScheduleLinkService())->previewFromScheduleBundle($source, $bundle);
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return [
            'source_system' => $source,
            'external_schedule_id' => $scheduleId,
            'preview' => $preview,
            'preview_error' => $error,
            'id_efector' => $idEfector > 0 ? (string) $idEfector : '',
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array{data: array<string, mixed>}
     */
    public static function submitVerify(int $idEfector, array $post): array
    {
        $scheduleId = trim((string) ($post['external_schedule_id'] ?? ''));
        $source = trim((string) ($post['source_system'] ?? 'msal-nis'));
        $idPes = (int) ($post['id_profesional_efector_servicio'] ?? 0);

        if ($scheduleId === '') {
            throw new \InvalidArgumentException('external_schedule_id es requerido.');
        }
        if ($idPes <= 0) {
            throw new \InvalidArgumentException('id_profesional_efector_servicio es requerido.');
        }

        $connector = FhirSchedulingConnectorRegistry::get($source);
        $bundle = $connector->readSchedule($scheduleId, ['Schedule:actor']);
        $linkService = new IntegrationScheduleLinkService();
        $preview = $linkService->previewFromScheduleBundle($source, $bundle);
        $actors = (new \common\components\Domain\Integrations\Scheduling\FhirScheduleActorExtractor())
            ->extractFromBundle($bundle);

        $userId = Yii::$app->has('user', true) && !Yii::$app->user->isGuest ? (int) Yii::$app->user->id : 0;
        $result = $linkService->verify($source, $scheduleId, $idPes, $actors, $userId);

        return [
            'data' => array_merge($result, ['preview' => $preview]),
        ];
    }

    /**
     * @return array{data: array<string, mixed>}
     */
    public static function listSchedulesFromHapi(int $idEfector, array $fromClient): array
    {
        $source = trim((string) ($fromClient['source_system'] ?? 'msal-nis'));
        $count = min(50, max(1, (int) ($fromClient['_count'] ?? 20)));
        $connector = FhirSchedulingConnectorRegistry::get($source);
        $bundle = $connector->searchSchedules(['_count' => $count]);
        $items = [];
        foreach (FhirBundleHelper::collectResources($bundle, 'Schedule') as $schedule) {
            $items[] = [
                'id' => FhirBundleHelper::resourceId($schedule),
                'label' => (string) ($schedule['id'] ?? ''),
                'meta' => [
                    'active' => $schedule['active'] ?? null,
                ],
            ];
        }

        return ['data' => ['items' => $items, 'total' => (int) ($bundle['total'] ?? count($items))]];
    }
}
