<?php

namespace common\components\Platform\Ui\Home\Service\Sections;

use Yii;

/**
 * Contexto operativo de sesión para el panel staff sin encounter (admin efector, etc.).
 */
final class StaffSessionContextSectionProvider implements HomePanelSectionProviderInterface
{
    public function build(array $context): array
    {
        $user = Yii::$app->user;
        $idEfector = (int) $user->getIdEfector();
        $idServicio = (int) $user->getServicioActual();
        $encounterClass = trim((string) ($user->getEncounterClass() ?? ''));

        $nombreServicio = '';
        $servicios = $user->getServicios();
        if (is_array($servicios) && $idServicio > 0 && isset($servicios[$idServicio])) {
            $row = $servicios[$idServicio];
            $nombreServicio = is_array($row)
                ? trim((string) ($row['nombre'] ?? $row['descripcion'] ?? ''))
                : trim((string) $row);
        }

        $hint = null;
        if ($encounterClass === '') {
            $hint = 'Seleccioná un servicio con contexto clínico (guardia, ambulatorio o internación) para ver el tablero del día.';
        }

        return [
            'id_efector' => $idEfector > 0 ? $idEfector : null,
            'nombre_efector' => trim((string) ($user->getNombreEfector() ?? '')),
            'id_servicio' => $idServicio > 0 ? $idServicio : null,
            'nombre_servicio' => $nombreServicio,
            'encounter_class' => $encounterClass !== '' ? $encounterClass : null,
            'fecha' => isset($context['fecha']) ? (string) $context['fecha'] : date('Y-m-d'),
            'hint' => $hint,
        ];
    }
}
