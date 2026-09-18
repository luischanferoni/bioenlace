<?php

namespace common\components\Domain\Clinical\Encounter\Domain\Model;

/**
 * Aggregate de narrativa pre-consulta (chat) que alimenta Condition CC.
 *
 * Persistencia: {@see \common\models\Clinical\AppointmentReasonMessage}.
 * Orquestación: {@see \common\components\Domain\Clinical\Encounter\Application\AppointmentReasonMessageService}.
 *
 * Stub — reglas de ventana/media siguen en Application; no hidratar mensajes aquí aún.
 */
final class AppointmentReason
{
    /** @var EncounterId */
    private $encounterId;

    private function __construct(EncounterId $encounterId)
    {
        $this->encounterId = $encounterId;
    }

    public static function forEncounter(EncounterId $encounterId): self
    {
        return new self($encounterId);
    }

    public function encounterId(): EncounterId
    {
        return $this->encounterId;
    }
}
