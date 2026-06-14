<?php

namespace common\components\Domain\Clinical\PatientSummary;

use common\components\Domain\Clinical\Enum\EncounterStatus;
use common\components\Domain\Clinical\Prescription\Enum\PrescriptionLegalStatus;
use common\components\Domain\Clinical\Enum\RequestStatus;
use common\components\Domain\Clinical\Laboratory\Service\LaboratoryResultQueryService;
use common\models\Clinical\DiagnosticReport;
use common\models\Clinical\ElectronicPrescription;
use common\models\Clinical\Encounter;
use common\models\Clinical\ServiceRequest;
use common\models\Efector;
use common\models\ProfesionalEfectorServicio;
use common\models\Servicio;

/**
 * Arma el DTO de resumen paciente desde encounter finalizado (texto IA en note).
 */
final class PatientEncounterSummaryBuilder
{
    /**
     * @return array<string, mixed>|null null si el encounter no es publicable
     */
    public function build(Encounter $encounter): ?array
    {
        if ($encounter->encounter_class !== Encounter::ENCOUNTER_CLASS_AMB) {
            return null;
        }
        if ($encounter->status !== EncounterStatus::FINISHED) {
            return null;
        }

        $narrative = trim((string) ($encounter->note ?? ''));
        $efector = $encounter->efector_id
            ? Efector::findOne((int) $encounter->efector_id)
            : null;
        $servicio = $encounter->service_id
            ? Servicio::findOne((int) $encounter->service_id)
            : null;

        $dto = [
            'encounterId' => (int) $encounter->id,
            'periodStart' => $encounter->period_start,
            'periodEnd' => $encounter->period_end,
            'reasonText' => trim((string) ($encounter->reason_text ?? '')),
            'narrativeText' => $narrative,
            'efector' => [
                'id' => $encounter->efector_id ? (int) $encounter->efector_id : null,
                'nombre' => $efector ? (string) $efector->nombre : null,
            ],
            'servicio' => [
                'id' => $encounter->service_id ? (int) $encounter->service_id : null,
                'nombre' => $servicio && isset($servicio->nombre) ? (string) $servicio->nombre : null,
            ],
            'profesional' => $this->resolveProfesionalDisplay($encounter),
            'prescriptions' => $this->listPrescriptions((int) $encounter->id),
            'orders' => $this->listOrders((int) $encounter->id, (int) $encounter->id),
            'laboratoryReports' => $this->listLaboratoryReports((int) $encounter->id),
        ];

        return $dto;
    }

    /**
     * @return array{id: int|null, display: string|null}
     */
    private function resolveProfesionalDisplay(Encounter $encounter): array
    {
        $pesId = (int) ($encounter->id_profesional_efector_servicio ?? 0);
        if ($pesId <= 0) {
            return ['id' => null, 'display' => null];
        }
        $pes = ProfesionalEfectorServicio::findOne(['id' => $pesId, 'deleted_at' => null]);
        if ($pes === null) {
            return ['id' => $pesId, 'display' => null];
        }
        $persona = $pes->persona ?? null;
        $display = $persona && method_exists($persona, 'getNombreCompleto')
            ? $persona->getNombreCompleto()
            : null;

        return ['id' => $pesId, 'display' => $display];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listPrescriptions(int $encounterId): array
    {
        $rows = ElectronicPrescription::find()
            ->where([
                'encounter_id' => $encounterId,
                'status' => PrescriptionLegalStatus::ISSUED,
                'deleted_at' => null,
            ])
            ->orderBy(['id' => SORT_DESC])
            ->all();

        $out = [];
        foreach ($rows as $rx) {
            $out[] = [
                'id' => (int) $rx->id,
                'issuedAt' => $rx->issued_at ?? null,
                'status' => (string) $rx->status,
                'detailApiRoute' => '/api/v1/clinical/electronic-prescription/ver-receta-como-paciente?prescription_id=' . (int) $rx->id,
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listLaboratoryReports(int $encounterId): array
    {
        $out = [];
        foreach ((new LaboratoryResultQueryService())->listForEncounter($encounterId) as $report) {
            if (!is_array($report)) {
                continue;
            }
            $out[] = [
                'id' => (int) ($report['id'] ?? 0),
                'display' => (string) ($report['display'] ?? 'Informe de laboratorio'),
                'issuedAt' => $report['issuedAt'] ?? null,
                'detailApiRoute' => '/api/v1/clinical/laboratory-result/ver-informe-como-paciente?report_id=' . (int) ($report['id'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listOrders(int $encounterId, int $encounterIdForLab): array
    {
        $hasLabReport = DiagnosticReport::find()
            ->where(['encounter_id' => $encounterIdForLab, 'deleted_at' => null])
            ->exists();

        $rows = ServiceRequest::find()
            ->where([
                'encounter_id' => $encounterId,
                'status' => RequestStatus::ACTIVE,
                'deleted_at' => null,
            ])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        $out = [];
        foreach ($rows as $sr) {
            $category = (string) $sr->category;
            $isLabLike = in_array($category, ['procedure', 'laboratory', 'lab'], true);
            $resultStatus = $isLabLike && $hasLabReport ? 'available' : ($isLabLike ? 'pending' : 'n/a');

            $out[] = [
                'id' => (int) $sr->id,
                'category' => $category,
                'display' => (string) ($sr->display ?? ''),
                'note' => trim((string) ($sr->note ?? '')),
                'resultStatus' => $resultStatus,
            ];
        }

        return $out;
    }
}
