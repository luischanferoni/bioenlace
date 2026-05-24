<?php

namespace frontend\modules\api\v1\controllers\clinical;

use common\components\Clinical\Dto\ElectronicPrescriptionDto;
use common\components\Clinical\Prescription\Enum\PrescriptionLegalStatus;
use common\components\Clinical\Prescription\Service\ElectronicPrescriptionService;
use common\components\Clinical\Service\EncounterAccessService;
use common\models\Clinical\ElectronicPrescription;
use common\models\Clinical\Encounter;
use frontend\modules\api\v1\controllers\BaseController;
use Yii;

/**
 * Receta electrónica emitida (MVP modo A).
 *
 * POST /api/v1/clinical/encounter/<encounterId>/electronic-prescription/crear-borrador
 * GET  /api/v1/clinical/encounter/<encounterId>/electronic-prescriptions
 * GET  /api/v1/clinical/electronic-prescription/<id>
 * POST /api/v1/clinical/electronic-prescription/<id>/emitir
 * POST /api/v1/clinical/electronic-prescription/<id>/anular
 * GET  /api/v1/clinical/electronic-prescription/mis-recetas-como-paciente
 */
class ElectronicPrescriptionController extends BaseController
{
    use ClinicalAccessTrait;

    private ElectronicPrescriptionService $service;

    public function init()
    {
        parent::init();
        $this->service = new ElectronicPrescriptionService();
    }

    public function actionCrearBorrador($encounterId): array
    {
        [$encounter, $err] = $this->requireEncounterAccess((int) $encounterId);
        if ($err !== null) {
            return $err;
        }

        $body = $this->jsonBody();

        try {
            $rx = $this->service->createDraftFromEncounter($encounter, $body);
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

    public function actionMisRecetasComoPaciente(): array
    {
        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona <= 0) {
            return $this->clinicalError('Solo pacientes autenticados.', null, 400);
        }

        $data = [];
        foreach ($this->service->listIssuedForPersona($idPersona) as $rx) {
            $row = ElectronicPrescriptionDto::fromModel($rx, true)->toArray();
            $row['itemCount'] = count($row['items'] ?? []);
            unset($row['items']);
            $data[] = $row;
        }

        return [
            'success' => true,
            'message' => 'Mis recetas electrónicas',
            'data' => $data,
        ];
    }

    private function canAccessPrescription(ElectronicPrescription $rx): bool
    {
        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona > 0 && (int) $rx->subject_persona_id === $idPersona) {
            if ($rx->status === PrescriptionLegalStatus::ISSUED || $rx->status === PrescriptionLegalStatus::CANCELLED) {
                return true;
            }

            return false;
        }

        $encounter = Encounter::findOne((int) $rx->encounter_id);

        return $encounter !== null && EncounterAccessService::userCanAccessEncounterApi($encounter);
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
