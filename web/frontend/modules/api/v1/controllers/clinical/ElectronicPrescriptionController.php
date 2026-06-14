<?php

namespace frontend\modules\api\v1\controllers\clinical;

use common\components\Domain\Clinical\Dto\ElectronicPrescriptionDto;
use common\components\Domain\Clinical\Prescription\Enum\PrescriptionLegalStatus;
use common\components\Domain\Clinical\Prescription\Service\ElectronicPrescriptionPdfService;
use common\components\Domain\Clinical\Prescription\Service\ElectronicPrescriptionPresentationService;
use common\components\Domain\Clinical\Prescription\Service\ElectronicPrescriptionService;
use common\components\Domain\Person\Representation\Enum\RepresentationPermission;
use common\components\Domain\Person\Representation\Service\PersonRepresentationSubjectService;
use common\models\Person\PersonRelatedAuditLog;
use common\components\Platform\Ui\UiScreenService;
use common\models\Clinical\ElectronicPrescription;
use common\models\Clinical\Encounter;
use common\models\Person\Persona;
use frontend\modules\api\v1\controllers\BaseController;
use Yii;
use yii\web\NotFoundHttpException;
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

    private ElectronicPrescriptionService $service;
    private ElectronicPrescriptionPresentationService $presentation;
    private ElectronicPrescriptionPdfService $pdf;

    public function init()
    {
        parent::init();
        $this->service = new ElectronicPrescriptionService();
        $this->presentation = new ElectronicPrescriptionPresentationService();
        $this->pdf = new ElectronicPrescriptionPdfService($this->presentation);
    }

    public function actionCrearBorrador($encounterId): array
    {
        [$encounter, $err] = $this->requireEncounterAccess((int) $encounterId);
        if ($err !== null) {
            return $err;
        }

        try {
            $rx = $this->service->createDraftFromEncounter($encounter, $this->jsonBody());
        } catch (\InvalidArgumentException $e) {
            return $this->clinicalError($e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            Yii::error($e, 'electronic-prescription');

            return $this->clinicalError('No se pudo crear el borrador de receta.', null, 500);
        }

        return [
            'success' => true,
            'message' => 'Borrador de receta creado',
            'data' => ElectronicPrescriptionDto::fromModel($rx)->toArray(),
        ];
    }

    public function actionPorEncounter($encounterId): array
    {
        [$encounter, $err] = $this->requireEncounterAccess((int) $encounterId);
        if ($err !== null) {
            return $err;
        }

        $data = [];
        foreach ($this->service->listForEncounter((int) $encounter->id) as $rx) {
            $data[] = ElectronicPrescriptionDto::fromModel($rx, false)->toArray();
        }

        return [
            'success' => true,
            'message' => 'Recetas del encounter',
            'data' => $data,
        ];
    }

    public function actionVer($id): array
    {
        $rx = $this->service->getById((int) $id);
        if ($rx === null) {
            return $this->clinicalError('Receta no encontrada', null, 404);
        }
        if (!$this->canAccessPrescription($rx)) {
            return $this->clinicalError('No tiene permiso para ver esta receta', null, 403);
        }

        return [
            'success' => true,
            'message' => 'Detalle de receta',
            'data' => ElectronicPrescriptionDto::fromModel($rx)->toArray(),
        ];
    }

    public function actionEmitir($id): array
    {
        $rx = $this->service->getById((int) $id);
        if ($rx === null) {
            return $this->clinicalError('Receta no encontrada', null, 404);
        }
        [$encounter, $err] = $this->requireEncounterAccess((int) $rx->encounter_id);
        if ($err !== null) {
            return $err;
        }

        try {
            $rx = $this->service->issue((int) $id);
        } catch (\InvalidArgumentException $e) {
            return $this->clinicalError($e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            Yii::error($e, 'electronic-prescription');

            return $this->clinicalError('No se pudo emitir la receta.', null, 500);
        }

        return [
            'success' => true,
            'message' => 'Receta emitida',
            'data' => ElectronicPrescriptionDto::fromModel($rx)->toArray(),
        ];
    }

    public function actionAnular($id): array
    {
        $rx = $this->service->getById((int) $id);
        if ($rx === null) {
            return $this->clinicalError('Receta no encontrada', null, 404);
        }
        [$encounter, $err] = $this->requireEncounterAccess((int) $rx->encounter_id);
        if ($err !== null) {
            return $err;
        }

        $body = $this->jsonBody();
        $reason = isset($body['reason']) ? (string) $body['reason'] : null;

        try {
            $rx = $this->service->cancel((int) $id, $reason);
        } catch (\InvalidArgumentException $e) {
            return $this->clinicalError($e->getMessage(), null, 400);
        }

        return [
            'success' => true,
            'message' => 'Receta anulada',
            'data' => ElectronicPrescriptionDto::fromModel($rx)->toArray(),
        ];
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
            return $this->clinicalError($e->getMessage(), null, 400);
        } catch (\yii\web\ForbiddenHttpException $e) {
            return $this->clinicalError($e->getMessage(), null, 403);
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
            foreach ((new ElectronicPrescriptionService())->listIssuedForPersona($idPersona) as $rx) {
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
            return $this->clinicalError('Solo pacientes autenticados.', null, 400);
        }

        $prescriptionId = (int) ($req->get('prescription_id') ?? $req->post('prescription_id') ?? 0);

        if (!$req->isPost) {
            if ($prescriptionId <= 0) {
                throw new \InvalidArgumentException('Seleccioná una receta de la lista.');
            }
            $rx = $this->service->getIssuedForPersona($idPersona, $prescriptionId);
            if ($rx === null) {
                throw new \InvalidArgumentException('Receta no encontrada.');
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
            throw new NotFoundHttpException('Solo pacientes autenticados.');
        }

        $prescriptionId = (int) Yii::$app->request->get('prescription_id');
        if ($prescriptionId <= 0) {
            throw new NotFoundHttpException('prescription_id requerido.');
        }

        $rx = $this->service->getIssuedForPersona($idPersona, $prescriptionId);
        if ($rx === null) {
            throw new NotFoundHttpException('Receta no encontrada.');
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
            return $this->clinicalError('token requerido', null, 400);
        }

        $rx = $this->service->findIssuedByVerificationToken($token);
        if ($rx === null) {
            return $this->clinicalError('Receta no encontrada o no vigente', null, 404);
        }

        $valid = true;
        if ($rx->valid_until !== null && $rx->valid_until !== '' && $rx->valid_until < date('Y-m-d')) {
            $valid = false;
        }

        return [
            'success' => true,
            'message' => $valid ? 'Receta vigente' : 'Receta vencida',
            'data' => [
                'prescription_number' => $rx->prescription_number,
                'status' => $rx->status,
                'issued_at' => $rx->issued_at,
                'valid_until' => $rx->valid_until,
                'valid' => $valid,
                'item_count' => (int) $rx->getItems()->count(),
                'document_hash' => $rx->document_hash,
                'signature_provider' => $rx->signature_provider,
            ],
        ];
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
