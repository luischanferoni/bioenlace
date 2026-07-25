<?php

namespace common\components\Domain\Clinical\Service;

use common\components\Domain\Clinical\CarePlan\Reminder\ActivityReminderTimingParser;
use common\components\Domain\Clinical\Service\ReferralRequestService;
use common\components\Domain\Clinical\CarePlan\Reminder\ReminderTimingJsonBuilder;
use common\components\Domain\Clinical\Enum\RequestStatus;
use common\components\Domain\Terminology\Snomed\SnomedCodeSystem;
use common\models\Clinical\CarePlan;
use common\models\Clinical\Encounter;
use common\models\Clinical\Input\IndicacionInput;
use common\models\Clinical\Input\PracticaInput;
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

        if (is_string($row)) {
            $row = $legacyModelo === 'ConsultaIndicaciones'
                ? ['Indicacion' => trim($row)]
                : ['Practica' => trim($row)];
        }
        if (!is_array($row)) {
            throw new \InvalidArgumentException('Fila de práctica/indicación inválida.');
        }

        if ($legacyModelo === 'ConsultaIndicaciones') {
            return $this->createIndicacionFromExtractedRow($encounter, $row, $carePlan);
        }
        if ($legacyModelo === 'ConsultaPracticas') {
            return $this->createPracticaFromExtractedRow($encounter, $row, $carePlan);
        }

        $campos = self::promptFieldsForModelo($legacyModelo);
        $primaryKey = $campos[0] ?? 'Practica';
        $secondaryKey = $campos[1] ?? null;
        $codigoKey = null;
        foreach ($campos as $campo) {
            if (mb_stripos($campo, 'codigo') !== false || mb_stripos($campo, 'código') !== false) {
                $codigoKey = $campo;
                break;
            }
        }

        $primary = trim((string) (
            $row[$primaryKey]
            ?? $row['termino']
            ?? $row['texto']
            ?? $row['display']
            ?? $row['Indicacion']
            ?? $row['Practica']
            ?? ''
        ));
        $secondary = $secondaryKey !== null
            ? trim((string) ($row[$secondaryKey] ?? $row['Resultado'] ?? $row['Plazo dias'] ?? ''))
            : '';
        $code = trim((string) (
            ($codigoKey !== null ? ($row[$codigoKey] ?? '') : '')
            ?: ($row['codigo'] ?? $row['conceptId'] ?? $row['Codigo'] ?? '')
        ));

        $display = $primary;
        if ($secondary !== '') {
            $display = trim($primary . ' ' . $secondary);
        }
        if ($display === '' && $code === '') {
            throw new \InvalidArgumentException('Fila sin práctica/indicación ni código.');
        }

        $sr = new ServiceRequest();
        $sr->encounter_id = $encounter->id;
        $sr->subject_persona_id = $encounter->subject_persona_id;
        $sr->status = RequestStatus::ACTIVE;
        $sr->intent = 'order';
        $sr->category = 'observation';
        $sr->code = $code !== '' ? $code : null;
        $sr->code_system = $code !== '' ? SnomedCodeSystem::URI : null;
        $sr->display = $display !== '' ? $display : $code;
        if ($secondary !== '') {
            $sr->note = $secondary;
        }
        $sr->id_profesional_efector_servicio = $encounter->id_profesional_efector_servicio;
        if ($carePlan !== null) {
            $sr->care_plan_id = $carePlan->id;
        }
        if (!$sr->save()) {
            throw new \RuntimeException('ServiceRequest: ' . json_encode($sr->getErrors()));
        }
        if ($carePlan !== null) {
            $this->carePlans->addServiceRequestActivity($carePlan, $sr);
        }

        return $sr;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function createIndicacionFromExtractedRow(
        Encounter $encounter,
        array $row,
        ?CarePlan $carePlan = null
    ): ServiceRequest {
        $input = IndicacionInput::fromExtractedRow($row);
        if (!$input->validate()) {
            $msgs = [];
            foreach ($input->getFirstErrors() as $msg) {
                $msgs[] = (string) $msg;
            }
            throw new \InvalidArgumentException(
                $msgs !== [] ? implode(' ', $msgs) : 'Indicación incompleta.'
            );
        }

        $sr = new ServiceRequest();
        $sr->encounter_id = $encounter->id;
        $sr->subject_persona_id = $encounter->subject_persona_id;
        $sr->status = RequestStatus::ACTIVE;
        $sr->intent = 'order';
        $sr->category = $input->categoryForServiceRequest();
        $sr->display = (string) $input->indicacion;
        if ($input->plazoDias !== null) {
            $sr->reminder_json = json_encode([
                'delay_days' => $input->plazoDias,
                'kind' => 'control',
            ], JSON_UNESCAPED_UNICODE);
        }
        $sr->id_profesional_efector_servicio = $encounter->id_profesional_efector_servicio;
        if ($carePlan !== null) {
            $sr->care_plan_id = $carePlan->id;
        }
        if (!$sr->save()) {
            throw new \RuntimeException('ServiceRequest: ' . json_encode($sr->getErrors()));
        }
        if ($carePlan !== null) {
            $this->carePlans->addServiceRequestActivity($carePlan, $sr);
        }

        return $sr;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function createPracticaFromExtractedRow(
        Encounter $encounter,
        array $row,
        ?CarePlan $carePlan = null
    ): ServiceRequest {
        $input = PracticaInput::fromExtractedRow($row);
        if (!$input->validate()) {
            $msgs = [];
            foreach ($input->getFirstErrors() as $msg) {
                $msgs[] = (string) $msg;
            }
            throw new \InvalidArgumentException(
                $msgs !== [] ? implode(' ', $msgs) : 'Práctica incompleta.'
            );
        }

        $display = trim((string) $input->practica);
        $resultado = trim((string) ($input->resultado ?? ''));
        $code = trim((string) ($input->codigo ?? ''));
        if ($resultado !== '') {
            $display = trim($display . ' ' . $resultado);
        }

        $sr = new ServiceRequest();
        $sr->encounter_id = $encounter->id;
        $sr->subject_persona_id = $encounter->subject_persona_id;
        $sr->status = RequestStatus::ACTIVE;
        $sr->intent = 'order';
        $sr->category = 'observation';
        $sr->code = $code !== '' ? $code : null;
        $sr->code_system = $code !== '' ? SnomedCodeSystem::URI : null;
        $sr->display = $display !== '' ? $display : $code;
        if ($resultado !== '') {
            $sr->note = $resultado;
        }
        $sr->id_profesional_efector_servicio = $encounter->id_profesional_efector_servicio;
        if ($carePlan !== null) {
            $sr->care_plan_id = $carePlan->id;
        }
        if (!$sr->save()) {
            throw new \RuntimeException('ServiceRequest: ' . json_encode($sr->getErrors()));
        }
        if ($carePlan !== null) {
            $this->carePlans->addServiceRequestActivity($carePlan, $sr);
        }

        return $sr;
    }

    /**
     * @return list<string>
     */
    private static function promptFieldsForModelo(string $legacyModelo): array
    {
        if ($legacyModelo === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $legacyModelo)) {
            return [];
        }
        $class = '\\common\\models\\' . $legacyModelo;
        if (!class_exists($class)) {
            return [];
        }
        $model = new $class();
        if (!method_exists($model, 'requeridosPrompt')) {
            return [];
        }
        $campos = $model->requeridosPrompt();

        return is_array($campos) ? array_values(array_map('strval', $campos)) : [];
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function resolvePlazoDias(array $row, ?string $plazoKey = null): ?int
    {
        $candidates = [];
        if ($plazoKey !== null && $plazoKey !== '') {
            $candidates[] = $plazoKey;
        }
        $candidates = array_merge($candidates, ['Plazo dias', 'plazo_dias', 'delay_days', 'dias']);
        foreach ($candidates as $key) {
            if (!array_key_exists($key, $row)) {
                continue;
            }
            if (preg_match('/(\d+)/', (string) $row[$key], $m)) {
                $n = (int) $m[1];
                if ($n > 0) {
                    return $n;
                }
            }
        }

        return null;
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
        $sr->code_system = trim((string) ($body['code_system'] ?? ''))
            ?: ($sr->code !== null && trim($sr->code) !== '' ? SnomedCodeSystem::URI : null);
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
