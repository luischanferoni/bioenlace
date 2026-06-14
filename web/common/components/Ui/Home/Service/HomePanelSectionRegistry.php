<?php

namespace common\components\Ui\Home\Service;

use common\components\Clinical\Home\Sections\EmergencyBoardSectionProvider;
use common\components\Clinical\Home\Sections\EmergencyIndicatorsSectionProvider;
use common\components\Clinical\Home\Sections\InpatientsSectionProvider;
use common\components\Clinical\Home\Sections\PatientCarePlansActiveSectionProvider;
use common\components\Scheduling\Home\Sections\AppointmentsDaySectionProvider;
use common\components\Scheduling\Home\Sections\PatientUpcomingAppointmentsSectionProvider;
use common\components\Scheduling\Home\Sections\SurgeriesDaySectionProvider;
use common\components\Ui\Home\Service\Sections\ActionCardsSectionProvider;
use common\components\Ui\Home\Service\Sections\HomePanelSectionProviderInterface;
use Yii;

/**
 * Registry estable: provider_id → implementación de dominio para secciones del panel.
 */
final class HomePanelSectionRegistry
{
    /** @var array<string, class-string<HomePanelSectionProviderInterface>> */
    private const PROVIDERS = [
        'emergency_board' => EmergencyBoardSectionProvider::class,
        'emergency_indicators' => EmergencyIndicatorsSectionProvider::class,
        'appointments_day' => AppointmentsDaySectionProvider::class,
        'inpatients' => InpatientsSectionProvider::class,
        'surgeries_day' => SurgeriesDaySectionProvider::class,
        'action_cards' => ActionCardsSectionProvider::class,
        'patient_upcoming_appointments' => PatientUpcomingAppointmentsSectionProvider::class,
        'patient_care_plans_active' => PatientCarePlansActiveSectionProvider::class,
    ];

    /** @var array<string, HomePanelSectionProviderInterface>|null */
    private static ?array $instances = null;

    public function get(string $providerId): ?HomePanelSectionProviderInterface
    {
        return $this->all()[$providerId] ?? null;
    }

    /**
     * @return array<string, HomePanelSectionProviderInterface>
     */
    private function all(): array
    {
        if (self::$instances !== null) {
            return self::$instances;
        }

        self::$instances = [];
        foreach (self::providerClasses() as $providerId => $class) {
            self::$instances[$providerId] = new $class();
        }

        return self::$instances;
    }

    /**
     * @return array<string, class-string<HomePanelSectionProviderInterface>>
     */
    private static function providerClasses(): array
    {
        $extra = Yii::$app->params['homePanelSectionProviders'] ?? [];
        if (!is_array($extra)) {
            return self::PROVIDERS;
        }

        /** @var array<string, class-string<HomePanelSectionProviderInterface>> $merged */
        $merged = array_merge(self::PROVIDERS, $extra);

        return $merged;
    }
}
