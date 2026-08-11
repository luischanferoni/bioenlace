<?php

namespace common\components\Domain\Clinical\Workflow;

use common\components\Domain\Clinical\Enum\CarePlanActivityKind;
use common\components\Domain\Clinical\Enum\CarePlanCategory;
use common\components\Domain\Clinical\Enum\CarePlanStatus;
use common\components\Domain\Clinical\Service\CarePlanPresentationService;
use common\components\Domain\Clinical\Service\EpisodeOfCareService;
use common\models\Clinical\CarePlan;
use common\models\Clinical\CarePlanActivity;
use common\models\Clinical\Encounter;
use common\models\Clinical\EncounterDefinition;
use common\models\Servicio;
use common\models\User;
use Yii;

/**
 * Categorías de captura: definición (service_id + class) + actor de sesión + CarePlan inpatient.
 */
final class EncounterCaptureCategoryResolver
{
    /** @var array<string, string> */
    private const CARE_PLAN_KIND_TO_MODELO = [
        CarePlanActivityKind::MEDICATION_REQUEST => 'ConsultaMedicamentos',
        CarePlanActivityKind::SERVICE_REQUEST => 'ConsultaIndicaciones',
        CarePlanActivityKind::NUTRITION_ORDER => 'ConsultaRegimen',
    ];

    /**
     * @param array<string, mixed> $body
     * @return list<array<string, mixed>>
     */
    public function resolve(?EncounterDefinition $definition, array $body = [], ?string $actorOverride = null): array
    {
        if (!$definition instanceof EncounterDefinition) {
            return [];
        }
        $categorias = EncounterDefinition::getCategoriasParaPrompt($definition);
        $actor = $actorOverride !== null && $actorOverride !== ''
            ? EncounterCaptureActorCatalog::normalize($actorOverride)
            : $this->resolveActorItemName();
        $class = trim((string) $definition->encounter_class);
        $categorias = $this->applyActor($categorias, $actor, $class);

        return $this->applyCarePlan($categorias, $body);
    }

    public function resolveActorItemName(): string
    {
        $idServicio = 0;
        if (Yii::$app->has('user', true)) {
            try {
                $idServicio = (int) Yii::$app->user->getServicioActual();
            } catch (\Throwable $e) {
                $idServicio = 0;
            }
        }
        if ($idServicio > 0) {
            $servicio = Servicio::findOne($idServicio);
            if ($servicio !== null) {
                return EncounterCaptureActorCatalog::normalize((string) $servicio->item_name);
            }
        }
        try {
            if (User::hasRole(['enfermeria'], false)) {
                return EncounterCaptureActorCatalog::ACTOR_ENFERMERIA;
            }
            if (User::hasRole(['Medico'], false)) {
                return EncounterCaptureActorCatalog::ACTOR_MEDICO;
            }
        } catch (\Throwable $e) {
            return '';
        }

        return '';
    }

    /**
     * @param list<array<string, mixed>> $categorias
     * @return list<array<string, mixed>>
     */
    private function applyActor(array $categorias, string $actor, string $encounterClass): array
    {
        foreach (EncounterCaptureActorCatalog::extraSteps($actor, $encounterClass) as $extra) {
            if ($this->indexOfModelo($categorias, $extra['modelo']) === null) {
                $categorias = $this->insertAfterTitle($categorias, $extra, null);
            }
        }
        foreach (EncounterCaptureActorCatalog::suggestedModelos($actor, $encounterClass) as $modelo) {
            $i = $this->indexOfModelo($categorias, $modelo);
            if ($i !== null) {
                $categorias[$i]['sugerido'] = true;
            }
        }

        return $categorias;
    }

    /**
     * @param list<array<string, mixed>> $categorias
     * @param array<string, mixed> $body
     * @return list<array<string, mixed>>
     */
    private function applyCarePlan(array $categorias, array $body): array
    {
        $plan = $this->findInpatientPlan($body);
        if ($plan === null) {
            return $categorias;
        }
        $presentation = new CarePlanPresentationService();
        $summary = $presentation->toPatientSummary($plan, true, 12);
        $activityLines = [];
        if (is_array($summary['activitySummaries'] ?? null)) {
            foreach ($summary['activitySummaries'] as $line) {
                if (is_string($line) && trim($line) !== '') {
                    $activityLines[] = trim($line);
                }
            }
        }
        $kinds = CarePlanActivity::find()
            ->select(['kind'])
            ->where(['care_plan_id' => $plan->id])
            ->column();
        $hintsByModelo = [];
        foreach ($kinds as $kind) {
            $modelo = self::CARE_PLAN_KIND_TO_MODELO[(string) $kind] ?? null;
            if ($modelo === null) {
                continue;
            }
            $hintsByModelo[$modelo] = $activityLines;
        }
        foreach ($hintsByModelo as $modelo => $hints) {
            $i = $this->indexOfModelo($categorias, $modelo);
            if ($i === null) {
                continue;
            }
            $categorias[$i]['sugerido'] = true;
            $categorias[$i]['plan_hints'] = $hints;
        }

        return $categorias;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function findInpatientPlan(array $body): ?CarePlan
    {
        $parent = strtoupper(trim((string) ($body['parent'] ?? '')));
        $parentId = (int) ($body['parent_id'] ?? 0);
        if ($parent !== Encounter::PARENT_INTERNACION || $parentId <= 0) {
            return null;
        }
        $episode = (new EpisodeOfCareService())->findActiveForInternacion($parentId);
        if ($episode === null) {
            return null;
        }

        $plan = CarePlan::find()
            ->andWhere([
                'episode_of_care_id' => $episode->id,
                'category' => CarePlanCategory::INPATIENT,
            ])
            ->andWhere(['status' => [CarePlanStatus::DRAFT, CarePlanStatus::ACTIVE, CarePlanStatus::ON_HOLD]])
            ->andWhere(['deleted_at' => null])
            ->orderBy(['id' => SORT_DESC])
            ->one();

        return $plan instanceof CarePlan ? $plan : null;
    }

    /**
     * @param list<array<string, mixed>> $categorias
     */
    private function indexOfModelo(array $categorias, string $modelo): ?int
    {
        foreach ($categorias as $i => $cat) {
            if (($cat['modelo'] ?? '') === $modelo) {
                return (int) $i;
            }
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $categorias
     * @param array{titulo: string, modelo: string, requerido: bool, sugerido: bool} $extra
     * @return list<array<string, mixed>>
     */
    private function insertAfterTitle(array $categorias, array $extra, ?string $afterTitle): array
    {
        $step = [
            'titulo' => $extra['titulo'],
            'modelo' => $extra['modelo'],
            'requerido' => (bool) ($extra['requerido'] ?? false),
            'sugerido' => (bool) ($extra['sugerido'] ?? false),
            'campos_requeridos' => EncounterDefinition::camposRequeridosDelModelo($extra['modelo']),
        ];
        if ($afterTitle === null) {
            array_unshift($categorias, $step);

            return $categorias;
        }

        return $categorias;
    }
}
