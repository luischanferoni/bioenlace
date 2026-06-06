<?php

namespace common\components\Core\DataAccess;

/**
 * Restricciones de scope inyectadas por checkers (no negociables en la query).
 */
final class ScopeConstraint
{
    public ?int $idEfector = null;

    public ?int $idPersona = null;

    /**
     * @return array<string, int>
     */
    public function toLogContext(): array
    {
        $out = [];
        if ($this->idEfector !== null && $this->idEfector > 0) {
            $out['id_efector'] = $this->idEfector;
        }
        if ($this->idPersona !== null && $this->idPersona > 0) {
            $out['id_persona'] = $this->idPersona;
        }

        return $out;
    }
}
