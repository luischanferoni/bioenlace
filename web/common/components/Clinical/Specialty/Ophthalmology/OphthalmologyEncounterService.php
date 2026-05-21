<?php

namespace common\components\Clinical\Specialty\Ophthalmology;

use common\components\Clinical\Dto\ObservationDto;
use common\components\Clinical\Dto\VisionPrescriptionDto;
use common\models\Clinical\Encounter;
use common\models\Clinical\Observation;
use common\models\Clinical\VisionPrescription;

/**
 * Prácticas oftalmológicas → {@see Observation}; receta de lentes → {@see VisionPrescription}.
 */
final class OphthalmologyEncounterService
{
    /**
     * @return array{observations: list<array<string, mixed>>, visionPrescriptions: list<array<string, mixed>>}
     */
    public function bundleForEncounter(int $encounterId): array
    {
        $observations = Observation::find()
            ->where([
                'encounter_id' => $encounterId,
                'category' => Observation::CATEGORY_OPHTHALMOLOGY,
                'deleted_at' => null,
            ])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        $obsOut = [];
        foreach ($observations as $o) {
            $obsOut[] = ObservationDto::fromModel($o)->toArray();
        }

        $prescriptions = VisionPrescription::find()
            ->where(['encounter_id' => $encounterId, 'deleted_at' => null])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        $vpOut = [];
        foreach ($prescriptions as $vp) {
            $vpOut[] = VisionPrescriptionDto::fromModel($vp)->toArray();
        }

        return [
            'observations' => $obsOut,
            'visionPrescriptions' => $vpOut,
        ];
    }

    /**
     * @param mixed $payload filas ex ConsultaPracticasOftalmologia
     */
    public function persistPractices(Encounter $encounter, $payload): void
    {
        if (!is_array($payload)) {
            return;
        }
        foreach ($payload as $row) {
            if (!is_array($row)) {
                continue;
            }
            $obs = new Observation();
            $obs->encounter_id = $encounter->id;
            $obs->subject_persona_id = $encounter->subject_persona_id;
            $obs->status = 'final';
            $obs->category = Observation::CATEGORY_OPHTHALMOLOGY;
            $obs->code = (string) ($row['codigo'] ?? $row['conceptId'] ?? '');
            $obs->code_system = 'http://snomed.info/sct';
            $obs->value_string = $this->formatPracticeResult($row);
            $obs->value_json = json_encode([
                'eye' => $row['ojo'] ?? null,
                'prueba' => $row['prueba'] ?? null,
                'estado' => $row['estado'] ?? null,
                'informe' => $row['informe'] ?? null,
                'tipo' => $row['tipo'] ?? null,
            ], JSON_UNESCAPED_UNICODE);
            $obs->effective_datetime = date('Y-m-d H:i:s');
            if ($obs->code === '') {
                continue;
            }
            if (!$obs->save()) {
                throw new \RuntimeException('Observation oftalmo: ' . json_encode($obs->getErrors()));
            }
        }
    }

    /**
     * @param mixed $payload objeto o fila única ex ConsultasRecetaLentes
     */
    public function persistLensPrescription(Encounter $encounter, $payload): void
    {
        if (!is_array($payload)) {
            return;
        }
        if ($this->isListPayload($payload)) {
            foreach ($payload as $row) {
                if (is_array($row)) {
                    $this->saveLensPrescription($encounter, $row);
                }
            }

            return;
        }
        $this->saveLensPrescription($encounter, $payload);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function saveLensPrescription(Encounter $encounter, array $row): void
    {
        $spec = [
            'od' => [
                'sphere' => $row['od_esfera'] ?? null,
                'cylinder' => $row['od_cilindro'] ?? null,
                'axis' => $row['od_eje'] ?? null,
                'add' => $row['od_add'] ?? null,
            ],
            'oi' => [
                'sphere' => $row['oi_esfera'] ?? null,
                'cylinder' => $row['oi_cilindro'] ?? null,
                'axis' => $row['oi_eje'] ?? null,
                'add' => $row['oi_add'] ?? null,
            ],
        ];

        $existing = VisionPrescription::find()
            ->where(['encounter_id' => $encounter->id, 'deleted_at' => null])
            ->one();

        $vp = $existing instanceof VisionPrescription ? $existing : new VisionPrescription();
        $vp->encounter_id = $encounter->id;
        $vp->subject_persona_id = $encounter->subject_persona_id;
        $vp->status = 'active';
        $vp->lens_spec_json = json_encode($spec, JSON_UNESCAPED_UNICODE);
        if (!$vp->save()) {
            throw new \RuntimeException('VisionPrescription: ' . json_encode($vp->getErrors()));
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function formatPracticeResult(array $row): string
    {
        $parts = array_filter([
            isset($row['ojo']) ? 'ojo: ' . $row['ojo'] : null,
            isset($row['resultado']) ? 'resultado: ' . $row['resultado'] : null,
            isset($row['informe']) ? 'informe: ' . $row['informe'] : null,
        ]);

        return $parts ? implode('; ', $parts) : (string) ($row['resultado'] ?? '');
    }

    /**
     * @param array<mixed> $payload
     */
    private function isListPayload(array $payload): bool
    {
        if ($payload === []) {
            return false;
        }
        $first = reset($payload);

        return is_int(key($payload)) && is_array($first);
    }
}
