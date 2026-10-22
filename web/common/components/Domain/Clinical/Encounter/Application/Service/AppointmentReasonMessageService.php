<?php

namespace common\components\Domain\Clinical\Encounter\Application\Service;

use common\components\Domain\Clinical\Encounter\Application\Service\SecureMediaService;
use common\components\Platform\Core\Http\Contract\ApiException;
use common\components\Platform\Ai\SpeechToText\DeviceSttQualityAssessor;
use common\components\Platform\Ai\SpeechToText\SttConfigService;
use common\models\Clinical\EncounterReasonMessage;
use Yii;

/**
 * Persistencia del chat pre-consulta (texto / imagen / audio).
 * Auth HTTP en el controller; aquí ventana + guardado. Los fallos lanzan {@see ApiException}.
 */
final class AppointmentReasonMessageService
{
    public const UPLOAD_MESSAGE_TYPES = ['imagen', 'audio'];

    /**
     * @return array<string, mixed> Mensaje persistido.
     * @throws ApiException
     */
    public function sendText(int $encounterId, string $message, int $userId, string $userName): array
    {
        $message = trim($message);
        if ($message === '') {
            throw new ApiException('reason_message_empty');
        }

        $this->assertWindowOpen($encounterId);

        $msg = new EncounterReasonMessage();
        $msg->encounter_id = $encounterId;
        $msg->user_id = $userId;
        $msg->user_name = $userName !== '' ? $userName : 'Paciente';
        $msg->texto = $message;
        $msg->message_type = EncounterReasonMessage::TYPE_TEXTO;

        if (!$msg->save()) {
            throw new ApiException('reason_message_save_failed', ['detail' => implode(', ', $msg->getFirstErrors())]);
        }

        return $this->payloadForText($msg);
    }

    /**
     * Guarda el archivo en disco y persiste el mensaje media.
     *
     * @return array<string, mixed>
     * @throws ApiException
     */
    public function sendMedia(
        int $encounterId,
        string $messageType,
        string $sourceTempPath,
        string $extension,
        int $userId,
        string $userName,
        string $transcript = '',
        array $sttMeta = []
    ): array {
        if (!in_array($messageType, self::UPLOAD_MESSAGE_TYPES, true)) {
            throw new ApiException('reason_media_type_invalid');
        }
        if ($sourceTempPath === '' || !is_file($sourceTempPath)) {
            throw new ApiException('reason_file_missing');
        }

        $this->assertWindowOpen($encounterId);

        $ext = trim($extension, '.');
        if ($ext === '') {
            $ext = $messageType === 'audio' ? 'm4a' : 'jpg';
        }
        $filename = sprintf('%s_%s.%s', date('YmdHis'), uniqid(), $ext);

        $basePath = Yii::getAlias('@frontend/web/uploads/motivos_consulta/' . $encounterId);
        if (!is_dir($basePath)) {
            if (!@mkdir($basePath, 0755, true)) {
                Yii::error('No se pudo crear directorio: ' . $basePath);

                throw new ApiException('reason_file_store_failed');
            }
        }

        $relativePath = 'uploads/motivos_consulta/' . $encounterId . '/' . $filename;
        $fullPath = Yii::getAlias('@frontend/web') . '/' . $relativePath;

        if (!@move_uploaded_file($sourceTempPath, $fullPath) && !@rename($sourceTempPath, $fullPath)) {
            if (!@copy($sourceTempPath, $fullPath)) {
                throw new ApiException('reason_file_store_failed');
            }
            @unlink($sourceTempPath);
        }

        $msg = new EncounterReasonMessage();
        $msg->encounter_id = $encounterId;
        $msg->user_id = $userId;
        $msg->user_name = $userName !== '' ? $userName : 'Paciente';
        $msg->texto = $relativePath;
        $msg->message_type = $messageType;
        if ($messageType === EncounterReasonMessage::TYPE_AUDIO && trim($transcript) !== '') {
            $transcript = trim($transcript);
            $sttMeta = array_intersect_key($sttMeta, array_flip([
                'provenance',
                'confidence',
                'duration_ms',
                'engine',
                'locale',
                'final_result',
                'on_device',
                'error',
                'client_edit_ratio',
            ]));
            $deviceEnabled = SttConfigService::isDeviceEnabled();
            $serverEnabled = SttConfigService::isServerEnabled();
            $quality = $deviceEnabled
                ? DeviceSttQualityAssessor::assess($transcript, $sttMeta, 'motivos_consulta')
                : ['ok' => false, 'needs_server' => true, 'reasons' => ['dispositivo_no_habilitado']];
            $sttMeta['device_quality'] = $quality;
            $sttMeta['device_transcript_accepted'] = ($sttMeta['provenance'] ?? null) === 'device'
                && DeviceSttQualityAssessor::isDeviceTranscriptAccepted(
                    $transcript,
                    $quality,
                    $deviceEnabled,
                    $serverEnabled
                );
            $msg->transcript = $transcript;
            $msg->stt_meta_json = json_encode(
                $sttMeta,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
        }

        if (!$msg->save()) {
            @unlink($fullPath);

            throw new ApiException('reason_message_save_failed', ['detail' => implode(', ', $msg->getFirstErrors())]);
        }

        $contentUrl = SecureMediaService::absoluteApiUrl(
            SecureMediaService::SCOPE_ENCOUNTER_REASONS,
            $encounterId,
            $relativePath
        );

        return [
            'id' => $msg->id,
            'encounter_id' => (int) $encounterId,
            'content' => $contentUrl,
            'user_id' => $msg->user_id,
            'user_name' => $msg->user_name,
            'message_type' => $msg->message_type,
            'created_at' => $msg->created_at,
            'transcript' => $msg->transcript,
        ];
    }

    /**
     * @throws ApiException
     */
    private function assertWindowOpen(int $encounterId): void
    {
        if (AppointmentReasonWindowService::isInputOpen($encounterId)) {
            return;
        }

        throw new ApiException(
            'reason_window_closed',
            ['minutes' => AppointmentReasonWindowService::minutesBeforeClose()],
            [],
            null,
            AppointmentReasonWindowService::apiState($encounterId)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadForText(EncounterReasonMessage $msg): array
    {
        return [
            'id' => $msg->id,
            'encounter_id' => (int) $msg->encounter_id,
            'content' => $msg->texto,
            'user_id' => $msg->user_id,
            'user_name' => $msg->user_name,
            'message_type' => $msg->message_type,
            'created_at' => $msg->created_at,
        ];
    }
}
