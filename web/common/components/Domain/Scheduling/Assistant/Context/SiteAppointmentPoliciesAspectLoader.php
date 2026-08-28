<?php

namespace common\components\Domain\Scheduling\Assistant\Context;

use common\components\Domain\Scheduling\Service\TurnoAutogestionAnticipacionService;
use common\components\Platform\Assistant\Context\AssistantContextAspectLoaderInterface;
use common\components\Platform\Assistant\Context\AssistantContextHISAreaAspect;
use common\components\Platform\Assistant\Context\AssistantContextLoadContext;
use common\models\EfectorTurnosConfig;

final class SiteAppointmentPoliciesAspectLoader implements AssistantContextAspectLoaderInterface
{
    public function aspectKey(): string
    {
        return AssistantContextHISAreaAspect::SITE_APPOINTMENT_POLICIES;
    }

    public function load(AssistantContextLoadContext $ctx): array
    {
        $siteId = $ctx->anchors->siteId;
        if ($siteId <= 0) {
            return [
                'scope' => [],
                'site_appointment_policies' => null,
            ];
        }

        $cfg = EfectorTurnosConfig::getOrCreateForEfector($siteId);
        $anticip = new TurnoAutogestionAnticipacionService();

        return [
            'scope' => ['site_id' => $siteId],
            'site_appointment_policies' => [
                'site_id' => $siteId,
                'confirmation_required' => (bool) $cfg->confirmacion_requerida,
                'min_hours_cancel_by_app' => $anticip->minHorasAntesCancelarParaEfector($siteId),
                'min_hours_rebook_by_app' => $anticip->minHorasAntesReprogramarParaEfector($siteId),
                'late_arrival_tolerance_minutes' => null,
            ],
        ];
    }
}
