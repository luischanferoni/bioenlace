<?php

namespace frontend\modules\api\v1\controllers\clinical;

use frontend\modules\api\v1\controllers\BaseController;
use common\models\Clinical\ConsultaChatMessage;
use common\components\Domain\Clinical\Encounter\Application\Service\SecureMediaService;
use common\components\Domain\Scheduling\Agenda\Application\UseCase\UploadEncounterAsyncChat;
use common\components\Domain\Scheduling\Agenda\Application\UseCase\PushEncounterAsyncNotification;
use common\components\Domain\Scheduling\Agenda\Application\Agents\EncounterAsyncInboxPriorityAgent;
use common\components\Domain\Scheduling\Agenda\Application\Service\EncounterAsyncChatPolicyCatalogService;
use common\components\Domain\Scheduling\Agenda\Application\Service\EncounterAsyncChatPolicyService;
use common\components\Domain\Scheduling\Agenda\Application\Service\EncounterAsyncMetaService;
use common\components\Domain\Scheduling\Agenda\Application\Service\EncounterAsyncIntakeContextService;
use common\models\Clinical\Encounter;
use common\models\Person\Persona;
use frontend\modules\api\v1\controllers\clinical\ClinicalAccessTrait;
use Yii;
use yii\web\UploadedFile;

/**
 * API chat clínico por encounter (mensajes, envío, subida, estado).
 *
 * La URL conserva el segmento `consulta-chat`; el identificador es `encounter_id`.
 */
class EncounterChatController extends BaseController
{
    use ClinicalAccessTrait;

    public $enableCsrfValidation = false;

