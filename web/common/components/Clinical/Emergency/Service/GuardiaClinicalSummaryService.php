<?php

namespace common\components\Clinical\Emergency\Service;

use common\components\Clinical\Enum\RequestStatus;
use common\components\Clinical\Laboratory\Service\LaboratoryResultQueryService;
use common\components\Clinical\PatientHistoriaUrl;
use common\components\Clinical\Service\ServiceRequestService;
use common\models\Clinical\DiagnosticReport;
use common\models\Clinical\Encounter;
use common\models\Clinical\ServiceRequest;
use common\models\Guardia;

/**
 * Pedidos y laboratorio del episodio de guardia (encounter EMER con parent GUARDIA).
 */
final class GuardiaClinicalSummaryService
{
    /** @var GuardiaEncounterResolver */
    private $encounters;

    public function __construct(?GuardiaEncounterResolver $encounters = null)
    {
        $this->encounters = $encounters ?? new GuardiaEncounterResolver();
    }

    /**
     * @return array<string, mixed>
     */
    public function resumen(int $guardiaId, int $idEfector): array
    {
        $guardia = Guardia::findOne(['id' => $guardiaId, 'id_efector' => $idEfector]);
        if ($guardia === null) {
            throw new \InvalidArgumentException('Guardia no encontrada.');
        }

        $encounter = $this->encounters->findLatestForGuardia($guardiaId);
        $capturaUrl = PatientHistoriaUrl::captura(
            (int) $guardia->id_persona,
            Encounter::PARENT_GUARDIA,
            (int) $guardia->id
        );

        $orders = [];
        $laboratoryReports = [];
        $ordersPending = 0;
        $ordersWithResult = 0;

        if ($encounter !== null) {
            $encounterId = (int) $encounter->id;
            $hasLabReport = DiagnosticReport::find()
                ->where(['encounter_id' => $encounterId, 'deleted_at' => null])
                ->exists();

            foreach ((new ServiceRequestService())->listForEncounter($encounterId) as $sr) {
                $category = (string) $sr->category;
                $isLabLike = in_array($category, ['procedure', 'laboratory', 'lab'], true);
                $resultStatus = $isLabLike && $hasLabReport ? 'available' : ($isLabLike ? 'pending' : 'n/a');
                if ($isLabLike) {
                    if ($resultStatus === 'available') {
                        $ordersWithResult++;
                    } else {
                        $ordersPending++;
                    }
                }
                $orders[] = [
                    'id' => (int) $sr->id,
                    'display' => (string) ($sr->display ?? ''),
                    'category' => $category,
                    'status' => (string) $sr->status,
                    'result_status' => $resultStatus,
                ];
            }

            foreach ((new LaboratoryResultQueryService())->listForEncounter($encounterId) as $report) {
                if (!is_array($report)) {
                    continue;
                }
                $laboratoryReports[] = [
                    'id' => (int) ($report['id'] ?? 0),
                    'display' => (string) ($report['display'] ?? 'Informe de laboratorio'),
                    'issued_at' => $report['issuedAt'] ?? null,
                ];
            }
        }

        return [
            'guardia_id' => $guardiaId,
            'encounter_id' => $encounter ? (int) $encounter->id : null,
            'encounter_status' => $encounter ? (string) $encounter->status : null,
            'captura_url' => $capturaUrl,
            'orders' => $orders,
            'orders_count' => count($orders),
            'orders_lab_pending' => $ordersPending,
            'orders_lab_with_result' => $ordersWithResult,
            'laboratory_reports' => $laboratoryReports,
            'laboratory_reports_count' => count($laboratoryReports),
        ];
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function crearPedido(int $guardiaId, int $idEfector, array $body): array
    {
        $guardia = Guardia::findOne(['id' => $guardiaId, 'id_efector' => $idEfector]);
        if ($guardia === null) {
            throw new \InvalidArgumentException('Guardia no encontrada.');
        }

        $encounter = $this->encounters->findLatestForGuardia($guardiaId);
        if ($encounter === null) {
            throw new \InvalidArgumentException(
                'Inicie la atención o abra la captura clínica antes de registrar pedidos.'
            );
        }

        $display = trim((string) ($body['display'] ?? ''));
        if ($display === '') {
            throw new \InvalidArgumentException('Se requiere display del pedido.');
        }

        $category = (string) ($body['category'] ?? 'laboratory');
        $sr = (new ServiceRequestService())->createFromApi($encounter, null, [
            'display' => $display,
            'code' => $body['code'] ?? null,
            'category' => $category,
            'status' => RequestStatus::ACTIVE,
            'intent' => 'order',
        ]);

        return [
            'service_request_id' => (int) $sr->id,
            'encounter_id' => (int) $encounter->id,
            'display' => (string) $sr->display,
            'category' => (string) $sr->category,
        ];
    }
}
