<?php

namespace common\components\Platform\Core\Service\Notificaciones;

/**
 * Contrato para proyectar recibos push genéricos hacia dominio (p. ej. eventos canónicos).
 */
interface PushNotificationReceiptProjectorInterface
{
    /**
     * @param array{
     *   notification: \common\models\Platform\PersonaNotificacion,
     *   interaction: \common\models\Platform\PersonaNotificacionInteraccion,
     *   interaction_type: string,
     *   id_user: int|null,
     *   actor_type: string|null
     * } $ctx
     */
    public function project(array $ctx): void;
}
