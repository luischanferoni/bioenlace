<?php

namespace common\components\Clinical\CarePlan;

use common\components\Clinical\Enum\CarePlanStatus;
use common\components\Clinical\Service\CarePlanPresentationService;
use common\models\Clinical\CarePlan;
use common\models\Clinical\CarePlanActivity;
use common\models\Clinical\Encounter;
use common\models\Person\Persona;
use yii\db\Query;

/**
 * Adherencia a planes de tratamiento activos (vista staff por efector).
 */
final class CarePlanAdherenceStaffService
{
    private const COMPLETED_STATUSES = ['completed', 'completed-success', 'complete'];

    /**
     * @return array<string, mixed>
     */
    public function resumen(int $idEfector): array
    {
        if ($idEfector <= 0) {
            throw new \InvalidArgumentException('Se requiere id_efector.');
        }

        $planIds = (new Query())
            ->select('cp.id')
            ->from(['cp' => CarePlan::tableName()])
            ->innerJoin(['e' => Encounter::tableName()], 'e.id = cp.encounter_id')
            ->where([
                'cp.deleted_at' => null,
                'cp.status' => [CarePlanStatus::ACTIVE, CarePlanStatus::ON_HOLD],
                'e.efector_id' => $idEfector,
                'e.deleted_at' => null,
            ])
            ->column();

        $planes = [];
        $totalActividades = 0;
        $totalCompletadas = 0;

        foreach ($planIds as $planId) {
            $plan = CarePlan::findOne((int) $planId);
            if ($plan === null) {
                continue;
            }
            $row = $this->serializePlanAdherence($plan);
            $planes[] = $row;
            $totalActividades += (int) $row['actividades_total'];
            $totalCompletadas += (int) $row['actividades_completadas'];
        }

        usort($planes, static function (array $a, array $b): int {
            return ($b['adherencia_pct'] ?? 0) <=> ($a['adherencia_pct'] ?? 0);
        });

        $promedio = $totalActividades > 0
            ? round(100.0 * $totalCompletadas / $totalActividades, 1)
            : null;

        return [
            'id_efector' => $idEfector,
            'planes_activos' => count($planes),
            'actividades_total' => $totalActividades,
            'actividades_completadas' => $totalCompletadas,
            'adherencia_promedio_pct' => $promedio,
            'planes' => $planes,
            'resumen_texto' => $this->formatResumenTexto(count($planes), $totalActividades, $totalCompletadas, $promedio),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePlanAdherence(CarePlan $plan): array
    {
        $activities = CarePlanActivity::find()
            ->where(['care_plan_id' => (int) $plan->id])
            ->all();

        $total = count($activities);
        $completed = 0;
        foreach ($activities as $a) {
            if ($this->isCompleted((string) $a->status)) {
                $completed++;
            }
        }

        $pct = $total > 0 ? round(100.0 * $completed / $total, 1) : null;
        $paciente = $plan->subject;
        $nombre = $paciente && method_exists($paciente, 'getNombreCompleto')
            ? $paciente->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N)
            : 'Paciente';

        $presentation = new CarePlanPresentationService();
        $summary = $presentation->toPatientSummary($plan, true, 3);

        return [
            'care_plan_id' => (int) $plan->id,
            'paciente_nombre' => $nombre,
            'id_persona' => (int) $plan->subject_persona_id,
            'category_label' => $summary['categoryLabel'] ?? $plan->category,
            'status' => $plan->status,
            'actividades_total' => $total,
            'actividades_completadas' => $completed,
            'actividades_pendientes' => max(0, $total - $completed),
            'adherencia_pct' => $pct,
        ];
    }

    private function isCompleted(string $status): bool
    {
        return in_array(strtolower($status), self::COMPLETED_STATUSES, true);
    }

    private function formatResumenTexto(int $planes, int $totalAct, int $completed, ?float $promedio): string
    {
        $lines = [
            "Planes activos en el efector: {$planes}",
            "Actividades: {$completed} / {$totalAct} completadas",
        ];
        if ($promedio !== null) {
            $lines[] = "Adherencia promedio: {$promedio}%";
        }

        return implode("\n", $lines);
    }
}
