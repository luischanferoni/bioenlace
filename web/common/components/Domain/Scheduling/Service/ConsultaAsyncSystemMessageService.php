<?php

namespace common\components\Domain\Scheduling\Service;

use common\components\Domain\Clinical\Enum\EncounterStatus;
use common\models\Clinical\Encounter;
use common\models\ConsultaChatMessage;

/**
 * Mensajes de sistema en chat async (message_type sistema).
 */
final class ConsultaAsyncSystemMessageService
{
    public function post(Encounter $encounter, string $content): void
    {
        $content = trim($content);
        if ($content === '') {
            return;
        }

        $chatMessage = new ConsultaChatMessage();
        $chatMessage->encounter_id = (int) $encounter->id;
        $chatMessage->user_id = 0;
        $chatMessage->user_name = 'Sistema';
        $chatMessage->user_role = 'sistema';
        $chatMessage->content = $content;
        $chatMessage->message_type = 'sistema';
        $chatMessage->save(false);
    }

    public function postTemplate(Encounter $encounter, string $templateKey, array $vars = []): void
    {
        $catalog = new ConsultaAsyncChatPolicyCatalogService();
        $tpl = $catalog->systemMessage($templateKey);
        if ($tpl === '') {
            return;
        }
        foreach ($vars as $key => $value) {
            $tpl = str_replace('{' . $key . '}', (string) $value, $tpl);
        }
        $this->post($encounter, $tpl);
    }

    public function tieneRespuestaStaff(int $encounterId): bool
    {
        return ConsultaChatMessage::find()
            ->where(['encounter_id' => $encounterId])
            ->andWhere(['user_role' => ['medico', 'enfermeria']])
            ->andWhere(['not in', 'message_type', ['sistema']])
            ->exists();
    }

    public function countMensajesPaciente(int $encounterId): int
    {
        return (int) ConsultaChatMessage::find()
            ->where(['encounter_id' => $encounterId, 'user_role' => 'paciente'])
            ->andWhere(['not in', 'message_type', ['sistema']])
            ->count();
    }
}
