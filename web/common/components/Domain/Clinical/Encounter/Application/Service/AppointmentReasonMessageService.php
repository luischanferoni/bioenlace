<?php

namespace common\components\Domain\Clinical\Encounter\Application\Service;

use common\components\Domain\Clinical\Encounter\Application\Service\SecureMediaService;
use common\models\Clinical\AppointmentReasonMessage;
use Yii;

/**
 * Persistencia del chat pre-consulta (texto / imagen / audio).
 * Auth HTTP en el controller; aquí ventana + guardado.
 */
final class AppointmentReasonMessageService
{
    public const UPLOAD_MESSAGE_TYPES = ['imagen', 'audio'];

    /**
     * @return array{success: bool, message: string, data: mixed, statusCode?: int}
     */
    public function sendText(int $encounterId, string $message, int $userId, string $userName): array
    {
        $message = trim($message);
        if ($message === '') {
            return $this->fail('El mensaje no puede estar vacío', null, 400);
        }

        $windowErr = $this->windowClosedResponse($encounterId);
        if ($windowErr !== null) {
            return $windowErr;
        }

        $msg = new AppointmentReasonMessage();
        $msg->encounter_id = $encounterId;
        $msg->user_id = $userId;
        $msg->user_name = $userName !== '' ? $userName : 'Paciente';
        $msg->texto = $message;
        $msg->message_type = AppointmentReasonMessage::TYPE_TEXTO;

        if (!$msg->save()) {
            return $this->fail(
                'Error guardando mensaje: ' . implode(', ', $msg->getFirstErrors()),
                null,
                400
            );
        }

        return $this->ok('Mensaje enviado exitosamente', $this->payloadForText($msg));
    }

    /**
     * Guarda el archivo en disco y persiste el mensaje media.
     *
     * @return array{success: bool, message: string, data: mixed, statusCode?: int}
     */
    public function sendMedia(
        int $encounterId,
        string $messageType,
        string $sourceTempPath,
        string $extension,
        int $userId,
        string $userName
    ): array {
        if (!in_array($messageType, self::UPLOAD_MESSAGE_TYPES, true)) {
            return $this->fail('message_type debe ser: imagen o audio', null, 400);
        }
        if ($sourceTempPath === '' || !is_file($sourceTempPath)) {
            return $this->fail('Debe enviar un archivo en el campo "file"', null, 400);
        }

        $windowErr = $this->windowClosedResponse($encounterId);
        if ($windowErr !== null) {
            return $windowErr;
        }

        $ext = trim($extension, '.');
        if ($ext === '') {
            $ext = $messageType === 'audio' ? 'm4a' : 'jpg';
        }
        $filename = sprintf('%s_%s.%s', date('YmdHis'), uniqid(), $ext);

        $basePath = Yii::getAlias('@frontend/web/uploads/motivos_consulta/' . $encounterId);
        if (!is_dir($basePath)) {
            if (!@mkdir($basePath, 0755, true)) {
                Yii::error('No se pudo crear directorio: ' . $basePath);

                return $this->fail('Error al guardar el archivo', null, 500);
            }
        }

        $relativePath = 'uploads/motivos_consulta/' . $encounterId . '/' . $filename;
        $fullPath = Yii::getAlias('@frontend/web') . '/' . $relativePath;

        if (!@move_uploaded_file($sourceTempPath, $fullPath) && !@rename($sourceTempPath, $fullPath)) {
            if (!@copy($sourceTempPath, $fullPath)) {
                return $this->fail('Error al guardar el archivo', null, 500);
            }
            @unlink($sourceTempPath);
        }

        $msg = new AppointmentReasonMessage();
        $msg->encounter_id = $encounterId;
        $msg->user_id = $userId;
        $msg->user_name = $userName !== '' ? $userName : 'Paciente';
        $msg->texto = $relativePath;
        $msg->message_type = $messageType;

        if (!$msg->save()) {
            @unlink($fullPath);

            return $this->fail(
                'Error guardando mensaje: ' . implode(', ', $msg->getFirstErrors()),
                null,
                400
            );
        }

        $contentUrl = SecureMediaService::absoluteApiUrl(
            SecureMediaService::SCOPE_MOTIVOS_CONSULTA,
            $encounterId,
            $relativePath
        );

        return $this->ok('Archivo enviado exitosamente', [
            'id' => $msg->id,
            'encounter_id' => (int) $encounterId,
            'consulta_id' => (int) $encounterId,
            'content' => $contentUrl,
            'user_id' => $msg->user_id,
            'user_name' => $msg->user_name,
            'message_type' => $msg->message_type,
            'created_at' => $msg->created_at,
        ]);
    }

    /**
     * @return array{success: bool, message: string, data: mixed, statusCode?: int}|null
     */
    private function windowClosedResponse(int $encounterId): ?array
    {
        if (AppointmentReasonWindowService::isInputOpen($encounterId)) {
            return null;
        }

        return $this->fail(
            'El plazo para cargar motivos finalizó '
            . AppointmentReasonWindowService::minutesBeforeClose()
            . ' minuto(s) antes del turno. El médico verá el resumen al iniciar la consulta.',
            AppointmentReasonWindowService::apiState($encounterId),
            403
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadForText(AppointmentReasonMessage $msg): array
    {
        return [
            'id' => $msg->id,
            'encounter_id' => (int) $msg->encounter_id,
            'consulta_id' => (int) $msg->encounter_id,
            'content' => $msg->texto,
            'user_id' => $msg->user_id,
            'user_name' => $msg->user_name,
            'message_type' => $msg->message_type,
            'created_at' => $msg->created_at,
        ];
    }

    /**
     * @param mixed $data
     * @return array{success: bool, message: string, data: mixed, statusCode: int}
     */
    private function fail(string $message, $data, int $statusCode): array
    {
        return [
            'success' => false,
            'message' => $message,
            'data' => $data,
            'statusCode' => $statusCode,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    private function ok(string $message, array $data): array
    {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];
    }
}
