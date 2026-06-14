<?php

namespace common\components\Domain\Clinical\Service;

use common\components\Domain\Clinical\CarePlan\Reminder\ActivityReminderTimingParser;
use common\components\Domain\Clinical\CarePlan\Reminder\ReminderTimingJsonBuilder;
use common\components\Domain\Clinical\Enum\RequestStatus;
use common\models\Clinical\CarePlan;
use common\models\Clinical\Encounter;
use common\models\Clinical\MedicationRequest;

final class MedicationRequestService
{
    private CarePlanService $carePlans;

    public function __construct(?CarePlanService $carePlans = null)
    {
        $this->carePlans = $carePlans ?? new CarePlanService();
    }

    /**
     * @return MedicationRequest[]
     */
    public function listForEncounter(int $encounterId): array
    {
        return MedicationRequest::find()
            ->where(['encounter_id' => $encounterId])
            ->andWhere(['deleted_at' => null])
            ->orderBy(['id' => SORT_ASC])
            ->all();
    }

    /**
     * Fila extraída por IA (modelo legacy ConsultaMedicamentos en JSON).
     *
     * @param array<string, mixed> $row
     */
    public function createFromExtractedRow(Encounter $encounter, CarePlan $carePlan, array $row): MedicationRequest
    {
        $mr = new MedicationRequest();
        $mr->encounter_id = $encounter->id;
        $mr->subject_persona_id = $encounter->subject_persona_id;
        $mr->care_plan_id = $carePlan->id;
        $mr->status = RequestStatus::ACTIVE;
        $mr->intent = 'order';
        $mr->medication_code = (string) (
            $row['id_snomed_medicamento'] ?? $row['snomed_code'] ?? $row['conceptId'] ?? ''
        );
        $mr->medication_display = $row['Nombre del medicamento'] ?? $row['termino'] ?? $row['medicamento'] ?? null;
        $parts = array_filter([
            isset($row['cantidad']) ? 'cant: ' . $row['cantidad'] : null,
            $row['Frecuencia de administracion'] ?? $row['frecuencia'] ?? null,
            $row['Duracion del tratamiento'] ?? $row['durante'] ?? null,
            $row['indicaciones'] ?? null,
        ]);
        $mr->dosage_text = $parts ? implode('; ', $parts) : null;
        $mr->authored_on = date('Y-m-d H:i:s');
        $mr->id_profesional_efector_servicio = $encounter->id_profesional_efector_servicio;
        if (!$mr->save()) {
            throw new \RuntimeException('MedicationRequest: ' . json_encode($mr->getErrors()));
        }
        $this->carePlans->addMedicationActivity($carePlan, $mr);

        return $mr;
    }

    /**
     * @param array<string, mixed> $body
     */
    public function createFromApi(Encounter $encounter, ?CarePlan $carePlan, array $body): MedicationRequest
    {
        $mr = new MedicationRequest();
        $mr->encounter_id = $encounter->id;
        $mr->subject_persona_id = $encounter->subject_persona_id;
        $mr->care_plan_id = $carePlan?->id ?? ($body['care_plan_id'] ?? null);
        $mr->status = (string) ($body['status'] ?? RequestStatus::ACTIVE);
        $mr->intent = (string) ($body['intent'] ?? 'order');
        $mr->medication_code = isset($body['medication_code']) ? (string) $body['medication_code'] : null;
        $mr->medication_display = $body['medication_display'] ?? $body['display'] ?? null;
        $mr->dosage_text = $body['dosage_text'] ?? null;
        $mr->dosage_json = $this->resolveDosageJson($body);
        $mr->authored_on = date('Y-m-d H:i:s');
        $mr->id_profesional_efector_servicio = $encounter->id_profesional_efector_servicio;
        if (!$mr->save()) {
            throw new \InvalidArgumentException('MedicationRequest: ' . json_encode($mr->getErrors()));
        }
        if ($carePlan !== null) {
            $this->carePlans->addMedicationActivity($carePlan, $mr);
        }

        return $mr;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function resolveDosageJson(array $body): ?string
    {
        if (isset($body['dosage_json'])) {
            if (is_string($body['dosage_json'])) {
                $json = trim($body['dosage_json']) !== '' ? $body['dosage_json'] : null;
            } elseif (is_array($body['dosage_json'])) {
                $json = json_encode($body['dosage_json'], JSON_UNESCAPED_UNICODE);
            } else {
                $json = null;
            }
        } else {
            $json = (new ReminderTimingJsonBuilder())->fromRequestBody($body);
        }

        if ($json === null) {
            return null;
        }

        if ((new ActivityReminderTimingParser())->parse($json) === null) {
            throw new \InvalidArgumentException(
                'dosage_json.timing inválido: use timeOfDay en formato HH:MM (ej. ["08:00","20:00"]).'
            );
        }

        return $json;
    }
}
