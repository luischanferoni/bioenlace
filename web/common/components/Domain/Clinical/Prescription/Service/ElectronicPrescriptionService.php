<?php

namespace common\components\Domain\Clinical\Prescription\Service;

use common\components\Domain\Clinical\Enum\RequestStatus;
use common\components\Domain\Clinical\Prescription\Enum\PrescriptionEventType;
use common\components\Domain\Clinical\Prescription\Enum\PrescriptionLegalStatus;
use common\components\Domain\Clinical\Prescription\Mapper\FhirRecetaDigitalBundleMapper;
use common\components\Domain\Clinical\Prescription\Support\PrescriptionDocumentSupport;
use common\components\Domain\Clinical\Prescription\Service\PrescriptionRdiPreSubmitValidationAgent;
use common\components\Domain\Clinical\Service\MedicationRequestService;
use common\models\Clinical\ElectronicPrescription;
use common\models\Clinical\ElectronicPrescriptionEvent;
use common\models\Clinical\ElectronicPrescriptionItem;
use common\models\Clinical\Encounter;
use common\models\Clinical\MedicationRequest;
use common\models\Person\Persona;
use Yii;
use yii\db\Transaction;

final class ElectronicPrescriptionService
{
    private MedicationRequestService $medicationRequests;
    private FhirRecetaDigitalBundleMapper $bundleMapper;
    private ElectronicPrescriptionRepositoryService $repository;

    public function __construct(
        ?MedicationRequestService $medicationRequests = null,
        ?FhirRecetaDigitalBundleMapper $bundleMapper = null,
        ?ElectronicPrescriptionRepositoryService $repository = null
    ) {
        $this->medicationRequests = $medicationRequests ?? new MedicationRequestService();
        $this->bundleMapper = $bundleMapper ?? new FhirRecetaDigitalBundleMapper();
        $this->repository = $repository ?? new ElectronicPrescriptionRepositoryService();
    }

    /**
     * @param array<string, mixed> $options diagnosis_code, diagnosis_display, notes, valid_days
     */
    public function createDraftFromEncounter(Encounter $encounter, array $options = []): ElectronicPrescription
    {
        $meds = $this->medicationRequests->listForEncounter((int) $encounter->id);
        $active = array_filter($meds, static fn (MedicationRequest $mr) => $mr->status === RequestStatus::ACTIVE);
        if ($active === []) {
            throw new \InvalidArgumentException('No hay medicación activa en el encounter para armar la receta.');
        }

        $pesId = $this->resolvePrescriberPesId($encounter);

        $tx = Yii::$app->db->beginTransaction(Transaction::SERIALIZABLE);
        try {
            $rx = new ElectronicPrescription();
            $rx->encounter_id = (int) $encounter->id;
            $rx->subject_persona_id = (int) $encounter->subject_persona_id;
            $rx->id_profesional_efector_servicio = $pesId;
            $rx->status = PrescriptionLegalStatus::DRAFT;
            $rx->diagnosis_code = isset($options['diagnosis_code']) ? (string) $options['diagnosis_code'] : null;
            $rx->diagnosis_code_system = isset($options['diagnosis_code_system'])
                ? (string) $options['diagnosis_code_system']
                : null;
            $rx->diagnosis_display = isset($options['diagnosis_display']) ? (string) $options['diagnosis_display'] : null;
            $rx->notes = isset($options['notes']) ? (string) $options['notes'] : null;
            if (!$rx->save()) {
                throw new \RuntimeException('ElectronicPrescription: ' . json_encode($rx->getErrors()));
            }

            $line = 0;
            foreach ($active as $mr) {
                $line++;
                $this->addItemFromMedicationRequest($rx, $mr, $line);
            }

            $this->recordEvent($rx, PrescriptionEventType::DRAFT_CREATED, null);
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }

        return ElectronicPrescription::findOne((int) $rx->id) ?? $rx;
    }

