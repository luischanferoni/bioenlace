<?php

namespace common\components\Domain\Integrations\Scheduling\Service;

use common\components\Domain\Integrations\Scheduling\FhirSchedulingConnectorRegistry;
use common\components\Domain\Integrations\Scheduling\Util\FhirBundleHelper;
use common\models\Integration\IntegrationFhirSyncState;
use Yii;

/**
 * Pull incremental Appointment desde HAPI NIS → espejo turnos.
 */
final class FhirSchedulingInboundPullService
{
    public function __construct(
        private ?TurnoInboundSyncService $syncService = null
    ) {
        $this->syncService = $syncService ?? new TurnoInboundSyncService();
    }

    /**
     * @return array{processed: int, created: int, updated: int, errors: int}
     */
    public function pull(?string $connectorKey = null, int $limit = 50): array
    {
        $config = Yii::$app->params['fhirSchedulingInbound'] ?? [];
        if (empty($config['enabled'])) {
            return ['processed' => 0, 'created' => 0, 'updated' => 0, 'errors' => 0];
        }

        $connector = FhirSchedulingConnectorRegistry::get($connectorKey);
        $source = $connector->getConnectorKey();
        $state = IntegrationFhirSyncState::getOrCreate($source);

        $params = ['_count' => $limit, '_sort' => '-_lastUpdated'];
        if ($state->last_cursor !== null && $state->last_cursor !== '') {
            $params['_lastUpdated'] = 'gt' . $state->last_cursor;
        }

        $bundle = $connector->searchAppointments($params);
        $appointments = FhirBundleHelper::collectResources($bundle, 'Appointment');

        $created = 0;
        $updated = 0;
        $errors = 0;
        $latestCursor = $state->last_cursor;

        foreach ($appointments as $appointment) {
            try {
                $result = $this->syncService->upsertFromFhirAppointment($appointment, $source, $connector);
                if ($result['action'] === 'created') {
                    $created++;
                } else {
                    $updated++;
                }
                $metaUpdated = (string) ($appointment['meta']['lastUpdated'] ?? '');
                if ($metaUpdated !== '') {
                    $latestCursor = $this->maxInstant($latestCursor, $metaUpdated);
                }
            } catch (\Throwable $e) {
                $errors++;
                Yii::error([
                    'source' => $source,
                    'appointment_id' => FhirBundleHelper::resourceId($appointment),
                    'error' => $e->getMessage(),
                ], 'fhir-scheduling-inbound');
            }
        }

        $now = gmdate('Y-m-d H:i:s');
        $state->last_success_at = $now;
        if ($latestCursor !== null && $latestCursor !== '') {
            $state->last_cursor = $latestCursor;
        }
        $state->last_error = $errors > 0 ? "{$errors} errores en último pull" : null;
        $state->updated_at = $now;
        $state->save(false);

        return [
            'processed' => count($appointments),
            'created' => $created,
            'updated' => $updated,
            'errors' => $errors,
        ];
    }

    private function maxInstant(?string $current, string $candidate): string
    {
        if ($current === null || $current === '') {
            return $candidate;
        }

        return strtotime($candidate) >= strtotime($current) ? $candidate : $current;
    }
}
