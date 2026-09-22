<?php

namespace frontend\modules\api\v1\controllers\clinical;

use frontend\modules\api\v1\controllers\BaseController;
use Yii;
use yii\web\UploadedFile;
use common\components\Domain\Clinical\Encounter\Application\Service\AppointmentReasonMessageService;
use common\components\Domain\Clinical\Encounter\Application\Service\AppointmentReasonWindowService;
use common\components\Domain\Clinical\Encounter\Application\Service\AppointmentReasonChatGuideCatalogService;
use common\components\Domain\Person\Representation\Domain\Model\RepresentationPermission;
use common\components\Domain\Person\Representation\Application\Service\PersonRepresentationSubjectService;
use common\models\Person\PersonRelatedAuditLog;
use common\models\Clinical\AppointmentReasonMessage;
use common\models\Clinical\Encounter;
use common\models\Scheduling\Turno;

/**
 * API motivos de consulta (mensajes, envío, subida de archivos).
 *
 * Id público RBAC/URL: `motivos-consulta` (alias en Module controllerMap).
 */
class AppointmentReasonController extends BaseController
{
    use ClinicalAccessTrait;

    public $enableCsrfValidation = false;

    /**
     * GET /api/v1/motivos-consulta/mensajes/{id} — {id} = encounter_id (alias legacy en clientes).
     */
    public function actionListarMensajes($id)
    {
        $encounterId = (int) $id;
        [$encounter, $err] = $this->requireEncounterAccess($encounterId, RepresentationPermission::CLINICAL_MOTIVOS);
        if ($err !== null) {
            return $err;
        }

        $messages = AppointmentReasonMessage::find()
            ->where(['encounter_id' => $encounterId])
            ->orderBy(['created_at' => SORT_ASC])
            ->all();

        $formattedMessages = AppointmentReasonMessage::serializeForApi($messages);
        $chatGuide = (new AppointmentReasonChatGuideCatalogService())->buildChatGuide(
            $this->reservaTriageCodeForEncounter($encounter)
        );

        return [
            'success' => true,
            'message' => 'Mensajes obtenidos exitosamente',
            'data' => array_merge(
                [
                    'messages' => $formattedMessages,
                    'encounter_id' => $encounterId,
                    'consulta_id' => $encounterId,
                    'chat_guide' => $chatGuide,
                ],
                AppointmentReasonWindowService::apiState($encounterId)
            ),
        ];
    }

    /**
     * POST enviar mensaje de texto.
     */
    public function actionEnviar()
    {
        $body = Yii::$app->request->getBodyParams();
        $encounterId = $this->resolveEncounterIdFromInput($body);
        if ($encounterId === null) {
            return ['success' => false, 'message' => 'Datos requeridos: encounter_id (o consulta_id), message', 'data' => null];
        }

        [$encounter, $err] = $this->requireEncounterAccess($encounterId, RepresentationPermission::CLINICAL_MOTIVOS);
        if ($err !== null) {
            return $err;
        }

        $userId = (int) Yii::$app->user->id;
        $userName = Yii::$app->user->identity->username ?? 'Paciente';
        $message = (string) ($body['message'] ?? '');

        $result = (new AppointmentReasonMessageService())->sendText(
            $encounterId,
            $message,
            $userId,
            $userName
        );

        return $this->finalizeMessageResult($result, $encounter);
    }

    /**
     * POST subir archivo (imagen o audio).
     */
    public function actionSubir()
    {
        $encounterId = $this->resolveEncounterIdFromInput(Yii::$app->request->post());
        if ($encounterId === null) {
            return ['success' => false, 'message' => 'Falta encounter_id (o consulta_id)', 'data' => null];
        }

        [$encounter, $err] = $this->requireEncounterAccess($encounterId, RepresentationPermission::CLINICAL_MOTIVOS);
        if ($err !== null) {
            return $err;
        }

        $messageType = Yii::$app->request->post('message_type', 'imagen');
        if (!in_array($messageType, AppointmentReasonMessageService::UPLOAD_MESSAGE_TYPES, true)) {
            return ['success' => false, 'message' => 'message_type debe ser: imagen o audio', 'data' => null];
        }

        $file = UploadedFile::getInstanceByName('file');
        if (!$file || !$file->tempName) {
            return ['success' => false, 'message' => 'Debe enviar un archivo en el campo "file"', 'data' => null];
        }

        $ext = $file->getExtension() ?: pathinfo($file->name, PATHINFO_EXTENSION);
        $userId = (int) Yii::$app->user->id;
        $userName = Yii::$app->user->identity->username ?? 'Paciente';

        $result = (new AppointmentReasonMessageService())->sendMedia(
            $encounterId,
            (string) $messageType,
            (string) $file->tempName,
            (string) $ext,
            $userId,
            $userName
        );

        return $this->finalizeMessageResult($result, $encounter, (string) $messageType);
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function finalizeMessageResult(array $result, Encounter $encounter, ?string $messageType = null): array
    {
        if (isset($result['statusCode'])) {
            Yii::$app->response->statusCode = (int) $result['statusCode'];
            unset($result['statusCode']);
        }

        if (($result['success'] ?? false) === true) {
            $auditPayload = ['encounter_id' => (int) $encounter->id];
            if ($messageType !== null) {
                $auditPayload['message_type'] = $messageType;
            }
            (new PersonRepresentationSubjectService())->auditDelegatedAction(
                PersonRelatedAuditLog::ACTION_MOTIVOS_SENT,
                (int) $encounter->subject_persona_id,
                $auditPayload
            );
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function resolveEncounterIdFromInput(array $input): ?int
    {
        $raw = $input['encounter_id'] ?? $input['consulta_id'] ?? null;
        if ($raw === null || $raw === '') {
            return null;
        }

        return (int) $raw;
    }

    private function reservaTriageCodeForEncounter(Encounter $encounter): string
    {
        $turno = null;
        if ($encounter->appointment_id) {
            $turno = Turno::findActive()->andWhere(['id_turnos' => (int) $encounter->appointment_id])->one();
        }
        if (!$turno instanceof Turno && $encounter->parent_type === Encounter::PARENT_TURNO && $encounter->parent_id) {
            $turno = Turno::findActive()->andWhere(['id_turnos' => (int) $encounter->parent_id])->one();
        }

        return $turno instanceof Turno ? trim((string) ($turno->reserva_triage_code ?? '')) : '';
    }
}
