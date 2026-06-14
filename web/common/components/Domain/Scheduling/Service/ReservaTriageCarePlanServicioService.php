<?php

namespace common\components\Domain\Scheduling\Service;

use common\components\Domain\Clinical\Enum\CarePlanActivityKind;
use common\components\Domain\Clinical\Enum\CarePlanCategory;
use common\components\Domain\Clinical\Service\PatientActiveCarePlanQuery;
use common\models\Clinical\CarePlan;
use common\models\Clinical\CarePlanActivity;
use common\models\Clinical\ServiceRequest;
use common\models\ProfesionalEfectorServicio;
use common\models\Servicio;

/**
 * Servicios reservables sugeridos desde un care plan activo del paciente (seguimiento crónico).
 */
final class ReservaTriageCarePlanServicioService
{
    /** @var array<string, list<string>> */
    private const CATEGORY_PATTERNS = [
        CarePlanCategory::CHRONIC => ['medicina_clinica', 'clinica', 'clínica'],
        CarePlanCategory::ACUTE_AMBULATORY => ['medicina_clinica', 'clinica', 'clínica'],
        CarePlanCategory::ODONTOLOGY => ['odontolog', 'odonto', 'dental'],
        CarePlanCategory::OPHTHALMOLOGY => ['oftalmolog', 'oftalmo', 'vista'],
        CarePlanCategory::MENTAL_HEALTH => ['psiquiat', 'psicolog', 'salud mental'],
        CarePlanCategory::REHABILITATION => ['rehabilit', 'kinesio', 'fisiatr'],
        CarePlanCategory::NUTRITION => ['nutric'],
    ];

    public function findPlanForPersona(int $carePlanId, int $idPersona): ?CarePlan
    {
        if ($carePlanId <= 0 || $idPersona <= 0) {
            return null;
        }
        foreach ((new PatientActiveCarePlanQuery())->listActive($idPersona) as $plan) {
            if ((int) $plan->id === $carePlanId) {
                return $plan;
            }
        }

        return null;
    }

    /**
     * @return list<int>
     */
    public function idsServicioReservaDesdePlan(CarePlan $plan): array
    {
        $ids = [];
        foreach ($this->idsDesdeActividadesServicio($plan) as $id) {
            $ids[] = $id;
        }
        foreach ($this->idsDesdeCategoria($plan) as $id) {
            $ids[] = $id;
        }

        return array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
    }

    /**
     * @return list<int>
     */
    private function idsDesdeActividadesServicio(CarePlan $plan): array
    {
        $activities = CarePlanActivity::find()
            ->where(['care_plan_id' => (int) $plan->id, 'kind' => CarePlanActivityKind::SERVICE_REQUEST])
            ->orderBy(['sort_order' => SORT_ASC])
            ->all();

        $ids = [];
        foreach ($activities as $activity) {
            if (!$activity instanceof CarePlanActivity) {
                continue;
            }
            $sr = ServiceRequest::findOne((int) $activity->resource_id);
            if ($sr === null) {
                continue;
            }
            $target = (int) ($sr->target_service_id ?? 0);
            if ($target > 0) {
                $ids[] = $target;
                continue;
            }
            $idPes = (int) ($sr->id_profesional_efector_servicio ?? 0);
            if ($idPes <= 0) {
                continue;
            }
            $pes = ProfesionalEfectorServicio::findOne($idPes);
            if ($pes !== null) {
                $idServicio = (int) ($pes->id_servicio ?? 0);
                if ($idServicio > 0) {
                    $ids[] = $idServicio;
                }
            }
        }

        return $ids;
    }

    /**
     * @return list<int>
     */
    private function idsDesdeCategoria(CarePlan $plan): array
    {
        $category = trim((string) ($plan->category ?? ''));
        $patrones = self::CATEGORY_PATTERNS[$category] ?? self::CATEGORY_PATTERNS[CarePlanCategory::CHRONIC];

        return $this->idsServiciosPorPatronesNombre($patrones);
    }

    /**
     * @param list<string> $patrones
     * @return list<int>
     */
    private function idsServiciosPorPatronesNombre(array $patrones): array
    {
        $rows = Servicio::find()
            ->where(['acepta_turnos' => 'SI'])
            ->orderBy(['nombre' => SORT_ASC])
            ->all();

        $ids = [];
        foreach ($rows as $servicio) {
            if (!$servicio instanceof Servicio) {
                continue;
            }
            $nombre = mb_strtolower(trim((string) $servicio->nombre), 'UTF-8');
            if ($nombre === '') {
                continue;
            }
            foreach ($patrones as $p) {
                if ($p !== '' && str_contains($nombre, mb_strtolower($p, 'UTF-8'))) {
                    $ids[] = (int) $servicio->id_servicio;
                    break;
                }
            }
        }

        return $ids;
    }
}
