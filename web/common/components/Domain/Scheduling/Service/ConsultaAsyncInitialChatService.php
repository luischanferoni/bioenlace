<?php

namespace common\components\Domain\Scheduling\Service;

use common\models\Clinical\Encounter;
use common\models\Persona;
use Yii;

/**
 * Mensaje inicial del paciente en el chat al crear solicitud async.
 */
final class ConsultaAsyncInitialChatService
{
    /**
     * @param array<string, mixed> $meta
     */
    public function seedMensajePaciente(Encounter $encounter, int $idPersona, string $mensaje, array $meta = []): void
    {
        $mensaje = trim($mensaje);
        if ($mensaje === '') {
            return;
        }

        if (\common\models\ConsultaChatMessage::find()->where(['encounter_id' => (int) $encounter->id])->exists()) {
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

        $catalog = new ConsultaAsyncChatPolicyCatalogService();
        $categoria = $catalog->solicitudCategoriaFromMeta($meta);

        $chatMessage = new \common\models\ConsultaChatMessage();
        $chatMessage->encounter_id = (int) $encounter->id;
        $chatMessage->user_id = $userId;
        $chatMessage->user_name = $userName;
        $chatMessage->user_role = 'paciente';
        $chatMessage->content = $mensaje;
        $chatMessage->message_type = 'texto';
        $chatMessage->solicitud_categoria = $categoria;
        $chatMessage->save(false);
    }
}
