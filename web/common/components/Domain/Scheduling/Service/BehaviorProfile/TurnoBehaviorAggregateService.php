<?php

namespace common\components\Domain\Scheduling\Service\BehaviorProfile;

use common\models\Scheduling\PersonaTurnosPerfil;
use common\models\Scheduling\PersonaTurnosPerfilMetrica;
use yii\db\Query;

/**
 * Agregados factuales por efector con supresión de cohortes pequeñas.
 */
final class TurnoBehaviorAggregateService
{
    private TurnoBehaviorProfileContract $contract;

    public function __construct(?TurnoBehaviorProfileContract $contract = null)
    {
        $this->contract = $contract ?? new TurnoBehaviorProfileContract();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function forEfector(array $filters): array
    {
        $idEfector = (int) ($filters['id_efector'] ?? 0);
        if ($idEfector <= 0) {
            throw new \InvalidArgumentException('id_efector es obligatorio');
        }
        $windowDays = (int) ($filters['window_days'] ?? 90);
        if (!in_array($windowDays, $this->contract->windowsDays(), true)) {
            throw new \InvalidArgumentException(
                'window_days debe ser uno de: ' . implode(', ', $this->contract->windowsDays())
            );
        }

        $metricCodes = [
            'ATTENDED',
            'NO_SHOW_ATTRIBUTABLE',
            'CLOSED_ELIGIBLE',
            'NO_SHOW_RATE',
            'COVERAGE_NATIVE',
            'COVERAGE_RATE',
        ];

        $rows = (new Query())
            ->from(['m' => PersonaTurnosPerfilMetrica::tableName()])
            ->innerJoin(
                ['p' => PersonaTurnosPerfil::tableName()],
                'p.id = m.id_perfil'
            )
            ->select([
                'p.id_persona',
                'm.metric_code',
                'm.numerator',
                'm.denominator',
                'm.value',
                'm.sample_size',
                'm.confidence_status',
                'p.as_of',
                'p.profile_contract_version',
            ])
            ->where([
                'p.is_current' => 1,
                'p.profile_contract_version' => $this->contract->version(),
                'm.scope_type' => PersonaTurnosPerfilMetrica::SCOPE_EFECTOR,
                'm.scope_id' => (string) $idEfector,
                'm.window_days' => $windowDays,
            ])
            ->andWhere(['in', 'm.metric_code', $metricCodes])
            ->all();

        $personIds = [];
        foreach ($rows as $row) {
            $personIds[(int) $row['id_persona']] = true;
        }
        $cohortSize = count($personIds);
        $minSample = $this->contract->minSampleSize();
        if ($cohortSize < $minSample) {
            return [
                'status' => 'SUPPRESSED_SMALL_COHORT',
                'id_efector' => $idEfector,
                'window_days' => $windowDays,
                'contract_version' => $this->contract->version(),
                'suppression_reason' => 'Cohorte insuficiente para publicar agregados.',
                'metrics' => [],
            ];
        }

        $sums = [];
        $asOf = null;
        foreach ($rows as $row) {
            $code = (string) $row['metric_code'];
            if (!isset($sums[$code])) {
                $sums[$code] = [
                    'numerator' => 0,
                    'denominator' => 0,
                    'has_denominator' => false,
                ];
            }
            $sums[$code]['numerator'] += (int) $row['numerator'];
            if ($row['denominator'] !== null) {
                $sums[$code]['denominator'] += (int) $row['denominator'];
                $sums[$code]['has_denominator'] = true;
            }
            $asOf = (string) $row['as_of'];
        }

        $metrics = [];
        foreach ($metricCodes as $code) {
            if (!isset($sums[$code])) {
                continue;
            }
            $num = $sums[$code]['numerator'];
            $den = $sums[$code]['has_denominator'] ? $sums[$code]['denominator'] : null;
            $isRate = str_ends_with($code, '_RATE');
            $value = null;
            $confidence = PersonaTurnosPerfilMetrica::CONFIDENCE_OK;
            if ($isRate) {
                if ($den === null || $den <= 0) {
                    $confidence = PersonaTurnosPerfilMetrica::CONFIDENCE_NOT_APPLICABLE;
                } elseif ($den < $minSample) {
                    $confidence = PersonaTurnosPerfilMetrica::CONFIDENCE_INSUFFICIENT_DATA;
                } else {
                    $value = round($num / $den, 6);
                }
            } else {
                $value = (float) $num;
            }
            $metrics[] = [
                'code' => $code,
                'numerator' => $num,
                'denominator' => $den,
                'value' => $value,
                'confidence_status' => $confidence,
            ];
        }

        return [
            'status' => 'AVAILABLE',
            'id_efector' => $idEfector,
            'window_days' => $windowDays,
            'contract_version' => $this->contract->version(),
            'as_of' => $asOf,
            'metrics' => $metrics,
            'disclaimer' => 'Agregado factual por efector. No identifica personas.',
        ];
    }
}
