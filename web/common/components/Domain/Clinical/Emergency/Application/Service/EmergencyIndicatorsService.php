<?php

namespace common\components\Domain\Clinical\Emergency\Application\Service;

use common\components\Domain\Clinical\Emergency\Domain\BoardState;
use common\components\Domain\Clinical\Emergency\Domain\BoardEventType;
use common\models\Clinical\Emergency\EmergencyBoardEvent;
use common\models\Clinical\Emergency\EmergencyTriage;
use common\models\Clinical\Emergency\EmergencyEpisode;
use yii\db\Query;

/**
 * KPIs operativos de guardia (Fase 5 — resumen en vivo).
 */
final class EmergencyIndicatorsService
{
    /**
     * @return array<string, mixed>
     */
    public function resumen(int $idEfector): array
    {
        $activos = EmergencyEpisode::find()
            ->where(['id_efector' => $idEfector])
            ->andWhere(['<>', 'estado', EmergencyEpisode::ESTADO_FINALIZADA])
            ->andWhere([
                'or',
                ['circuito_estado' => null],
                ['not in', 'circuito_estado', [BoardState::FINALIZADO]],
            ])
            ->count();

        $sinTriage = (int) (new Query())
            ->from(['g' => EmergencyEpisode::tableName()])
            ->leftJoin(['gt' => EmergencyTriage::tableName()], 'gt.guardia_id = g.id')
            ->where(['g.id_efector' => $idEfector])
            ->andWhere(['g.deleted_at' => null])
            ->andWhere(['<>', 'g.estado', EmergencyEpisode::ESTADO_FINALIZADA])
            ->andWhere(['gt.id' => null])
            ->count('*', EmergencyEpisode::getDb());

        $porNivel = (new Query())
            ->select(['g.prioridad_triage', 'cnt' => 'COUNT(*)'])
            ->from(['g' => EmergencyEpisode::tableName()])
            ->where(['g.id_efector' => $idEfector])
            ->andWhere(['g.deleted_at' => null])
            ->andWhere(['<>', 'g.estado', EmergencyEpisode::ESTADO_FINALIZADA])
            ->andWhere(['not', ['g.prioridad_triage' => null]])
            ->groupBy(['g.prioridad_triage'])
            ->all(EmergencyEpisode::getDb());

        $porCircuito = (new Query())
            ->select(['g.circuito_estado', 'cnt' => 'COUNT(*)'])
            ->from(['g' => EmergencyEpisode::tableName()])
            ->where(['g.id_efector' => $idEfector])
            ->andWhere(['g.deleted_at' => null])
            ->andWhere(['<>', 'g.estado', EmergencyEpisode::ESTADO_FINALIZADA])
            ->groupBy(['g.circuito_estado'])
            ->all(EmergencyEpisode::getDb());

        $hoy = date('Y-m-d');
        $ingresosHoy = EmergencyEpisode::find()
            ->where(['id_efector' => $idEfector])
            ->andWhere(['>=', 'fecha', $hoy])
            ->count();

        $medianas = $this->medianasTiemposHoy($idEfector);

        $slaIncumplidos = $this->contarSlaIncumplidosTablero($idEfector);

        return [
            'activos' => (int) $activos,
            'sin_triage' => $sinTriage,
            'ingresos_hoy' => (int) $ingresosHoy,
            'por_nivel' => $porNivel,
            'por_circuito' => $porCircuito,
            'tiempos_hoy' => $medianas,
            'sla_incumplidos_tablero' => $slaIncumplidos,
            'sla_config' => (new EmergencySlaService())->configForEfector($idEfector),
        ];
    }

    public function contarSlaIncumplidosTablero(int $idEfector): int
    {
        $items = (new EmergencyQueueService())->tablero($idEfector, ['solo_activos' => true])['items'];
        $n = 0;
        foreach ($items as $row) {
            if (!empty($row['sla_violado'])) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * @return array<string, int|null>
     */
    private function medianasTiemposHoy(int $idEfector): array
    {
        $guardiaIds = EmergencyEpisode::find()
            ->select('id')
            ->where(['id_efector' => $idEfector, 'fecha' => date('Y-m-d')])
            ->column();
        if ($guardiaIds === []) {
            return ['minutos_a_triage' => null, 'minutos_a_medico' => null];
        }

        $aTriage = [];
        $aMedico = [];
        foreach ($guardiaIds as $gid) {
            $events = EmergencyBoardEvent::find()
                ->where(['guardia_id' => (int) $gid])
                ->orderBy(['occurred_at' => SORT_ASC])
                ->all();
            $ingreso = null;
            $triage = null;
            $medico = null;
            foreach ($events as $ev) {
                if ($ev->tipo === BoardEventType::INGRESO && $ingreso === null) {
                    $ingreso = strtotime($ev->occurred_at);
                }
                if ($ev->tipo === BoardEventType::TRIAGE && $triage === null) {
                    $triage = strtotime($ev->occurred_at);
                }
                if ($ev->tipo === BoardEventType::INICIO_ATENCION && $medico === null) {
                    $medico = strtotime($ev->occurred_at);
                }
            }
            if ($ingreso && $triage) {
                $aTriage[] = (int) round(($triage - $ingreso) / 60);
            }
            if ($ingreso && $medico) {
                $aMedico[] = (int) round(($medico - $ingreso) / 60);
            }
        }

        return [
            'minutos_a_triage' => $this->median($aTriage),
            'minutos_a_medico' => $this->median($aMedico),
        ];
    }

    /**
     * @param int[] $values
     */
    private function median(array $values): ?int
    {
        if ($values === []) {
            return null;
        }
        sort($values);
        $n = count($values);
        $mid = (int) floor($n / 2);

        return $n % 2 === 0 ? (int) round(($values[$mid - 1] + $values[$mid]) / 2) : $values[$mid];
    }
}
