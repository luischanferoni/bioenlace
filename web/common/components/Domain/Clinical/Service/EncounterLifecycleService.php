<?php

namespace common\components\Domain\Clinical\Service;

use common\components\Domain\Clinical\Enum\EncounterStatus;
use common\components\Domain\Clinical\CareCohort\Service\CareEncounterOrchestrator;
use common\components\Domain\Clinical\HistoryExchange\ClinicalHistoryOutboundEnqueueService;
use common\components\Domain\Clinical\PatientSummary\PatientEncounterSummaryPublishService;
use common\components\Domain\Clinical\Workflow\ClinicalOperationalContextResolver;
use common\components\Domain\Integrations\Scheduling\Service\TurnoFhirOutboundNotifier;
use common\models\Clinical\Encounter;
use common\models\Person\Persona;
use common\models\ProfesionalEfectorServicio;
use common\models\Scheduling\Turno;
use common\models\Turno as TurnoAlias;
use Yii;

final class EncounterLifecycleService
{
    /**
     * @param array<string, mixed> $params subject_persona_id, encounter_class, service_id, efector_id, parent_type, parent_id, …
     */
    public function start(array $params): Encounter
    {
        $encounter = new Encounter();
        $encounter->subject_persona_id = (int) $params['subject_persona_id'];
        $encounter->encounter_class = (string) ($params['encounter_class'] ?? Encounter::ENCOUNTER_CLASS_AMB);
        $encounter->status = EncounterStatus::IN_PROGRESS;
        $encounter->period_start = date('Y-m-d H:i:s');
        $encounter->service_id = isset($params['service_id']) ? (int) $params['service_id'] : null;
        $encounter->efector_id = isset($params['efector_id']) ? (int) $params['efector_id'] : null;
        $encounter->appointment_id = isset($params['appointment_id']) ? (int) $params['appointment_id'] : null;
        $encounter->parent_type = $params['parent_type'] ?? null;
        $encounter->parent_id = isset($params['parent_id']) ? (int) $params['parent_id'] : null;
        $encounter->reason_text = $params['reason_text'] ?? null;
        $encounter->note = $params['note'] ?? null;
        $encounter->workflow_step = (int) ($params['workflow_step'] ?? 0);

        $idPes = $params['id_profesional_efector_servicio'] ?? Yii::$app->user->getIdProfesionalEfectorServicio();
        if ($idPes !== null && $idPes !== '') {
            $encounter->id_profesional_efector_servicio = (int) $idPes;
        }

        if (!$encounter->save()) {
            throw new \RuntimeException('No se pudo crear el encounter: ' . json_encode($encounter->getErrors()));
        }

        return $encounter;
    }

    public function finalize(Encounter $encounter): Encounter
    {
        $encounter->status = EncounterStatus::FINISHED;
        $encounter->period_end = date('Y-m-d H:i:s');
        if (!$encounter->save(false, ['status', 'period_end', 'updated_at', 'updated_by'])) {
            throw new \RuntimeException('No se pudo finalizar el encounter.');
        }

        if ($encounter->encounter_class === Encounter::ENCOUNTER_CLASS_AMB) {
            (new PatientEncounterSummaryPublishService())->schedulePublication($encounter);
            (new CareEncounterOrchestrator())->onEncounterFinalized($encounter);
            try {
                (new \common\components\Domain\Clinical\Service\EncounterJourney\EncounterJourneyNotificationScheduler())
                    ->scheduleForEncounter($encounter);
            } catch (\Throwable $e) {
                Yii::warning('EncounterJourney post notifications: ' . $e->getMessage(), 'encounter-journey');
            }
        }

        (new ClinicalHistoryOutboundEnqueueService())->scheduleIfApplicable($encounter);

        return $encounter;
    }

    /**
     * Cierra encounter y aplica reglas CarePlan asociadas.
     *
     * @param array<string, mixed> $carePlanOptions Ver {@see CarePlanLifecycleService::onEncounterClose}
     */
    public function close(Encounter $encounter, array $carePlanOptions = []): Encounter
    {
        $encounter = $this->finalize($encounter);
        (new CarePlanLifecycleService(null, $this))->onEncounterClose($encounter, $carePlanOptions);

        return $encounter;
    }

    /**
     * Tras guardar documentación clínica: finaliza encounter y marca turno atendido si aplica.
     */
    public function onCaptureDocumented(Encounter $encounter): Encounter
    {
        if (trim((string) ($encounter->status ?? '')) !== EncounterStatus::FINISHED) {
            $encounter = $this->finalize($encounter);
        }

        $this->syncAppointmentAttendedFromEncounter($encounter);

        return $encounter;
    }

