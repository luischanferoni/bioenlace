<?php

namespace common\components\Domain\Clinical\Encounter\Domain\Model;

/**
 * Aggregate root Encounter (andamiaje Fase C).
 *
 * Las reglas de ciclo de vida viven hoy en
 * {@see \common\components\Domain\Clinical\Encounter\Application\EncounterLifecycleService}.
 * Migrar invariantes aquí de a una; este stub no se usa en runtime todavía.
 */
final class Encounter
{
    /** @var EncounterId */
    private $id;

    private function __construct(EncounterId $id)
    {
        $this->id = $id;
    }

    public static function reconstitute(EncounterId $id): self
    {
        return new self($id);
    }

    public function id(): EncounterId
    {
        return $this->id;
    }
}
