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
        $row = self::normalizeExtractedMedicationRow($row);
        $display = self::resolveMedicationDisplay($row);
        if ($display === '') {
            throw new \InvalidArgumentException('Fila de medicación sin nombre/display recognizable.');
        }

        $mr = new MedicationRequest();
        $mr->encounter_id = $encounter->id;
        $mr->subject_persona_id = $encounter->subject_persona_id;
        $mr->care_plan_id = $carePlan->id;
        $mr->status = RequestStatus::ACTIVE;
        $mr->intent = 'order';
        $code = trim((string) (
            $row['id_snomed_medicamento'] ?? $row['snomed_code'] ?? $row['conceptId'] ?? $row['codigo'] ?? ''
        ));
        $mr->medication_code = $code !== '' ? $code : null;
        $mr->medication_display = $display;
        $parts = array_filter([
            isset($row['cantidad']) ? 'cant: ' . $row['cantidad'] : null,
            $row['Frecuencia de administracion'] ?? $row['frecuencia'] ?? $row['posologia'] ?? null,
            $row['Duracion del tratamiento'] ?? $row['durante'] ?? $row['duracion'] ?? null,
            $row['indicaciones'] ?? null,
        ]);
        $mr->dosage_text = $parts !== [] ? implode('; ', $parts) : ($row['dosage_text'] ?? null);
        if ($mr->dosage_text === null && isset($row['texto']) && trim((string) $row['texto']) !== $display) {
            $mr->dosage_text = trim((string) $row['texto']);
        }
        $mr->authored_on = date('Y-m-d H:i:s');
        $mr->id_profesional_efector_servicio = $encounter->id_profesional_efector_servicio;
        if (!$mr->save()) {
            throw new \RuntimeException('MedicationRequest: ' . json_encode($mr->getErrors()));
        }
        $this->carePlans->addMedicationActivity($carePlan, $mr);

        return $mr;
    }

    /**
     * Normaliza string o mapa asociativo suelto a fila de medicación.
     *
     * @param array<string, mixed>|string $row
     * @return array<string, mixed>
     */
    public static function normalizeExtractedMedicationRow($row): array
    {
        if (is_string($row)) {
            $text = trim($row);

            return $text === '' ? [] : ['texto' => $text];
        }
        if (!is_array($row)) {
            return [];
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function resolveMedicationDisplay(array $row): string
    {
        foreach ([
            'Nombre del medicamento',
            'nombre_del_medicamento',
            'medication_display',
            'medicamento',
            'termino',
            'nombre',
            'display',
            'label',
            'texto',
            'descripcion',
        ] as $key) {
            $value = trim((string) ($row[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * Acepta lista de filas, un string, o un único mapa asociativo (IA a veces no envuelve en []).
     *
     * @param mixed $payload
     * @return list<array<string, mixed>>
     */
    public static function normalizeExtractedMedicationPayload(mixed $payload): array
    {
        if ($payload === null) {
            return [];
        }
        if (is_string($payload)) {
            $row = self::normalizeExtractedMedicationRow($payload);

            return $row === [] ? [] : [$row];
        }
        if (!is_array($payload)) {
            return [];
        }
        if ($payload === []) {
            return [];
        }

        // Lista de filas (índices 0..n) vs un único objeto asociativo.
        if (self::isListArray($payload)) {
            $out = [];
            foreach ($payload as $row) {
                if (is_string($row) || is_array($row)) {
                    $normalized = self::normalizeExtractedMedicationRow($row);
                    if ($normalized !== [] && self::resolveMedicationDisplay($normalized) !== '') {
                        $out[] = $normalized;
                    }
                }
            }

            return $out;
        }

        // Un único objeto asociativo
        $normalized = self::normalizeExtractedMedicationRow($payload);

        return $normalized !== [] && self::resolveMedicationDisplay($normalized) !== ''
            ? [$normalized]
            : [];
    }

    /**
     * @param array<mixed> $arr
     */
    private static function isListArray(array $arr): bool
    {
        if (function_exists('array_is_list')) {
            return array_is_list($arr);
        }
        $i = 0;
        foreach ($arr as $k => $_) {
            if ($k !== $i) {
                return false;
            }
            $i++;
        }

        return true;
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
