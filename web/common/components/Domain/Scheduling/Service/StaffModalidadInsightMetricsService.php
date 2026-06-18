<?php

namespace common\components\Domain\Scheduling\Service;

use common\models\ReservaTriageTeleconsultaElegibilidad;
use common\models\Scheduling\Turno;
use yii\db\Query;

/**
 * KPIs agregados de turnos presenciales con potencial remoto (insight educativo).
 */
final class StaffModalidadInsightMetricsService
{
    /**
     * @param array<string, mixed> $filters id_efector (requerido), fecha_desde, fecha_hasta, id_profesional_efector_servicio
     * @return array{
     *   presencial_insight_sugerido: int,
     *   presencial_con_triage: int,
     *   pct_sugerido: float|null,
     *   fecha_desde: string,
     *   fecha_hasta: string
     * }
     */
    public function resumen(array $filters): array
    {
        $idEfector = (int) ($filters['id_efector'] ?? 0);
        if ($idEfector <= 0) {
            throw new \InvalidArgumentException('Se requiere id_efector.');
        }

        $kpiCfg = (new AgendaAtencionRemotaCatalogService())->kpiPresencialRemoto();
        $hasta = isset($filters['fecha_hasta']) ? (string) $filters['fecha_hasta'] : date('Y-m-d');
        $desde = isset($filters['fecha_desde'])
            ? (string) $filters['fecha_desde']
            : date('Y-m-d', strtotime($hasta . ' -' . $kpiCfg['periodo_dias'] . ' days'));

        $idPes = isset($filters['id_profesional_efector_servicio'])
            ? (int) $filters['id_profesional_efector_servicio']
            : 0;

        $codigosSugerido = ReservaTriageTeleconsultaElegibilidad::listCodigosPorElegibilidad($kpiCfg['elegibilidad']);
        if ($codigosSugerido === []) {
            return [
                'presencial_insight_sugerido' => 0,
                'presencial_con_triage' => 0,
                'pct_sugerido' => null,
                'fecha_desde' => $desde,
                'fecha_hasta' => $hasta,
            ];
        }

        $base = (new Query())
            ->from(['t' => Turno::tableName()])
            ->where(['t.id_efector' => $idEfector])
            ->andWhere(['between', 't.fecha', $desde, $hasta])
            ->andWhere(['t.tipo_atencion' => Turno::TIPO_ATENCION_PRESENCIAL])
            ->andWhere(['not', ['t.reserva_triage_code' => null]])
            ->andWhere(['<>', 't.reserva_triage_code', '']);

        if ($idPes > 0) {
            $base->andWhere(['t.id_profesional_efector_servicio' => $idPes]);
        }

        $conTriage = (int) (clone $base)->count('*', Turno::getDb());

        $sugerido = (int) (clone $base)
            ->andWhere(['t.reserva_triage_code' => $codigosSugerido])
            ->count('*', Turno::getDb());

        $pct = $conTriage > 0 ? round(100.0 * $sugerido / $conTriage, 0) : null;

        return [
            'presencial_insight_sugerido' => $sugerido,
            'presencial_con_triage' => $conTriage,
            'pct_sugerido' => $pct,
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
        ];
    }
}
