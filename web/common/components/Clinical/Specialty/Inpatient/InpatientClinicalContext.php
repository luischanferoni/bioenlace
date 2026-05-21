<?php

namespace common\components\Clinical\Specialty\Inpatient;

use common\components\Clinical\Enum\CarePlanCategory;
use common\components\Clinical\Enum\CarePlanStatus;
use common\components\Clinical\Enum\EncounterStatus;
use common\components\Clinical\Service\CarePlanLifecycleService;
use common\components\Clinical\Service\EpisodeOfCareService;
use common\models\Clinical\CarePlan;
use common\models\Clinical\Encounter;
use common\models\Clinical\EpisodeOfCare;
use common\models\SegNivelInternacion;

/**
 * Contexto clínico activo de una internación (episode + care plan inpatient + encounter IMP).
 */
final class InpatientClinicalContext
{
    public function __construct(
        public readonly SegNivelInternacion $internacion,
        public readonly EpisodeOfCare $episode,
        public readonly CarePlan $carePlan,
        public readonly Encounter $encounter
    ) {
    }

    public static function ensure(SegNivelInternacion $internacion): self
    {
        $episodes = new EpisodeOfCareService();
        $episode = $episodes->findActiveForInternacion((int) $internacion->id);
        if ($episode === null) {
            $episode = (new CarePlanLifecycleService())->onInternacionAdmission($internacion);
        }

        $carePlan = CarePlan::find()
            ->andWhere([
                'episode_of_care_id' => $episode->id,
                'category' => CarePlanCategory::INPATIENT,
            ])
            ->andWhere(['status' => [CarePlanStatus::DRAFT, CarePlanStatus::ACTIVE, CarePlanStatus::ON_HOLD]])
            ->andWhere(['deleted_at' => null])
            ->orderBy(['id' => SORT_DESC])
            ->one();

        if (!$carePlan instanceof CarePlan) {
            throw new \RuntimeException(
                'No hay care plan inpatient activo para la internación #' . $internacion->id
            );
        }

        $encounter = self::findOpenInpatientEncounter((int) $internacion->id);
        if ($encounter === null) {
            throw new \RuntimeException(
                'No hay encounter IMP en curso para la internación #' . $internacion->id
            );
        }

        return new self($internacion, $episode, $carePlan, $encounter);
    }

    public static function findOpenInpatientEncounter(int $internacionId): ?Encounter
    {
        return Encounter::find()
            ->andWhere([
                'parent_type' => Encounter::PARENT_CLASSES[Encounter::PARENT_INTERNACION],
                'parent_id' => $internacionId,
                'encounter_class' => Encounter::ENCOUNTER_CLASS_IMP,
                'status' => EncounterStatus::IN_PROGRESS,
            ])
            ->andWhere(['deleted_at' => null])
            ->orderBy(['id' => SORT_DESC])
            ->one();
    }
}
