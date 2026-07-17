<?php

namespace common\components\Domain\Scheduling\Service;

use common\models\Clinical\Encounter;
use common\models\ConsultaChatMessage;
use common\models\Persona;
use Yii;

/**
 * Mensaje inicial del paciente en el chat al crear solicitud async.
 */
final class ConsultaAsyncInitialChatService
{
    public function seedMensajePaciente(Encounter $encounter, int $idPersona, string $mensaje): void
    {
        $mensaje = trim($mensaje);
        if ($mensaje === '') {
            return;
        }

        if (ConsultaChatMessage::find()->where(['encounter_id' => (int) $encounter->id])->exists()) {
            return;
        }

        $persona = Persona::findOne(['id_persona' => $idPersona]);
        $userId = (int) ($persona->id_user ?? Yii::$app->user->id ?? 0);
        if ($userId <= 0) {
            return;
        }

        $userName = 'Paciente';
        if ($persona !== null) {
            $userName = $persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N) ?: $userName;
        } elseif (Yii::$app->user->identity !== null) {
            $userName = (string) (Yii::$app->user->identity->username ?? $userName);
        }

        $chatMessage = new ConsultaChatMessage();
        $chatMessage->encounter_id = (int) $encounter->id;
        $chatMessage->user_id = $userId;
        $chatMessage->user_name = $userName;
        $chatMessage->user_role = 'paciente';
        $chatMessage->content = $mensaje;
        $chatMessage->message_type = 'texto';
        $chatMessage->save(false);
    }
}
