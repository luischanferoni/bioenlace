<?php

namespace common\components\Domain\Scheduling\Service\BehaviorProfile;

/**
 * Comando para registrar un evento canónico de turno (idempotente por clave).
 */
final class TurnoCanonicalEventCommand
{
    public int $idTurno;
    public int $idPersona;
    public string $eventCode;
    public string $actorType;
    public string $attributionQuality;
    public string $idempotencyKey;
    public ?int $idUser = null;
    public ?string $channel = null;
    public ?string $origin = null;
    public ?string $motivoNormalizado = null;
    public ?string $occurredAt = null;
    public ?int $correctedEventId = null;
    public ?int $idTurnoRelacionado = null;
    public ?string $relatedTurnoRole = null;
    /** @var array<string, mixed> */
    public array $meta = [];
    /** Tipo legacy opcional para UI histórica; si null se deriva del event_code. */
    public ?string $legacyTipoEvento = null;

    /**
     * @param array<string, mixed> $meta
     */
    public static function create(
        int $idTurno,
        int $idPersona,
        string $eventCode,
        string $actorType,
        string $idempotencyKey,
        string $attributionQuality = \common\models\TurnoEventoAudit::QUALITY_NATIVE,
        ?int $idUser = null,
        ?string $channel = null,
        ?string $origin = null,
        ?string $motivoNormalizado = null,
        ?string $occurredAt = null,
        array $meta = [],
        ?string $legacyTipoEvento = null,
        ?int $correctedEventId = null,
        ?int $idTurnoRelacionado = null,
        ?string $relatedTurnoRole = null
    ): self {
        $c = new self();
        $c->idTurno = $idTurno;
        $c->idPersona = $idPersona;
        $c->eventCode = $eventCode;
        $c->actorType = $actorType;
        $c->idempotencyKey = $idempotencyKey;
        $c->attributionQuality = $attributionQuality;
        $c->idUser = $idUser;
        $c->channel = $channel;
        $c->origin = $origin;
        $c->motivoNormalizado = $motivoNormalizado;
        $c->occurredAt = $occurredAt;
        $c->meta = $meta;
        $c->legacyTipoEvento = $legacyTipoEvento;
        $c->correctedEventId = $correctedEventId;
        $c->idTurnoRelacionado = $idTurnoRelacionado;
        $c->relatedTurnoRole = $relatedTurnoRole;

        return $c;
    }
}
