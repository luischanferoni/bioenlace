<?php

namespace common\components\Clinical\Prescription\Service;

use common\components\Clinical\Enum\RequestStatus;
use common\components\Clinical\Prescription\Enum\PrescriptionEventType;
use common\components\Clinical\Prescription\Enum\PrescriptionLegalStatus;
use common\components\Clinical\Prescription\Mapper\FhirRecetaDigitalBundleMapper;
use common\components\Clinical\Prescription\Support\PrescriptionDocumentSupport;
use common\models\Clinical\ElectronicPrescription;
use common\models\Clinical\ElectronicPrescriptionEvent;
use common\models\Clinical\ElectronicPrescriptionItem;
use common\models\Clinical\Encounter;
use common\models\Clinical\MedicationRequest;
use common\models\Person\Persona;
use Yii;
use yii\db\Query;

/**
 * Receta electrónica emitida ficticia para desarrollo (lista, detalle, PDF, QR).
 */
final class ElectronicPrescriptionDemoSeedService
{
    public const SEED_MARKER = 'seed:electronic-prescription-demo';

    public const PRESCRIPTION_NUMBER_PREFIX = 'DEV-RX-';

    private const CARE_PLAN_SEED_TITLE = '[DEV] Care plan demo (app paciente)';

    /**
     * Crea o actualiza una receta emitida demo para la persona.
     *
     * @return array{prescription_id: int, created: bool, prescription_number: string}
     */
    public function upsertForPersona(int $idPersona): array
    {
        if (Persona::findOne($idPersona) === null) {
            throw new \InvalidArgumentException("No existe personas.id_persona={$idPersona}");
        }

        if (Yii::$app->db->schema->getTableSchema('{{%electronic_prescription}}', true) === null) {
            throw new \RuntimeException('Tabla electronic_prescription inexistente. Ejecutá migraciones de receta electrónica.');
        }

        $encounter = $this->resolveEncounter($idPersona);
        $meds = $this->ensureDemoMedication($idPersona, (int) $encounter->id);

        $prescriptionNumber = self::PRESCRIPTION_NUMBER_PREFIX . $idPersona;
        $rx = ElectronicPrescription::findOne([
            'prescription_number' => $prescriptionNumber,
            'deleted_at' => null,
        ]) ?? new ElectronicPrescription();

        $created = $rx->isNewRecord;
        $now = date('Y-m-d H:i:s');

        $rx->encounter_id = (int) $encounter->id;
        $rx->subject_persona_id = $idPersona;
        $rx->id_profesional_efector_servicio = $encounter->id_profesional_efector_servicio;
        $rx->status = PrescriptionLegalStatus::ISSUED;
        $rx->prescription_number = $prescriptionNumber;
        $rx->diagnosis_code = '38341003';
        $rx->diagnosis_code_system = 'http://snomed.info/sct';
        $rx->diagnosis_display = '[DEV] Hipertensión arterial (demo)';
        $rx->valid_from = date('Y-m-d');
        $rx->valid_until = date('Y-m-d', strtotime('+30 days'));
        $rx->issued_at = $now;
        $rx->notes = self::SEED_MARKER;

        if (!$rx->save()) {
            throw new \RuntimeException('ElectronicPrescription: ' . json_encode($rx->getErrors()));
        }

        $this->replaceItemsFromMedication($rx, $meds);

        $patient = Persona::findOne($idPersona);
        $bundle = (new FhirRecetaDigitalBundleMapper())->toBundleArray($rx, $patient);
        $bundleJson = json_encode($bundle, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $rx->fhir_bundle_json = $bundleJson;
        PrescriptionDocumentSupport::applyIssuanceSecurityFields($rx, $bundleJson);

        if (!$rx->save()) {
            throw new \RuntimeException('ElectronicPrescription (emitida): ' . json_encode($rx->getErrors()));
        }

        $repoResult = (new ElectronicPrescriptionRepositoryService())->syncAfterIssue($rx, $bundleJson);
        $this->recordSeedEvent($rx, PrescriptionEventType::ISSUED, ['prescription_number' => $rx->prescription_number]);
        $this->recordSeedEvent($rx, PrescriptionEventType::REPOSITORY_SYNC, $repoResult->toArray());

        return [
            'prescription_id' => (int) $rx->id,
            'created' => $created,
            'prescription_number' => (string) $rx->prescription_number,
        ];
    }

    public function removeForPersona(int $idPersona): bool
    {
        $prescriptionNumber = self::PRESCRIPTION_NUMBER_PREFIX . $idPersona;
        $rx = ElectronicPrescription::findOne([
            'prescription_number' => $prescriptionNumber,
            'deleted_at' => null,
        ]);
        if ($rx === null) {
            return false;
        }

        foreach ($rx->items as $item) {
            $item->delete();
        }
        ElectronicPrescriptionEvent::deleteAll(['electronic_prescription_id' => (int) $rx->id]);

        return (bool) $rx->delete();
    }

    /**
     * @return list<array{id: int, prescription_number: string|null, issued_at: string|null}>
     */
    public function listIssuedForPersona(int $idPersona): array
    {
        $rows = [];
        foreach ((new ElectronicPrescriptionService())->listIssuedForPersona($idPersona) as $rx) {
            $rows[] = [
                'id' => (int) $rx->id,
                'prescription_number' => $rx->prescription_number,
                'issued_at' => $rx->issued_at,
                'diagnosis_display' => $rx->diagnosis_display,
                'is_demo' => str_starts_with((string) ($rx->prescription_number ?? ''), self::PRESCRIPTION_NUMBER_PREFIX),
            ];
        }

        return $rows;
    }

    private function resolveEncounter(int $idPersona): Encounter
    {
        $encounterId = (new Query())
            ->select('encounter_id')
            ->from('{{%care_plan}}')
            ->where(['subject_persona_id' => $idPersona, 'title' => self::CARE_PLAN_SEED_TITLE, 'deleted_at' => null])
            ->andWhere(['not', ['encounter_id' => null]])
            ->scalar();

        if ($encounterId) {
            $enc = Encounter::findOne((int) $encounterId);
            if ($enc !== null) {
                return $enc;
            }
        }

        $enc = Encounter::find()
            ->andWhere(['subject_persona_id' => $idPersona, 'deleted_at' => null])
            ->orderBy(['id' => SORT_DESC])
            ->one();

        if ($enc !== null) {
            return $enc;
        }

        $now = date('Y-m-d H:i:s');
        $enc = new Encounter();
        $enc->subject_persona_id = $idPersona;
        $enc->encounter_class = 'AMB';
        $enc->status = 'finished';
        $enc->period_start = $now;
        $enc->period_end = $now;
        $enc->reason_text = 'Encounter demo para seed de receta';
        $enc->note = self::SEED_MARKER;
        if (!$enc->save()) {
            throw new \RuntimeException('Encounter: ' . json_encode($enc->getErrors()));
        }

        return $enc;
    }

    /**
     * @return MedicationRequest[]
     */
    private function ensureDemoMedication(int $idPersona, int $encounterId): array
    {
        $existing = MedicationRequest::find()
            ->andWhere([
                'encounter_id' => $encounterId,
                'subject_persona_id' => $idPersona,
                'status' => RequestStatus::ACTIVE,
                'deleted_at' => null,
            ])
            ->all();

        if ($existing !== []) {
            return $existing;
        }

        $now = date('Y-m-d H:i:s');
        $demoMeds = [
            ['code' => '387458008', 'display' => 'Enalapril 10 mg — 1 comp/día', 'dosage' => '1 comprimido por la mañana'],
            ['code' => '387517004', 'display' => 'Amlodipina 5 mg — 1 comp/día', 'dosage' => '1 comprimido por la noche'],
        ];

        $out = [];
        foreach ($demoMeds as $row) {
            $mr = new MedicationRequest();
            $mr->encounter_id = $encounterId;
            $mr->subject_persona_id = $idPersona;
            $mr->status = RequestStatus::ACTIVE;
            $mr->intent = 'order';
            $mr->medication_code = $row['code'];
            $mr->medication_display = $row['display'];
            $mr->dosage_text = $row['dosage'];
            $mr->authored_on = $now;
            if (!$mr->save()) {
                throw new \RuntimeException('MedicationRequest: ' . json_encode($mr->getErrors()));
            }
            $out[] = $mr;
        }

        return $out;
    }

    /**
     * @param MedicationRequest[] $meds
     */
    private function replaceItemsFromMedication(ElectronicPrescription $rx, array $meds): void
    {
        ElectronicPrescriptionItem::deleteAll(['electronic_prescription_id' => (int) $rx->id]);

        $line = 0;
        foreach ($meds as $mr) {
            $line++;
            $item = new ElectronicPrescriptionItem();
            $item->electronic_prescription_id = (int) $rx->id;
            $item->line_number = $line;
            $item->medication_request_id = (int) $mr->id;
            $item->medication_code = $mr->medication_code;
            $item->medication_code_system = 'http://snomed.info/sct';
            $item->medication_display = $mr->medication_display;
            $item->dosage_text = $mr->dosage_text;
            if (!$item->save()) {
                throw new \RuntimeException('ElectronicPrescriptionItem: ' . json_encode($item->getErrors()));
            }
        }
    }

    /** @param array<string, mixed>|null $payload */
    private function recordSeedEvent(ElectronicPrescription $rx, string $type, ?array $payload): void
    {
        $ev = new ElectronicPrescriptionEvent();
        $ev->electronic_prescription_id = (int) $rx->id;
        $ev->event_type = $type;
        $ev->payload_json = $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null;
        $ev->save(false);
    }
}
