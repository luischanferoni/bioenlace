<?php

namespace common\components\Domain\Integrations\Scheduling\Service;

use common\components\Domain\Integrations\Scheduling\FhirSchedulingConnectorRegistry;
use common\components\Domain\Integrations\Scheduling\Mapper\FhirAppointmentStatusMapper;
use common\models\Scheduling\Turno;
use Yii;

/**
 * Publica cambios de estado de turnos espejo FHIR hacia HAPI NIS.
 */
final class FhirAppointmentOutboundSyncService
{
    public function isOutboundEnabled(): bool
    {
        $config = Yii::$app->params['fhirSchedulingInbound'] ?? [];
        if (empty($config['enabled'])) {
            return false;
        }

        return !empty($config['outbound']['enabled']);
    }

    /**
     * @return bool true si se publicó o ya estaba alineado; false si no aplica
     */
    public function syncFromTurno(Turno $turno): bool
    {
        if (!$this->isOutboundEnabled()) {
            return false;
        }

        if (!FhirAppointmentStatusMapper::turnoNeedsOutboundSync($turno)) {
            return false;
        }

        $externalId = trim((string) $turno->external_appointment_id);
        $source = trim((string) $turno->appointment_source_system);
        $targetStatus = FhirAppointmentStatusMapper::mapTurnoEstadoToFhir(
            (string) $turno->estado,
            $turno->estado_motivo !== null ? (string) $turno->estado_motivo : null
        );
        if ($targetStatus === null) {
            return false;
        }

        $connector = FhirSchedulingConnectorRegistry::get($source);
        $connector->updateAppointmentStatus($externalId, $targetStatus);

        $turno->fhir_status = $targetStatus;
        $turno->usuario_mod = 'fhir-outbound';
        $turno->fecha_mod = date('Y-m-d H:i:s');
        $turno->save(false);

        Yii::info([
            'source' => $source,
            'external_appointment_id' => $externalId,
            'id_turnos' => (int) $turno->id_turnos,
            'fhir_status' => $targetStatus,
        ], 'fhir-scheduling-outbound');

        return true;
    }

    /**
     * Reintenta turnos espejo cuyo fhir_status no coincide con el estado interno.
     *
     * @return array{pushed: int, skipped: int, errors: int}
     */
    public function pushPending(int $limit = 50): array
    {
        if (!$this->isOutboundEnabled()) {
            return ['pushed' => 0, 'skipped' => 0, 'errors' => 0];
        }

        $limit = max(1, min(500, $limit));
        $turnos = Turno::find()
            ->where(['not', ['external_appointment_id' => null]])
            ->andWhere(['not', ['appointment_source_system' => null]])
            ->andWhere(['not', ['external_appointment_id' => '']])
            ->orderBy(['fecha_mod' => SORT_ASC])
            ->limit($limit * 3)
            ->all();

        $pushed = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($turnos as $turno) {
            if ($pushed + $skipped + $errors >= $limit) {
                break;
            }
            if (!FhirAppointmentStatusMapper::turnoNeedsOutboundSync($turno)) {
                $skipped++;
                continue;
            }
            try {
                if ($this->syncFromTurno($turno)) {
                    $pushed++;
                } else {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                $errors++;
                Yii::error([
                    'id_turnos' => (int) $turno->id_turnos,
                    'external_appointment_id' => $turno->external_appointment_id,
                    'error' => $e->getMessage(),
                ], 'fhir-scheduling-outbound');
            }
        }

        return ['pushed' => $pushed, 'skipped' => $skipped, 'errors' => $errors];
    }
}
