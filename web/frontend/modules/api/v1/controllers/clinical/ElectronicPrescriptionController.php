<?php

namespace frontend\modules\api\v1\controllers\clinical;

use common\components\Domain\Clinical\Prescription\Domain\Model\ElectronicPrescriptionDto;
use common\components\Domain\Clinical\Prescription\Domain\PrescriptionLegalStatus;
use common\components\Domain\Clinical\Prescription\Application\Service\ElectronicPrescriptionPdfService;
use common\components\Domain\Clinical\Prescription\Application\Presentation\ElectronicPrescriptionPresentationService;
use common\components\Domain\Clinical\Prescription\Application\UseCase\IssueElectronicPrescription;
use common\components\Domain\Person\Representation\Domain\Model\RepresentationPermission;
use common\components\Domain\Person\Representation\Application\Service\PersonRepresentationSubjectService;
use common\models\Person\PersonRelatedAuditLog;
use common\components\Platform\Core\Http\Contract\ApiException;
use common\components\Platform\Ui\UiScreenService;
use common\models\Clinical\ElectronicPrescription;
use common\models\Clinical\Encounter;
use common\models\Person\Persona;
use frontend\modules\api\v1\controllers\BaseController;
use Yii;
use yii\web\Response;

/**
 * Receta electrónica emitida.
 *
 * Fase 1: borrador / emitir / anular.
 * Fase 2: PDF, verificación por token, UI JSON paciente.
 */
class ElectronicPrescriptionController extends BaseController
{
    use ClinicalAccessTrait;

    private IssueElectronicPrescription $service;
    private ElectronicPrescriptionPresentationService $presentation;
    private ElectronicPrescriptionPdfService $pdf;

    public function init()
    {
        parent::init();
        $this->service = new IssueElectronicPrescription();
        $this->presentation = new ElectronicPrescriptionPresentationService();
        $this->pdf = new ElectronicPrescriptionPdfService($this->presentation);
    }

    public function actionCrearBorrador($encounterId): array
    {
        $encounter = $this->assertEncounterAccess((int) $encounterId);

        try {
            $rx = $this->service->createDraftFromEncounter($encounter, $this->jsonBody());
        } catch (\InvalidArgumentException $e) {
            return $this->fail('prescription_invalid', ['detail' => $e->getMessage()]);
        } catch (\Throwable $e) {
            Yii::error($e, 'electronic-prescription');

            return $this->fail('prescription_draft_failed');
        }

        return $this->ok(ElectronicPrescriptionDto::fromModel($rx)->toArray(), ['message' => 'Borrador de receta creado']);
    }

    public function actionPorEncounter($encounterId): array
    {
        $encounter = $this->assertEncounterAccess((int) $encounterId);

        $data = [];
        foreach ($this->service->listForEncounter((int) $encounter->id) as $rx) {
            $data[] = ElectronicPrescriptionDto::fromModel($rx, false)->toArray();
        }

        return $this->ok($data);
    }

    public function actionVer($id): array
    {
        $rx = $this->service->getById((int) $id);
        if ($rx === null) {
            return $this->fail('prescription_not_found');
        }
        if (!$this->canAccessPrescription($rx)) {
            return $this->fail('prescription_forbidden');
        }

        return $this->ok(ElectronicPrescriptionDto::fromModel($rx)->toArray());
    }

    public function actionEmitir($id): array
    {
        $rx = $this->service->getById((int) $id);
        if ($rx === null) {
            return $this->fail('prescription_not_found');
        }
        $this->assertEncounterAccess((int) $rx->encounter_id);

        try {
            $rx = $this->service->issue((int) $id);
        } catch (\InvalidArgumentException $e) {
            return $this->fail('prescription_invalid', ['detail' => $e->getMessage()]);
        } catch (\Throwable $e) {
            Yii::error($e, 'electronic-prescription');

            return $this->fail('prescription_issue_failed');
        }

        return $this->ok(ElectronicPrescriptionDto::fromModel($rx)->toArray(), ['message' => 'Receta emitida']);
    }

    public function actionAnular($id): array
    {
        $rx = $this->service->getById((int) $id);
        if ($rx === null) {
            return $this->fail('prescription_not_found');
        }
        $this->assertEncounterAccess((int) $rx->encounter_id);

        $body = $this->jsonBody();
        $reason = isset($body['reason']) ? (string) $body['reason'] : null;

        try {
            $rx = $this->service->cancel((int) $id, $reason);
        } catch (\InvalidArgumentException $e) {
            return $this->fail('prescription_invalid', ['detail' => $e->getMessage()]);
        }

        return $this->ok(ElectronicPrescriptionDto::fromModel($rx)->toArray(), ['message' => 'Receta anulada']);
    }