    public function issue(int $prescriptionId): ElectronicPrescription
    {
        $rx = $this->requirePrescription($prescriptionId);
        if ($rx->status !== PrescriptionLegalStatus::DRAFT) {
            throw new \InvalidArgumentException('Solo se puede emitir una receta en borrador.');
        }

        $itemCount = ElectronicPrescriptionItem::find()
            ->andWhere(['electronic_prescription_id' => $rx->id, 'deleted_at' => null])
            ->count();
        if ($itemCount < 1) {
            throw new \InvalidArgumentException('La receta no tiene ítems.');
        }

        (new PrescriptionRdiPreSubmitValidationAgent())->assertCanIssue($rx);

        $tx = Yii::$app->db->beginTransaction();
        try {
            $rx->status = PrescriptionLegalStatus::ISSUED;
            $rx->prescription_number = $this->generatePrescriptionNumber((int) $rx->id);
            $rx->issued_at = date('Y-m-d H:i:s');
            $rx->valid_from = date('Y-m-d');
            $rx->valid_until = date('Y-m-d', strtotime('+30 days'));

            $patient = Persona::findOne((int) $rx->subject_persona_id);
            $bundle = $this->bundleMapper->toBundleArray($rx, $patient);
            $bundleJson = json_encode($bundle, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $rx->fhir_bundle_json = $bundleJson;
            PrescriptionDocumentSupport::applyIssuanceSecurityFields($rx, $bundleJson);

            if (!$rx->save()) {
                throw new \RuntimeException('ElectronicPrescription: ' . json_encode($rx->getErrors()));
            }

            $this->recordEvent($rx, PrescriptionEventType::ISSUED, ['prescription_number' => $rx->prescription_number]);

            $repoResult = $this->repository->syncAfterIssue($rx, $bundleJson);
            $this->recordEvent($rx, PrescriptionEventType::REPOSITORY_SYNC, $repoResult->toArray());

            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }

        return $this->requirePrescription($prescriptionId);
    }

    public function cancel(int $prescriptionId, ?string $reason = null): ElectronicPrescription
    {
        $rx = $this->requirePrescription($prescriptionId);
        if ($rx->status !== PrescriptionLegalStatus::ISSUED) {
            throw new \InvalidArgumentException('Solo se puede anular una receta emitida.');
        }

        $rx->status = PrescriptionLegalStatus::CANCELLED;
        $rx->cancelled_at = date('Y-m-d H:i:s');
        $rx->cancellation_reason = $reason !== null && trim($reason) !== '' ? trim($reason) : null;
        if (!$rx->save()) {
            throw new \RuntimeException('ElectronicPrescription: ' . json_encode($rx->getErrors()));
        }

        $this->recordEvent($rx, PrescriptionEventType::CANCELLED, ['reason' => $rx->cancellation_reason]);

        return $rx;
    }

    public function getById(int $id): ?ElectronicPrescription
    {
        return ElectronicPrescription::findOne(['id' => $id, 'deleted_at' => null]);
    }

    public function findIssuedByVerificationToken(string $token): ?ElectronicPrescription
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        return ElectronicPrescription::findOne([
            'verification_token' => $token,
            'status' => PrescriptionLegalStatus::ISSUED,
            'deleted_at' => null,
        ]);
    }

    public function getIssuedForPersona(int $idPersona, int $prescriptionId): ?ElectronicPrescription
    {
        if ($idPersona <= 0 || $prescriptionId <= 0) {
            return null;
        }

        return ElectronicPrescription::findOne([
            'id' => $prescriptionId,
            'subject_persona_id' => $idPersona,
            'status' => PrescriptionLegalStatus::ISSUED,
            'deleted_at' => null,
        ]);
    }

    /**
     * @return ElectronicPrescription[]
     */
    public function listForEncounter(int $encounterId): array
    {
        return ElectronicPrescription::find()
            ->andWhere(['encounter_id' => $encounterId, 'deleted_at' => null])
            ->orderBy(['id' => SORT_DESC])
            ->all();
    }

    /**
     * @return ElectronicPrescription[]
     */
    public function listIssuedForPersona(int $idPersona, int $limit = 50): array
    {
        return ElectronicPrescription::find()
            ->andWhere([
                'subject_persona_id' => $idPersona,
                'status' => PrescriptionLegalStatus::ISSUED,
                'deleted_at' => null,
            ])
            ->orderBy(['issued_at' => SORT_DESC, 'id' => SORT_DESC])
            ->limit($limit)
            ->all();
    }

    private function requirePrescription(int $id): ElectronicPrescription
    {
        $rx = $this->getById($id);
        if ($rx === null) {
            throw new \InvalidArgumentException('Receta no encontrada.');
        }

        return $rx;
    }

    private function addItemFromMedicationRequest(
        ElectronicPrescription $rx,
        MedicationRequest $mr,
        int $lineNumber
    ): void {
        $item = new ElectronicPrescriptionItem();
        $item->electronic_prescription_id = (int) $rx->id;
        $item->line_number = $lineNumber;
        $item->medication_request_id = (int) $mr->id;
        $item->medication_code = $mr->medication_code;
        $item->medication_code_system = 'http://snomed.info/sct';
        $item->medication_display = $mr->medication_display;
        $item->dosage_text = $mr->dosage_text;
        if (!$item->save()) {
            throw new \RuntimeException('ElectronicPrescriptionItem: ' . json_encode($item->getErrors()));
        }
    }

    private function resolvePrescriberPesId(Encounter $encounter): ?int
    {
        $pesRaw = Yii::$app->user->getIdProfesionalEfectorServicio();
        $pesSession = $pesRaw !== null && $pesRaw !== '' ? (int) $pesRaw : 0;
        if ($pesSession > 0) {
            return $pesSession;
        }
        $pesEnc = (int) ($encounter->id_profesional_efector_servicio ?? 0);

        return $pesEnc > 0 ? $pesEnc : null;
    }

    private function generatePrescriptionNumber(int $id): string
    {
        return 'RX-' . date('Ymd') . '-' . str_pad((string) $id, 8, '0', STR_PAD_LEFT);
    }

    /** @param array<string, mixed>|null $payload */
    private function recordEvent(ElectronicPrescription $rx, string $type, ?array $payload): void
    {
        $ev = new ElectronicPrescriptionEvent();
        $ev->electronic_prescription_id = (int) $rx->id;
        $ev->event_type = $type;
        if (Yii::$app->has('user') && !Yii::$app->user->isGuest) {
            $ev->actor_user_id = (int) Yii::$app->user->id;
        }
        $ev->payload_json = $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null;
        $ev->save(false);
    }
}
