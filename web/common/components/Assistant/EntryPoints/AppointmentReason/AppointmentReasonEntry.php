<?php

namespace common\components\Assistant\EntryPoints\AppointmentReason;

use common\components\Clinical\Service\AppointmentReasonWindowService;
use common\components\Core\Permission\Domain\EncounterDomainAccessService;
use common\components\Person\Representation\Enum\RepresentationPermission;
use common\models\Clinical\Encounter;
use common\models\ConsultaMotivosMessage;
use Yii;

/**
 * Motivo de consulta del paciente (app paciente, antes de la atención).
 * Solo persistencia en el chat; la IA corre en lote ~1 min antes del turno ({@see AppointmentReasonBatchService}).
 */
final class AppointmentReasonEntry
{
    /**
     * POST /api/v1/motivos-consulta/enviar
     *
     * @return array<string, mixed>
     */
    public static function enviarTexto(int $encounterId, string $message, int $userId, string $userName): array
    {
        $message = trim($message);
        if ($message === '') {
            return [
                'success' => false,
                'message' => 'El mensaje no puede estar vacío',
                'data' => null,
            ];
        }

        [$encounter, $err] = self::requireEncounterAccess($encounterId);
        if ($err !== null) {
            return $err;
        }
        unset($encounter);

        $windowErr = self::windowClosedResponse($encounterId);
        if ($windowErr !== null) {
            return $windowErr;
        }

        $msg = new ConsultaMotivosMessage();
        $msg->encounter_id = $encounterId;
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
                'encounter_id' => (int) $encounterId,
                'consulta_id' => (int) $encounterId,
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
    private static function requireEncounterAccess(int $encounterId): array
    {
        $encounter = Encounter::findOne($encounterId);
        if (!$encounter) {
            Yii::$app->response->statusCode = 404;

            return [null, ['success' => false, 'message' => 'Encounter no encontrado', 'data' => null]];
        }
        if (!EncounterDomainAccessService::canAccess($encounter, 'Encounter.access', RepresentationPermission::CLINICAL_MOTIVOS)) {
            Yii::$app->response->statusCode = 403;

            return [null, ['success' => false, 'message' => 'No tiene permiso para acceder a este encounter', 'data' => null]];
        }

        return [$encounter, null];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function windowClosedResponse(int $encounterId): ?array
    {
        if (AppointmentReasonWindowService::isInputOpen($encounterId)) {
            return null;
        }

        Yii::$app->response->statusCode = 403;

        return [
            'success' => false,
            'message' => 'El plazo para cargar motivos finalizó '
                . AppointmentReasonWindowService::minutesBeforeClose()
                . ' minuto(s) antes del turno. El médico verá el resumen al iniciar la consulta.',
            'data' => AppointmentReasonWindowService::apiState($encounterId),
        ];
    }
}
