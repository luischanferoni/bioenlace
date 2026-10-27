<?php

namespace common\components\Domain\Organization\Efector\Application\Presentation;

use common\components\Platform\Core\Permission\OperationalRoleManifest;
use common\components\Platform\Core\Permission\OperativeRoleResolver;
use common\components\Platform\Ui\Home\Service\Sections\HomePanelSectionProviderInterface;
use common\models\Organization\BillingSignupRequest;

/**
 * Indicadores de red para AdminMinisterio / nivelCentral (MVP: solicitudes de alta pendientes).
 */
final class StaffRedSolicitudesSectionProvider implements HomePanelSectionProviderInterface
{
    public function build(array $context): array
    {
        $manifest = new OperationalRoleManifest();
        if (!$manifest->belongsToNetworkPanel((new OperativeRoleResolver($manifest))->resolve())) {
            return ['title' => '', 'items' => []];
        }

        $pendientes = (int) BillingSignupRequest::find()
            ->where(['status' => BillingSignupRequest::STATUS_PENDING])
            ->count();

        return [
            'title' => 'Red',
            'items' => [
                ['label' => 'Solicitudes de alta pendientes', 'value' => $pendientes],
            ],
        ];
    }
}
