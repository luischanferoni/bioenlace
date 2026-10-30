<?php

namespace common\components\Domain\Organization\Efector\Application\UseCase;

use common\components\Domain\Organization\Pes\Application\UseCase\EnsurePesAssignment;
use common\components\Platform\Core\Service\Push\PushNotificationSender;
use common\models\Organization\Efector;
use common\models\Person\Persona;
use Yii;

/**
 * El AdminEfector agrega un profesional (Persona) a un servicio de su efector
 * y le avisa por push a la app del personal.
 *
 * La asignación es idempotente (reusa EnsurePesAssignment) y la notificación
 * viaja por el canal FCM existente (PushNotificationSender).
 */
final class AddProfessionalToEfector
{
    /**
     * @return array{id_persona: int, id_efector: int, id_servicio: int}
     */
    public function add(
        int $idPersona,
        int $idEfector,
        int $idServicio,
        ?int $actingUserId = null,
        ?string $cuil = null
    ): array {
        $persona = Persona::findOne(['id_persona' => $idPersona]);
        if ($persona === null) {
            throw new \InvalidArgumentException('Persona inexistente.');
        }

        $efector = Efector::findOne(['id_efector' => $idEfector, 'deleted_at' => null]);
        if ($efector === null) {
            throw new \InvalidArgumentException('Efector inexistente.');
        }

        $actingUserId = $actingUserId !== null && $actingUserId > 0
            ? $actingUserId
            : (int) (Yii::$app->has('user', true) ? (Yii::$app->user->id ?? 0) : 0);

        EnsurePesAssignment::ensurePersonaServicioEnEfector(
            $idPersona,
            $idEfector,
            $idServicio,
            $actingUserId > 0 ? $actingUserId : null,
            $cuil
        );

        try {
            (new PushNotificationSender())->sendToPersona(
                $idPersona,
                [
                    'type' => 'profesional_agregado',
                    'id_efector' => $idEfector,
                    'id_servicio' => $idServicio,
                ],
                'Te agregaron a un efector',
                'Ya podés operar en ' . $efector->nombre . '. Abrí la app para configurar tu sesión.',
                true,
                ['idempotency_key' => 'profesional_agregado:' . $idPersona . ':' . $idEfector . ':' . $idServicio]
            );
        } catch (\Throwable $e) {
            Yii::warning('No se pudo enviar push de alta profesional: ' . $e->getMessage(), 'billing.signup');
        }

        return [
            'id_persona' => $idPersona,
            'id_efector' => $idEfector,
            'id_servicio' => $idServicio,
        ];
    }
}