    private function syncAppointmentAttendedFromEncounter(Encounter $encounter): void
    {
        $turnoId = (int) ($encounter->appointment_id ?? 0);
        if ($turnoId <= 0 && strtoupper(trim((string) ($encounter->parent_type ?? ''))) === Encounter::PARENT_TURNO) {
            $turnoId = (int) ($encounter->parent_id ?? 0);
        }
        if ($turnoId <= 0) {
            return;
        }

        $turno = Turno::findOne($turnoId);
        if ($turno === null) {
            return;
        }

        if ($turno->estado === Turno::ESTADO_ATENDIDO) {
            return;
        }

        $turno->estado = Turno::ESTADO_ATENDIDO;
        $turno->atendido = Turno::ATENDIDO_SI;
        if (!$turno->save(false, ['estado', 'atendido', 'updated_at', 'updated_by'])) {
            Yii::warning(
                'No se pudo marcar turno ' . $turnoId . ' como atendido: ' . json_encode($turno->getErrors()),
                __METHOD__
            );

            return;
        }

        TurnoFhirOutboundNotifier::afterEstadoChangedById($turnoId);
    }

    public function resolveSubjectPersonaId(array $body): ?int
    {
        foreach (['id_persona', 'subject_persona_id'] as $key) {
            if (!array_key_exists($key, $body)) {
                continue;
            }
            $raw = $body[$key];
            if ($raw === null || $raw === '') {
                continue;
            }
            $id = (int) $raw;
            if ($id > 0) {
                return $id;
            }
        }

        $fromParent = ClinicalOperationalContextResolver::resolveSubjectPersonaIdFromParent($body);
        if ($fromParent !== null && $fromParent > 0) {
            return $fromParent;
        }

        $fromEncounter = $this->resolveSubjectPersonaIdFromLinkedEncounter($body);
        if ($fromEncounter !== null && $fromEncounter > 0) {
            return $fromEncounter;
        }

        $idPersona = Yii::$app->user->getIdPersona();
        if ($idPersona) {
            return (int) $idPersona;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function resolveSubjectPersonaIdFromLinkedEncounter(array $body): ?int
    {
        $encounterId = (int) ($body['encounter_id'] ?? $body['id_consulta'] ?? 0);
        if ($encounterId > 0) {
            $encounter = Encounter::findOne($encounterId);
            if ($encounter !== null && (int) $encounter->subject_persona_id > 0) {
                return (int) $encounter->subject_persona_id;
            }
        }

        $parent = strtoupper(trim((string) ($body['parent'] ?? '')));
        $parentId = (int) ($body['parent_id'] ?? 0);
        if ($parent === Encounter::PARENT_TURNO && $parentId > 0) {
            $encounter = Encounter::find()
                ->where(['appointment_id' => $parentId, 'deleted_at' => null])
                ->orderBy(['id' => SORT_DESC])
                ->one();
            if ($encounter !== null && (int) $encounter->subject_persona_id > 0) {
                return (int) $encounter->subject_persona_id;
            }
        }

        return null;
    }

    public function findSubject(int $idPersona): ?Persona
    {
        return Persona::findOne(['id_persona' => $idPersona]);
    }

    /**
     * Encounter ambulatorio vinculado a un turno (motivos pre-consulta). Idempotente.
     */
    public function ensureFromTurno(Turno|TurnoAlias $turno): ?Encounter
    {
        $turnoId = (int) $turno->id_turnos;
        if ($turnoId <= 0) {
            return null;
        }

        $existing = Encounter::find()
            ->where(['appointment_id' => $turnoId])
            ->andWhere(['deleted_at' => null])
            ->orderBy(['id' => SORT_DESC])
            ->one();
        if ($existing instanceof Encounter) {
            $pesTurno = (int) ($turno->id_profesional_efector_servicio ?? 0);
            if ($pesTurno > 0 && (int) ($existing->id_profesional_efector_servicio ?? 0) <= 0) {
                $existing->updateAttributes(['id_profesional_efector_servicio' => $pesTurno]);
            }

            return $existing;
        }

        $efectorId = (int) ($turno->getAttribute('id_efector') ?: 0);
        $serviceId = (int) ($turno->id_servicio_asignado ?: $turno->getAttribute('id_servicio') ?: 0);
        if ($efectorId <= 0 && (int) $turno->id_profesional_efector_servicio > 0) {
            $pes = ProfesionalEfectorServicio::findOne([
                'id' => (int) $turno->id_profesional_efector_servicio,
                'deleted_at' => null,
            ]);
            if ($pes !== null) {
                $efectorId = (int) $pes->id_efector;
            }
        }
        if ($efectorId <= 0 || $serviceId <= 0 || (int) $turno->id_persona <= 0) {
            return null;
        }

        $encounter = $this->start([
            'subject_persona_id' => (int) $turno->id_persona,
            'encounter_class' => Encounter::ENCOUNTER_CLASS_AMB,
            'service_id' => $serviceId,
            'efector_id' => $efectorId,
            'appointment_id' => $turnoId,
            'parent_type' => Encounter::PARENT_TURNO,
            'parent_id' => $turnoId,
            'id_profesional_efector_servicio' => (int) $turno->id_profesional_efector_servicio ?: null,
        ]);

        (new CareEncounterOrchestrator())->onEncounterEnsured($encounter);

        return $encounter;
    }
}
