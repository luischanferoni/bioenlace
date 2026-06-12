<?php

namespace common\components\Home\Service;

use common\components\Home\Service\Sections\ActionCardsSectionProvider;
use common\components\Home\Service\Sections\AppointmentsDaySectionProvider;
use common\components\Home\Service\Sections\EmergencyBoardSectionProvider;
use common\components\Home\Service\Sections\EmergencyIndicatorsSectionProvider;
use common\components\Home\Service\Sections\HomePanelSectionProviderInterface;
use common\components\Home\Service\Sections\InpatientsSectionProvider;
use common\components\Home\Service\Sections\PatientCarePlansActiveSectionProvider;
use common\components\Home\Service\Sections\PatientUpcomingAppointmentsSectionProvider;
use common\components\Home\Service\Sections\SurgeriesDaySectionProvider;

/**
 * Registry estable: provider_id → callable de dominio para secciones del panel.
 */
final class HomePanelSectionRegistry
{
    /** @var array<string, HomePanelSectionProviderInterface>|null */
    private static ?array $providers = null;

    public function get(string $providerId): ?HomePanelSectionProviderInterface
    {
        return $this->all()[$providerId] ?? null;
    }

    /**
     * @return array<string, HomePanelSectionProviderInterface>
     */
    private function all(): array
    {
        if (self::$providers !== null) {
            return self::$providers;
        }

        self::$providers = [
            'emergency_board' => new EmergencyBoardSectionProvider(),
            'emergency_indicators' => new EmergencyIndicatorsSectionProvider(),
            'appointments_day' => new AppointmentsDaySectionProvider(),
            'inpatients' => new InpatientsSectionProvider(),
            'surgeries_day' => new SurgeriesDaySectionProvider(),
            'action_cards' => new ActionCardsSectionProvider(),
            'patient_upcoming_appointments' => new PatientUpcomingAppointmentsSectionProvider(),
            'patient_care_plans_active' => new PatientCarePlansActiveSectionProvider(),
        ];

        return self::$providers;
    }
}
