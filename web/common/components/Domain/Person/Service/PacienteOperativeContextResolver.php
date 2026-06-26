<?php

namespace common\components\Domain\Person\Service;

use common\models\Person\PersonaPacienteContexto;
use Yii;

/**
 * Resuelve el contexto operativo del actor autenticado (cuenta logueada).
 */
final class PacienteOperativeContextResolver
{
    public function forActorPersonaId(int $idPersona): ?PersonaPacienteContexto
    {
        if ($idPersona <= 0) {
            return null;
        }

        return PersonaPacienteContexto::findOne($idPersona);
    }

    public function forCurrentActor(): ?PersonaPacienteContexto
    {
        if (!Yii::$app->has('user')) {
            return null;
        }
        $idPersona = (int) Yii::$app->user->getIdPersona();

        return $this->forActorPersonaId($idPersona);
    }
}
