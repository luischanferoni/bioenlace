<?php

namespace common\components\Clinical\Service;

use common\components\Clinical\Enum\EncounterStatus;
use common\models\Clinical\Encounter;
use common\models\Persona;
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

        return $encounter;
    }

    public function resolveSubjectPersonaId(array $body): ?int
    {
        if (!empty($body['id_persona'])) {
            return (int) $body['id_persona'];
        }
        if (!empty($body['subject_persona_id'])) {
            return (int) $body['subject_persona_id'];
        }
        $idPersona = Yii::$app->user->getIdPersona();
        if ($idPersona) {
            return (int) $idPersona;
        }

        return null;
    }

    public function findSubject(int $idPersona): ?Persona
    {
        return Persona::findOne(['id_persona' => $idPersona]);
    }
}
