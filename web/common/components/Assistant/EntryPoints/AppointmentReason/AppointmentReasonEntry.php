<?php

namespace common\components\Assistant\EntryPoints\AppointmentReason;

use common\components\Clinical\Service\EncounterAccessService;
use common\models\Clinical\Encounter;
use common\models\ConsultaMotivosMessage;
use Yii;

/**
 * Motivo de consulta del paciente (pre-turno / app paciente).
 * Sin preprocess del chat asistente: persistencia y, a futuro, extracción estructurada propia.
 */
final class AppointmentReasonEntry
{
    /**
     * POST /api/v1/motivos-consulta/enviar
     *
     * @return array<string, mixed>
     */
    public static function enviarTexto(int $consultaId, string $message, int $userId, string $userName): array
    {
        $message = trim($message);
        if ($message === '') {
            return [
                'success' => false,
                'message' => 'El mensaje no puede estar vacío',
                'data' => null,
            ];
        }

        [$consulta, $err] = self::requireConsultaAccess($consultaId);
        if ($err !== null) {
            return $err;
        }
        unset($consulta);

        $msg = new ConsultaMotivosMessage();
        $msg->consulta_id = $consultaId;
        $msg->user_id = $userId;
        $msg->user_name = $userName !== '' ? $userName : 'Paciente';
        $msg->texto = $message;
        $msg->message_type = ConsultaMotivosMessage::TYPE_TEXTO;

        if (!$msg->save()) {
            return [
                'success' => false,
                'message' => 'Error guardando mensaje: ' . implode(', ', $msg->getFirstErrors()),
                'data' => null,
            ];
        }

        return [
            'success' => true,
            'message' => 'Mensaje enviado exitosamente',
            'data' => [
                'id' => $msg->id,
                'content' => $msg->texto,
                'user_id' => $msg->user_id,
                'user_name' => $msg->user_name,
                'message_type' => $msg->message_type,
                'created_at' => $msg->created_at,
            ],
        ];
    }

    /**
     * @return array{0: Encounter|null, 1: array<string, mixed>|null}
     */
    private static function requireConsultaAccess(int $encounterId): array
    {
        $encounter = Encounter::findOne($encounterId);
        if (!$encounter) {
            Yii::$app->response->statusCode = 404;

            return [null, ['success' => false, 'message' => 'Encounter no encontrado', 'data' => null]];
        }
        if (!EncounterAccessService::userCanAccessEncounterApi($encounter)) {
            Yii::$app->response->statusCode = 403;

            return [null, ['success' => false, 'message' => 'No tiene permiso para acceder a este encounter', 'data' => null]];
        }

        return [$encounter, null];
    }
}
