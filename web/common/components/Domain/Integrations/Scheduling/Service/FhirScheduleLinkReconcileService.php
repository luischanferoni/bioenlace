<?php

namespace common\components\Domain\Integrations\Scheduling\Service;

use common\components\Domain\Integrations\Scheduling\FhirScheduleActorExtractor;
use common\components\Domain\Integrations\Scheduling\FhirSchedulePesResolver;
use common\components\Domain\Integrations\Scheduling\FhirSchedulingConnectorRegistry;
use common\models\Integration\IntegrationScheduleLink;
use Yii;

/**
 * Marca vínculos verificados como stale si los actores FHIR divergen.
 */
final class FhirScheduleLinkReconcileService
{
    public function reconcile(?string $connectorKey = null, int $limit = 100): int
    {
        $config = Yii::$app->params['fhirSchedulingInbound'] ?? [];
        if (empty($config['enabled'])) {
            return 0;
        }

        $connector = FhirSchedulingConnectorRegistry::get($connectorKey);
        $source = $connector->getConnectorKey();
        $resolver = new FhirSchedulePesResolver();
        $extractor = new FhirScheduleActorExtractor();
        $stale = 0;

        $links = IntegrationScheduleLink::find()
            ->where(['source_system' => $source, 'status' => IntegrationScheduleLink::STATUS_VERIFIED])
            ->limit($limit)
            ->all();

        foreach ($links as $link) {
            try {
                $bundle = $connector->readSchedule((string) $link->external_schedule_id, ['Schedule:actor']);
                $actors = $extractor->extractFromBundle($bundle);
                $fp = $resolver->fingerprint($actors);
                if ($link->actor_fingerprint !== null && $link->actor_fingerprint !== '' && $link->actor_fingerprint !== $fp) {
                    $link->status = IntegrationScheduleLink::STATUS_STALE;
                    $link->updated_at = gmdate('Y-m-d H:i:s');
                    $link->save(false);
                    $stale++;
                }
            } catch (\Throwable $e) {
                Yii::warning('Reconcile schedule ' . $link->external_schedule_id . ': ' . $e->getMessage(), 'fhir-scheduling-inbound');
            }
        }

        return $stale;
    }
}