    /**
     * UI JSON: listado de recetas emitidas del paciente (asistente / móvil).
     */
    public function actionMisRecetasComoPaciente(): array
    {
        $req = Yii::$app->request;
        $params = array_merge($req->get(), $req->post());
        try {
            $subjectSvc = new PersonRepresentationSubjectService();
            $idPersona = $subjectSvc->resolveAndAuthorize($params, RepresentationPermission::CLINICAL_CARE_PLAN);
            $subjectSvc->auditDelegatedAction(PersonRelatedAuditLog::ACTION_CARE_PLAN_ACCESSED, $idPersona, ['scope' => 'recetas']);
        } catch (\InvalidArgumentException $e) {
            return $this->fail('prescription_invalid', ['detail' => $e->getMessage()]);
        } catch (\yii\web\ForbiddenHttpException $e) {
            return $this->fail('forbidden');
        }

        $out = UiScreenService::handleScreen(
            'electronic-prescription',
            'mis-recetas-como-paciente',
            $req->get(),
            $req->post(),
            static function (): array {
                return ['data' => ['ok' => true]];
            }
        );

        if (($out['kind'] ?? '') === 'ui_definition' && $req->isGet) {
            $items = [];
            foreach ((new IssueElectronicPrescription())->listIssuedForPersona($idPersona) as $rx) {
                $label = (string) ($rx->prescription_number ?? 'Receta');
                $issued = (string) ($rx->issued_at ?? '');
                if ($issued !== '') {
                    $label .= ' · ' . $issued;
                }
                $items[] = [
                    'id' => (string) $rx->id,
                    'name' => $label,
                    'label' => $label,
                    'subtitle' => (string) ($rx->diagnosis_display ?? ''),
                ];
            }

            return UiScreenService::withListBlockItems($out, $items);
        }

        return $out;
    }

    /**
     * UI JSON: detalle de receta emitida + PDF.
     */
    public function actionVerRecetaComoPaciente(): array
    {
        $req = Yii::$app->request;
        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona <= 0) {
            return $this->fail('patient_required');
        }

        $prescriptionId = (int) ($req->get('prescription_id') ?? $req->post('prescription_id') ?? 0);

        if (!$req->isPost) {
            if ($prescriptionId <= 0) {
                return $this->fail('prescription_select_required');
            }
            $rx = $this->service->getIssuedForPersona($idPersona, $prescriptionId);
            if ($rx === null) {
                return $this->fail('prescription_not_found');
            }

            $pdfPath = '/api/v1/clinical/electronic-prescription/descargar-pdf-como-paciente?prescription_id=' . $prescriptionId;
            $filename = 'receta-' . ($rx->prescription_number ?? $prescriptionId) . '.pdf';

            return UiScreenService::renderUiDefinition(
                'electronic-prescription',
                'ver-receta-como-paciente',
                array_merge($req->get(), ['prescription_id' => $prescriptionId]),
                [
                    'prescription_id' => (string) $prescriptionId,
                    'detalle_mensaje' => $this->presentation->formatDetailMessage($rx),
                    'pdf_url' => $pdfPath,
                    'filename' => $filename,
                ]
            );
        }

        return UiScreenService::handleScreen(
            'electronic-prescription',
            'ver-receta-como-paciente',
            $req->get(),
            $req->post(),
            static function (array $post): array {
                return ['data' => ['ok' => true, 'prescription_id' => (int) ($post['prescription_id'] ?? 0)]];
            }
        );
    }

    /**
     * GET /api/v1/clinical/electronic-prescription/descargar-pdf-como-paciente?prescription_id=
     */
    public function actionDescargarPdfComoPaciente()
    {
        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona <= 0) {
            throw new ApiException('patient_required');
        }

        $prescriptionId = (int) Yii::$app->request->get('prescription_id');
        if ($prescriptionId <= 0) {
            throw new ApiException('prescription_select_required');
        }

        $rx = $this->service->getIssuedForPersona($idPersona, $prescriptionId);
        if ($rx === null) {
            throw new ApiException('prescription_not_found');
        }

        $persona = Persona::findOne($idPersona);
        $binary = $this->pdf->renderBinary($rx, $persona);

        $filename = 'receta-' . ($rx->prescription_number ?? $prescriptionId) . '.pdf';
        $response = Yii::$app->response;
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->content = $binary;

        return $response;
    }

    /**
     * Verificación por token (farmacia / control). No expone datos clínicos completos del paciente.
     *
     * GET /api/v1/clinical/electronic-prescription/verificar-receta?token=
     */
    public function actionVerificarReceta(): array
    {
        $token = trim((string) Yii::$app->request->get('token'));
        if ($token === '') {
            return $this->fail('prescription_token_required');
        }

        $rx = $this->service->findIssuedByVerificationToken($token);
        if ($rx === null) {
            return $this->fail('prescription_token_not_found');
        }

        $valid = true;
        if ($rx->valid_until !== null && $rx->valid_until !== '' && $rx->valid_until < date('Y-m-d')) {
            $valid = false;
        }

        return $this->ok([
            'prescription_number' => $rx->prescription_number,
            'status' => $rx->status,
            'issued_at' => $rx->issued_at,
            'valid_until' => $rx->valid_until,
            'valid' => $valid,
            'item_count' => (int) $rx->getItems()->count(),
            'document_hash' => $rx->document_hash,
            'signature_provider' => $rx->signature_provider,
        ], ['message' => $valid ? 'Receta vigente' : 'Receta vencida']);
    }

    private function canAccessPrescription(ElectronicPrescription $rx): bool
    {
        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona > 0 && (int) $rx->subject_persona_id === $idPersona) {
            return in_array($rx->status, [PrescriptionLegalStatus::ISSUED, PrescriptionLegalStatus::CANCELLED], true);
        }

        $encounter = Encounter::findOne((int) $rx->encounter_id);

        return $encounter !== null && $this->canAccessEncounterDomain($encounter, 'Encounter.access');
    }

    /** @return array<string, mixed> */
    private function jsonBody(): array
    {
        $body = Yii::$app->request->getBodyParams();
        if (empty($body)) {
            $body = Yii::$app->request->post();
        }

        return is_array($body) ? $body : [];
    }
}
