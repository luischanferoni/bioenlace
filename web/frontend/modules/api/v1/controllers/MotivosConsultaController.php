<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\Response;
use yii\web\UploadedFile;
use common\components\Assistant\EntryPoints\AppointmentReason\AppointmentReasonEntry;
use common\components\Clinical\Service\AppointmentReasonWindowService;
use common\components\Clinical\Service\EncounterAccessService;
use common\models\Clinical\Encounter;
use common\models\ConsultaMotivosMessage;

/**
 * API motivos de consulta (mensajes, envío, subida de archivos).
 *
 * La lógica se implementa directamente aquí, sin usar el controlador del frontend.
 */
class MotivosConsultaController extends BaseController
{
    public $enableCsrfValidation = false;

    /**
     * GET /api/v1/motivos-consulta/mensajes/{id} — {id} = encounter_id (alias legacy en clientes).
     */
    public function actionListarMensajes($id)
    {
        $encounterId = (int) $id;
        [$encounter, $err] = $this->requireEncounterAccess($encounterId);
        if ($err !== null) {
            return $err;
        }
        unset($encounter);

        $messages = ConsultaMotivosMessage::find()
            ->where(['encounter_id' => $encounterId])
            ->orderBy(['created_at' => SORT_ASC])
            ->all();

        $baseUrl = Yii::$app->request->hostInfo . (Yii::getAlias('@web') ?: '');
        $formattedMessages = ConsultaMotivosMessage::serializeForApi($messages, $baseUrl);

        return [
            'success' => true,
            'message' => 'Mensajes obtenidos exitosamente',
            'data' => array_merge(
                [
                    'messages' => $formattedMessages,
                    'encounter_id' => $encounterId,
                    'consulta_id' => $encounterId,
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

        $message = $body['message'] ?? null;
        if ($message === null || $message === '') {
            return ['success' => false, 'message' => 'El mensaje no puede estar vacío', 'data' => null];
        }

        $userId = (int) Yii::$app->user->id;
        $userName = Yii::$app->user->identity->username ?? 'Paciente';

        return AppointmentReasonEntry::enviarTexto($encounterId, (string) $message, $userId, $userName);
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

        [$encounter, $err] = $this->requireEncounterAccess($encounterId);
        if ($err !== null) {
            return $err;
        }
        unset($encounter);

        if (!AppointmentReasonWindowService::isInputOpen($encounterId)) {
            Yii::$app->response->statusCode = 403;

            return [
                'success' => false,
                'message' => 'El plazo para cargar motivos finalizó '
                    . AppointmentReasonWindowService::minutesBeforeClose()
                    . ' minuto(s) antes del turno.',
                'data' => AppointmentReasonWindowService::apiState($encounterId),
            ];
        }

        $messageType = Yii::$app->request->post('message_type', 'imagen');
        if (!in_array($messageType, self::UPLOAD_MESSAGE_TYPES, true)) {
            return ['success' => false, 'message' => 'message_type debe ser: imagen o audio', 'data' => null];
        }

        $file = UploadedFile::getInstanceByName('file');
        if (!$file || !$file->tempName) {
            return ['success' => false, 'message' => 'Debe enviar un archivo en el campo "file"', 'data' => null];
        }

        $ext = $file->getExtension() ?: pathinfo($file->name, PATHINFO_EXTENSION);
        if (!$ext) {
            $ext = $messageType === 'audio' ? 'm4a' : 'jpg';
        }
        $filename = sprintf('%s_%s.%s', date('YmdHis'), uniqid(), $ext);

        $basePath = Yii::getAlias('@frontend/web/uploads/motivos_consulta/' . $encounterId);
        if (!is_dir($basePath)) {
            if (!@mkdir($basePath, 0755, true)) {
                Yii::error('No se pudo crear directorio: ' . $basePath);
                return ['success' => false, 'message' => 'Error al guardar el archivo', 'data' => null];
            }
        }

        $relativePath = 'uploads/motivos_consulta/' . $encounterId . '/' . $filename;
        $fullPath = Yii::getAlias('@frontend/web') . '/' . $relativePath;

        if (!$file->saveAs($fullPath)) {
            return ['success' => false, 'message' => 'Error al guardar el archivo', 'data' => null];
        }

        $userId = Yii::$app->user->id;
        $userName = Yii::$app->user->identity->username ?? 'Paciente';

        $msg = new ConsultaMotivosMessage();
        $msg->encounter_id = $encounterId;
        $msg->user_id = $userId;
        $msg->user_name = $userName;
        $msg->texto = $relativePath;
        $msg->message_type = $messageType;

        if (!$msg->save()) {
            @unlink($fullPath);
            return [
                'success' => false,
                'message' => 'Error guardando mensaje: ' . implode(', ', $msg->getFirstErrors()),
                'data' => null,
            ];
        }

        $baseUrl = Yii::$app->request->hostInfo . (Yii::getAlias('@web') ?: '');
        $contentUrl = rtrim($baseUrl, '/') . '/' . ltrim($relativePath, '/');

        return [
            'success' => true,
            'message' => 'Archivo enviado exitosamente',
            'data' => [
                'id' => $msg->id,
                'encounter_id' => $encounterId,
                'consulta_id' => $encounterId,
                'content' => $contentUrl,
                'user_id' => $msg->user_id,
                'user_name' => $msg->user_name,
                'message_type' => $msg->message_type,
                'created_at' => $msg->created_at,
            ],
        ];
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

    /**
     * Paciente: `consulta.id_persona` === sesión `idPersona` ({@see JsonHttpBearerAuth}).
     * Médico: mismo contexto PES que en sesión operativa ({@see EncounterAccessService::userCanAccessEncounterApi}).
     */
    protected function canAccessEncounter(Encounter $encounter): bool
    {
        return EncounterAccessService::userCanAccessEncounterApi($encounter);
    }

    /**
     * @return array{0: Encounter|null, 1: array|null}
     */
    protected function requireEncounterAccess($encounterId)
    {
        $encounter = Encounter::findOne((int) $encounterId);
        if (!$encounter) {
            Yii::$app->response->statusCode = 404;
            return [null, ['success' => false, 'message' => 'Encounter no encontrado', 'data' => null]];
        }
        if (!$this->canAccessEncounter($encounter)) {
            Yii::$app->response->statusCode = 403;
            return [null, ['success' => false, 'message' => 'No tiene permiso para acceder a este encounter', 'data' => null]];
        }
        return [$encounter, null];
    }

    private const UPLOAD_MESSAGE_TYPES = ['imagen', 'audio'];
}