    /**
     * GET /api/v1/consulta-chat/mensajes/{id} — {id} = encounter_id.
     */
    public function actionListarMensajes($id)
    {
        $encounterId = (int) $id;
        $encounter = $this->assertEncounterAccess($encounterId);

        $subject = $encounter->subject;
        $meta = (new EncounterAsyncMetaService())->fromEncounter($encounter);
        $idPersona = (int) ($encounter->subject_persona_id ?? 0);
        $intakeContext = (new EncounterAsyncIntakeContextService())->buildFromMeta($meta, $idPersona);
        $idPersonaViewer = (int) Yii::$app->user->getIdPersona();
        $viewerEsPaciente = $idPersonaViewer > 0 && $idPersonaViewer === $idPersona;
        $chatPolicy = (new EncounterAsyncChatPolicyService())->resolveForEncounter($encounter, $viewerEsPaciente);
        $policyCatalog = new EncounterAsyncChatPolicyCatalogService();

        $messages = ConsultaChatMessage::find()
            ->where(['encounter_id' => $encounterId])
            ->orderBy(['created_at' => SORT_ASC])
            ->all();

        $formattedMessages = [];
        foreach ($messages as $message) {
            $content = $message->content;
            if (in_array($message->message_type, ['imagen', 'audio', 'video', 'documento'], true) && $content !== '') {
                $content = SecureMediaService::contentForApi(
                    SecureMediaService::SCOPE_CONSULTA_CHAT,
                    $encounterId,
                    (string) $content
                );
            } elseif (
                !$viewerEsPaciente
                && ($message->message_type === 'sistema' || $message->user_role === 'sistema')
            ) {
                $staffFacing = $policyCatalog->staffFacingSystemContent((string) $content);
                if ($staffFacing !== null) {
                    $content = $staffFacing;
                }
            }
            $formattedMessages[] = [
                'id' => $message->id,
                'content' => $content,
                'user_id' => $message->user_id,
                'user_name' => $message->user_name,
                'user_role' => $message->user_role,
                'message_type' => $message->message_type ?: 'texto',
                'message_kind' => $this->messageKindFromMessage($message),
                'solicitud_categoria' => $this->solicitudCategoriaForApi($message),
                'solicitud_categoria_label' => $this->solicitudCategoriaLabelForApi($message),
                'created_at' => $message->created_at,
                'is_read' => $message->is_read,
            ];
        }

        return $this->ok([
            'messages' => $formattedMessages,
            'encounter_id' => (int) $encounter->id,
            'intake_context' => $intakeContext,
            'chat_policy' => $chatPolicy,
            'encounter' => [
                'id' => (int) $encounter->id,
                'status' => (string) $encounter->status,
                'paciente' => $subject ? $subject->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N) : 'Paciente',
            ],
        ]);
    }

    private function messageKindFromMessage(ConsultaChatMessage $message): string
    {
        $categoria = $this->solicitudCategoriaForApi($message);
        if ($categoria !== null) {
            return 'solicitud';
        }
        $messageType = (string) ($message->message_type ?: 'texto');
        if ($messageType === 'sistema') {
            return 'sistema';
        }

        return 'chat';
    }

    private function solicitudCategoriaForApi(ConsultaChatMessage $message): ?string
    {
        $raw = trim((string) ($message->solicitud_categoria ?? ''));
        if ($raw !== '') {
            return $raw;
        }
        $legacy = (new EncounterAsyncChatPolicyCatalogService())
            ->solicitudCategoriaFromLegacyMessageType((string) ($message->message_type ?: ''));

        return $legacy;
    }

    private function solicitudCategoriaLabelForApi(ConsultaChatMessage $message): ?string
    {
        $categoria = $this->solicitudCategoriaForApi($message);
        if ($categoria === null) {
            return null;
        }

        return (new EncounterAsyncChatPolicyCatalogService())->solicitudCategoriaLabel($categoria);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseEncounterNote(?string $note): array
    {
        return (new EncounterAsyncMetaService())->parse($note);
    }

    public function actionEnviar()
    {
        $body = Yii::$app->request->getBodyParams();
        $encounterId = $this->resolveEncounterIdFromBody($body);
        if (!$encounterId) {
            return $this->fail('encounter_id_required');
        }

        $encounter = $this->assertEncounterAccess($encounterId);

        $message = $body['message'] ?? null;
        $user_role = $body['user_role'] ?? null;
        if ($message === null || $message === '') {
            return $this->fail('chat_message_required');
        }

        $userId = Yii::$app->user->id;
        $userName = Yii::$app->user->identity->username ?? 'Usuario';
        if (!$user_role) {
            $user_role = (int) $encounter->subject_persona_id === (int) Yii::$app->user->getIdPersona() ? 'paciente' : 'medico';
        }

        if ($encounter->parent_type === Encounter::PARENT_SOLICITUD_ASYNC) {
            try {
                $policySvc = new EncounterAsyncChatPolicyService();
                if ($user_role === 'paciente') {
                    $policySvc->assertPatientCanSend($encounter);
                } else {
                    $policySvc->assertStaffCanSend($encounter);
                }
            } catch (\InvalidArgumentException $e) {
                return $this->fail('chat_send_not_allowed', ['detail' => $e->getMessage()]);
            }
        }

        $messageType = $body['message_type'] ?? 'texto';
        $allowedTypes = ['texto', 'imagen', 'audio', 'video', 'documento'];
        if (!in_array($messageType, $allowedTypes, true)) {
            $messageType = 'texto';
        }

        $chatMessage = new ConsultaChatMessage();
        $chatMessage->encounter_id = $encounterId;
        $chatMessage->user_id = $userId;
        $chatMessage->user_name = $userName;
        $chatMessage->user_role = $user_role;
        $chatMessage->content = $message;
        $chatMessage->message_type = $messageType;

        if (!$chatMessage->save()) {
            return $this->fail('chat_message_save_failed', ['detail' => implode(', ', $chatMessage->getFirstErrors())]);
        }

        if ($user_role === 'paciente' && $encounter->parent_type === Encounter::PARENT_SOLICITUD_ASYNC) {
            try {
                (new EncounterAsyncInboxPriorityAgent())->onPacienteMensaje($encounter);
            } catch (\Throwable $e) {
                Yii::warning('Prioridad async mensaje paciente: ' . $e->getMessage(), 'consulta-async-prioridad');
            }
            try {
                (new EncounterAsyncChatPolicyService())->maybeAutoCloseAfterPatientMessage($encounter);
            } catch (\Throwable $e) {
                Yii::warning('Auto-cierre async: ' . $e->getMessage(), 'consulta-async-lifecycle');
            }
        }

        if (
            $encounter->parent_type === Encounter::PARENT_SOLICITUD_ASYNC
            && in_array($user_role, ['medico', 'enfermeria'], true)
        ) {
            $prevStaffCount = ConsultaChatMessage::find()
                ->where(['encounter_id' => $encounterId])
                ->andWhere(['user_role' => ['medico', 'enfermeria']])
                ->andWhere(['<', 'id', (int) $chatMessage->id])
                ->count();
            if ($prevStaffCount === 0) {
                try {
                    (new PushEncounterAsyncNotification())->notifyRespuestaStaffPatient($encounter);
                } catch (\Throwable $e) {
                    Yii::warning('Push async respuesta staff: ' . $e->getMessage(), 'consulta-async-push');
                }
            }
        }

        return $this->ok([
            'id' => $chatMessage->id,
            'content' => $chatMessage->content,
            'user_id' => $chatMessage->user_id,
            'user_name' => $chatMessage->user_name,
            'user_role' => $chatMessage->user_role,
            'message_type' => $chatMessage->message_type,
            'created_at' => $chatMessage->created_at,
            'is_read' => $chatMessage->is_read,
        ], ['message' => 'Mensaje enviado']);
    }

    public function actionSubir()
    {
        $encounterId = $this->resolveEncounterIdFromBody(Yii::$app->request->post());
        if (!$encounterId) {
            return $this->fail('encounter_id_required');
        }

        $encounter = $this->assertEncounterAccess($encounterId);

        $messageType = Yii::$app->request->post('message_type', 'audio');
        $isAsync = $encounter->parent_type === Encounter::PARENT_SOLICITUD_ASYNC;
        if (!$isAsync && !in_array($messageType, self::UPLOAD_MESSAGE_TYPES, true)) {
            return $this->fail('chat_attachment_type_invalid');
        }
        if ($isAsync && !in_array($messageType, ['imagen', 'audio', 'documento'], true)) {
            return $this->fail('chat_attachment_type_invalid');
        }

        $file = UploadedFile::getInstanceByName('file');
        if (!$file || !$file->tempName) {
            return $this->fail('chat_file_missing');
        }

        $userId = Yii::$app->user->id;
        $userName = Yii::$app->user->identity->username ?? 'Usuario';
        $user_role = (int) $encounter->subject_persona_id === (int) Yii::$app->user->getIdPersona() ? 'paciente' : 'medico';
        $viewerEsPaciente = $user_role === 'paciente';

        if ($isAsync) {
            try {
                $policySvc = new EncounterAsyncChatPolicyService();
                if ($viewerEsPaciente) {
                    $policySvc->assertPatientCanSend($encounter);
                } else {
                    $policySvc->assertStaffCanSend($encounter);
                }
                (new UploadEncounterAsyncChat())->assertUploadAllowed(
                    $encounter,
                    $messageType,
                    $file,
                    $viewerEsPaciente
                );
            } catch (\InvalidArgumentException $e) {
                return $this->fail('chat_send_not_allowed', ['detail' => $e->getMessage()]);
            }
        }

        $ext = $file->getExtension() ?: pathinfo($file->name, PATHINFO_EXTENSION);
        if (!$ext) {
            $ext = 'bin';
        }
        $filename = sprintf('%s_%s.%s', date('YmdHis'), uniqid(), $ext);

        $basePath = Yii::getAlias('@frontend/web/uploads/consulta_chat/' . $encounterId);
        if (!is_dir($basePath)) {
            if (!@mkdir($basePath, 0755, true)) {
                Yii::error('No se pudo crear directorio: ' . $basePath);

                return $this->fail('chat_file_store_failed');
            }
        }

        $relativePath = 'uploads/consulta_chat/' . $encounterId . '/' . $filename;
        $fullPath = Yii::getAlias('@frontend/web') . '/' . $relativePath;

        if (!$file->saveAs($fullPath)) {
            return $this->fail('chat_file_store_failed');
        }

        $chatMessage = new ConsultaChatMessage();
        $chatMessage->encounter_id = $encounterId;
        $chatMessage->user_id = $userId;
        $chatMessage->user_name = $userName;
        $chatMessage->user_role = $user_role;
        $chatMessage->content = $relativePath;
        $chatMessage->message_type = $messageType;

        if (!$chatMessage->save()) {
            @unlink($fullPath);

            return $this->fail('chat_message_save_failed', ['detail' => implode(', ', $chatMessage->getFirstErrors())]);
        }

        if ($user_role === 'paciente' && $encounter->parent_type === Encounter::PARENT_SOLICITUD_ASYNC) {
            try {
                (new EncounterAsyncInboxPriorityAgent())->onPacienteMensaje($encounter);
            } catch (\Throwable $e) {
                Yii::warning('Prioridad async mensaje paciente: ' . $e->getMessage(), 'consulta-async-prioridad');
            }
            try {
                (new EncounterAsyncChatPolicyService())->maybeAutoCloseAfterPatientMessage($encounter);
            } catch (\Throwable $e) {
                Yii::warning('Auto-cierre async: ' . $e->getMessage(), 'consulta-async-lifecycle');
            }
        }

        $contentUrl = SecureMediaService::absoluteApiUrl(
            SecureMediaService::SCOPE_CONSULTA_CHAT,
            $encounterId,
            $relativePath
        );

        return $this->ok([
            'id' => $chatMessage->id,
            'content' => $contentUrl,
            'user_id' => $chatMessage->user_id,
            'user_name' => $chatMessage->user_name,
            'user_role' => $chatMessage->user_role,
            'message_type' => $chatMessage->message_type,
            'created_at' => $chatMessage->created_at,
            'is_read' => $chatMessage->is_read,
        ], ['message' => 'Archivo enviado']);
    }

    public function actionEstado($id)
    {
        $encounterId = (int) $id;
        $encounter = $this->assertEncounterAccess($encounterId);

        $unreadCount = ConsultaChatMessage::find()
            ->where(['encounter_id' => $encounterId, 'is_read' => false])
            ->count();

        return $this->ok([
            'encounter_id' => $encounterId,
            'unread_count' => $unreadCount,
            'is_online' => true,
            'last_activity' => $encounter->updated_at ?? $encounter->created_at,
        ]);
    }

    /**
     * @param array<string, mixed> $body
     */
    protected function resolveEncounterIdFromBody(array $body): int
    {
        $id = $body['encounter_id'] ?? null;

        return $id ? (int) $id : 0;
    }

    private const UPLOAD_MESSAGE_TYPES = ['imagen', 'audio', 'video', 'documento'];
}
