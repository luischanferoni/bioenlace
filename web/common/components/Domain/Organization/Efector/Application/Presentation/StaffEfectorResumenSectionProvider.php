<?php

namespace common\components\Domain\Organization\Efector\Application\Presentation;

use common\components\Domain\Organization\Efector\Application\Service\EfectorResumenMetricsService;
use common\components\Domain\Scheduling\Agenda\Application\UseCase\ResolveServicioTeleconsultaPolitica;
use common\components\Platform\Ui\Home\Service\Sections\HomePanelSectionProviderInterface;
use Yii;

/**
 * Resumen del efector para el panel de inicio de AdminEfector.
 *
 * Sólo devuelve datos cuando la sesión opera como AdminEfector; en el resto de los roles
 * el panel recibe una sección vacía y no la muestra.
 */
final class StaffEfectorResumenSectionProvider implements HomePanelSectionProviderInterface
{
    public function build(array $context): array
    {
        if (!ResolveServicioTeleconsultaPolitica::usuarioEsAdminEfectorOperativo()) {
            return ['title' => '', 'items' => []];
        }

        $idEfector = (int) ($context['id_efector'] ?? 0);
        if ($idEfector <= 0 && Yii::$app->has('user', true)) {
            $idEfector = (int) (Yii::$app->user->getIdEfector() ?? 0);
        }
        if ($idEfector <= 0) {
            return ['title' => '', 'items' => []];
        }

        $metrics = (new EfectorResumenMetricsService())->resumen($idEfector);
        if ($metrics === []) {
            return ['title' => '', 'items' => []];
        }

        return [
            'title' => 'Mi efector',
            'items' => [
                ['label' => 'Profesionales activos', 'value' => $metrics['profesionales_activos']],
                ['label' => 'Servicios', 'value' => $metrics['servicios']],
            ],
        ];
    }
}
