<?php

namespace common\components\Domain\Clinical\Specialty\Application\Service;

use common\components\Domain\Clinical\Capture\Domain\RowContract\OftalmologiaPracticaRowContract;
use common\components\Domain\Clinical\Capture\Domain\RowContract\OftalmologiaRecetaRowContract;
use common\components\Domain\Clinical\Laboratory\Domain\Model\ObservationDto;
use common\components\Domain\Clinical\CarePlan\Domain\Model\VisionPrescriptionDto;
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
     * Última receta de lentes del paciente, con las medidas por ojo.
     *
     * @return array{encounterId: int, registradoEn: string|null, od: array<string, string|null>, oi: array<string, string|null>}|null
     */
    public function latestLensPrescriptionForPerson(int $personaId): ?array
    {
        $vp = VisionPrescription::find()
            ->where(['subject_persona_id' => $personaId, 'deleted_at' => null])
            ->orderBy(['id' => SORT_DESC])
            ->one();
        if (!$vp instanceof VisionPrescription) {
            return null;
        }

        $spec = json_decode((string) $vp->lens_spec_json, true);
        $spec = is_array($spec) ? $spec : [];

        return [
            'encounterId' => (int) $vp->encounter_id,
            'registradoEn' => $vp->created_at !== null ? (string) $vp->created_at : null,
            'od' => $this->eyeValues($spec['od'] ?? null),
            'oi' => $this->eyeValues($spec['oi'] ?? null),
        ];
    }

    /**
     * @param mixed $eye
     * @return array{esfera: string|null, cilindro: string|null, eje: string|null, adicion: string|null}
     */
    private function eyeValues($eye): array
    {
        $eye = is_array($eye) ? $eye : [];

        return [
            'esfera' => $this->nullIfEmpty(trim((string) ($eye['sphere'] ?? ''))),
            'cilindro' => $this->nullIfEmpty(trim((string) ($eye['cylinder'] ?? ''))),
            'eje' => $this->nullIfEmpty(trim((string) ($eye['axis'] ?? ''))),
            'adicion' => $this->nullIfEmpty(trim((string) ($eye['add'] ?? ''))),
        ];
    }

    /**
     * @param mixed $payload ophthalmology practice rows
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

            $codigo = OftalmologiaPracticaRowContract::extractCodigo($row);
            if ($codigo === '') {
                $codigo = OftalmologiaPracticaRowContract::extractPrueba($row);
            }
            if ($codigo === '') {
                continue;
            }

            $ojo = OftalmologiaPracticaRowContract::extractOjo($row);
            $prueba = OftalmologiaPracticaRowContract::extractPrueba($row);
            $estado = OftalmologiaPracticaRowContract::extractEstado($row);
            $informe = OftalmologiaPracticaRowContract::extractInforme($row);
            $tipo = OftalmologiaPracticaRowContract::extractTipo($row);

            $obs = new Observation();
            $obs->encounter_id = $encounter->id;
            $obs->subject_persona_id = $encounter->subject_persona_id;
            $obs->status = 'final';
            $obs->category = Observation::CATEGORY_OPHTHALMOLOGY;
            $obs->code = $codigo;
            // Sólo un código numérico es SNOMED; el nombre de la práctica es un término local.
            $obs->code_system = ctype_digit($codigo) ? 'http://snomed.info/sct' : null;
            $obs->value_string = $this->formatPracticeResult($ojo, $prueba, $informe);
            $obs->value_json = json_encode([
                'eye' => $ojo !== '' ? $ojo : null,
                'prueba' => $prueba !== '' ? $prueba : null,
                'estado' => $estado !== '' ? $estado : null,
                'informe' => $informe !== '' ? $informe : null,
                'tipo' => $tipo !== '' ? $tipo : null,
            ], JSON_UNESCAPED_UNICODE);
            $obs->effective_datetime = date('Y-m-d H:i:s');
            if (!$obs->save()) {
                throw new \RuntimeException('Observation oftalmo: ' . json_encode($obs->getErrors()));
            }
        }
    }

    /**
     * @param mixed $payload ophthalmology lens prescription object or single row
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
        $values = OftalmologiaRecetaRowContract::values($row);
        $spec = [
            'od' => [
                'sphere' => $this->nullIfEmpty($values[OftalmologiaRecetaRowContract::FIELD_OD_ESFERA]),
                'cylinder' => $this->nullIfEmpty($values[OftalmologiaRecetaRowContract::FIELD_OD_CILINDRO]),
                'axis' => $this->nullIfEmpty($values[OftalmologiaRecetaRowContract::FIELD_OD_EJE]),
                'add' => $this->nullIfEmpty($values[OftalmologiaRecetaRowContract::FIELD_OD_ADICION]),
            ],
            'oi' => [
                'sphere' => $this->nullIfEmpty($values[OftalmologiaRecetaRowContract::FIELD_OI_ESFERA]),
                'cylinder' => $this->nullIfEmpty($values[OftalmologiaRecetaRowContract::FIELD_OI_CILINDRO]),
                'axis' => $this->nullIfEmpty($values[OftalmologiaRecetaRowContract::FIELD_OI_EJE]),
                'add' => $this->nullIfEmpty($values[OftalmologiaRecetaRowContract::FIELD_OI_ADICION]),
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

    private function formatPracticeResult(string $ojo, string $prueba, string $informe): string
    {
        $parts = [];
        if ($ojo !== '') {
            $parts[] = 'ojo: ' . $ojo;
        }
        if ($prueba !== '') {
            $parts[] = 'práctica: ' . $prueba;
        }
        if ($informe !== '') {
            $parts[] = 'informe: ' . $informe;
        }

        return implode('; ', $parts);
    }

    private function nullIfEmpty(string $value): ?string
    {
        return $value !== '' ? $value : null;
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
