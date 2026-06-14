<?php

namespace common\components\Person\DataAccess\Scope;

use common\components\Core\DataAccess\PermissionContext;
use common\components\Core\DataAccess\QuerySpec;
use common\components\Core\DataAccess\ScopeCheckerInterface;
use common\components\Core\DataAccess\ScopeConstraint;
use common\models\Person\Persona;
use Yii;

/**
 * Solo datos de la persona autenticada (paciente / usuario consultando sobre sí mismo).
 *
 * Identidad desde sesión ({@see getIdPersona()}); documento del spec solo como refuerzo.
 */
final class PermitirParaSiMismoScopeChecker implements ScopeCheckerInterface
{
    public function assertAndResolve(QuerySpec $spec, PermissionContext $ctx): ScopeConstraint
    {
        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona <= 0) {
            throw new \InvalidArgumentException('Se requiere persona autenticada para consultar datos propios.');
        }

        if ($spec->requestedIdPersona !== null && $spec->requestedIdPersona > 0
            && $spec->requestedIdPersona !== $idPersona) {
            throw new \InvalidArgumentException('No puede consultar datos de otra persona.');
        }

        if ($spec->requestedDocumento !== null && $spec->requestedDocumento !== '') {
            $persona = Persona::findOne($idPersona);
            if ($persona === null) {
                throw new \InvalidArgumentException('Persona de sesión no encontrada.');
            }
            $docSesion = trim((string) ($persona->documento ?? ''));
            if ($docSesion === '' || $docSesion !== trim($spec->requestedDocumento)) {
                throw new \InvalidArgumentException('El documento indicado no coincide con su identidad.');
            }
        }

        $constraint = new ScopeConstraint();
        $constraint->idPersona = $idPersona;

        return $constraint;
    }
}
