<?php

namespace common\components\Clinical\Service;

use common\components\Clinical\CarePlan\Reminder\ActivityReminderTimingParser;
use common\components\Clinical\Service\ReferralRequestService;
use common\components\Clinical\CarePlan\Reminder\ReminderTimingJsonBuilder;
use common\components\Clinical\Enum\RequestStatus;
use common\models\Clinical\CarePlan;
use common\models\Clinical\Encounter;
use common\models\Clinical\ServiceRequest;

final class ServiceRequestService
{
    private CarePlanService $carePlans;

    public function __construct(?CarePlanService $carePlans = null)
    {
        $this->carePlans = $carePlans ?? new CarePlanService();
    }

    /**
     * @return ServiceRequest[]
     */
    public function listForEncounter(int $encounterId): array
    {
        return ServiceRequest::find()
            ->where(['encounter_id' => $encounterId])
            ->andWhere(['deleted_at' => null])
            ->orderBy(['id' => SORT_ASC])
            ->all();
    }

    /**
     * @param array<string, mixed>|string $row
     */
    public function createFromExtractedRow(
        Encounter $encounter,
        $row,
        string $legacyModelo = 'ConsultaPracticas',
        ?CarePlan $carePlan = null
    ): ServiceRequest {
        if ($legacyModelo === 'ConsultaDerivaciones' && is_array($row)) {
            return ReferralRequestService::createFromExtractedRow($encounter, $row);
        }

        $sr = new ServiceRequest();
        $sr->encounter_id = $encounter->id;
        $sr->subject_persona_id = $encounter->subject_persona_id;
        $sr->status = RequestStatus::ACTIVE;
        $sr->intent = 'order';
        $sr->category = 'procedure';
        if (is_array($row)) {
            $sr->code = (string) ($row['codigo'] ?? $row['conceptId'] ?? '');
            $sr->display = $row['termino'] ?? $row['texto'] ?? null;
        }
        $sr->id_profesional_efector_servicio = $encounter->id_profesional_efector_servicio;
        if (!$sr->save()) {
            throw new \RuntimeException('ServiceRequest: ' . json_encode($sr->getErrors()));
        }
        if ($carePlan !== null) {
            $this->carePlans->addServiceRequestActivity($carePlan, $sr);
        }

        return $sr;
    }

    /**
     * @param array<string, mixed> $body
     */
    public function createFromApi(Encounter $encounter, ?CarePlan $carePlan, array $body): ServiceRequest
    {
        $sr = new ServiceRequest();
        $sr->encounter_id = $encounter->id;
        $sr->subject_persona_id = $encounter->subject_persona_id;
        $sr->care_plan_id = $carePlan?->id ?? ($body['care_plan_id'] ?? null);
        $sr->status = (string) ($body['status'] ?? RequestStatus::ACTIVE);
        $sr->intent = (string) ($body['intent'] ?? 'order');
        $sr->category = (string) ($body['category'] ?? 'procedure');
        $sr->code = isset($body['code']) ? (string) $body['code'] : null;
        $sr->display = $body['display'] ?? null;
        $sr->reminder_json = $this->resolveReminderJson($body);
        $sr->id_profesional_efector_servicio = $encounter->id_profesional_efector_servicio;
        if (!$sr->save()) {
            throw new \InvalidArgumentException('ServiceRequest: ' . json_encode($sr->getErrors()));
        }
        if ($carePlan !== null) {
            $this->carePlans->addServiceRequestActivity($carePlan, $sr);
        }

        return $sr;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function resolveReminderJson(array $body): ?string
    {
        if (isset($body['reminder_json'])) {
            if (is_string($body['reminder_json'])) {
                $json = trim($body['reminder_json']) !== '' ? $body['reminder_json'] : null;
            } elseif (is_array($body['reminder_json'])) {
                $json = json_encode($body['reminder_json'], JSON_UNESCAPED_UNICODE);
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
                'reminder_json.timing inválido: use timeOfDay en formato HH:MM (ej. ["07:00"]).'
            );
        }

        return $json;
    }
}
