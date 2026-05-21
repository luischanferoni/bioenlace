<?php

namespace common\components\Clinical;

use yii\helpers\Url;

/**
 * Enlaces a captura clínica vía timeline ({@see \frontend\controllers\PacienteController::actionHistoria}).
 *
 * Reemplaza {@see \common\models\Consulta::armarUrlAConsultadesdeParent} (MVC Consulta* retirado).
 */
final class PatientHistoriaUrl
{
    /**
     * @param string $parent Clave {@see \common\models\Consulta::PARENT_*} (TURNO, GUARDIA, …)
     * @param array<string, scalar> $extraParams Query adicional (p. ej. id_servicio en pase previo)
     */
    public static function captura(int $idPersona, string $parent, int $parentId, array $extraParams = []): string
    {
        return Url::to(array_merge([
            '/paciente/historia',
            'id' => $idPersona,
            'parent' => $parent,
            'parent_id' => $parentId,
        ], $extraParams));
    }
}
