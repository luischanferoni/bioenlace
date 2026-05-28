<?php

namespace common\components\Clinical\Emergency\Service;

use common\components\Clinical\Emergency\Enum\CircuitoEstado;
use common\components\Clinical\Emergency\Enum\CircuitoEventType;
use common\models\Emergency\GuardiaCircuitoEvent;
use common\models\Emergency\GuardiaTriage;
use common\models\Guardia;
use yii\db\Query;

/**
 * KPIs operativos de guardia (Fase 5 — resumen en vivo).
 */
final class GuardiaIndicadoresService
{
    /**
     * @return array<string, mixed>
     */
    public function resumen(int $idEfector): array
    {
        $activos = Guardia::find()
            ->where(['id_efector' => $idEfector])
            ->andWhere(['<>', 'estado', Guardia::ESTADO_FINALIZADA])
            ->andWhere([
                'or',
                ['circuito_estado' => null],
                ['not in', 'circuito_estado', [CircuitoEstado::FINALIZADO]],
            ])
            ->count();

        $sinTriage = (int) (new Query())
            ->from(['g' => Guardia::tableName()])
            ->leftJoin(['gt' => GuardiaTriage::tableName()], 'gt.guardia_id = g.id')
            ->where(['g.id_efector' => $idEfector])
            ->andWhere(['<>', 'g.estado', Guardia::ESTADO_FINALIZADA])
            ->andWhere(['gt.id' => null])
            ->count('*', Guardia::getDb());

        $porNivel = (new Query())
            ->select(['g.prioridad_triage', 'cnt' => 'COUNT(*)'])
            ->from(['g' => Guardia::tableName()])
            ->where(['g.id_efector' => $idEfector])
            ->andWhere(['<>', 'g.estado', Guardia::ESTADO_FINALIZADA])
            ->andWhere(['not', ['g.prioridad_triage' => null]])
            ->groupBy(['g.prioridad_triage'])
            ->all(Guardia::getDb());

        $porCircuito = (new Query())
            ->select(['g.circuito_estado', 'cnt' => 'COUNT(*)'])
            ->from(['g' => Guardia::tableName()])
            ->where(['g.id_efector' => $idEfector])
            ->andWhere(['<>', 'g.estado', Guardia::ESTADO_FINALIZADA])
            ->groupBy(['g.circuito_estado'])
            ->all(Guardia::getDb());

        $hoy = date('Y-m-d');
        $ingresosHoy = Guardia::find()
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
            'sla_config' => (new GuardiaSlaService())->configForEfector($idEfector),
        ];
    }

    public function contarSlaIncumplidosTablero(int $idEfector): int
    {
        $items = (new GuardiaQueueService())->tablero($idEfector, ['solo_activos' => true])['items'];
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
        $guardiaIds = Guardia::find()
            ->select('id')
            ->where(['id_efector' => $idEfector, 'fecha' => date('Y-m-d')])
            ->column();
        if ($guardiaIds === []) {
            return ['minutos_a_triage' => null, 'minutos_a_medico' => null];
        }

        $aTriage = [];
        $aMedico = [];
        foreach ($guardiaIds as $gid) {
            $events = GuardiaCircuitoEvent::find()
                ->where(['guardia_id' => (int) $gid])
                ->orderBy(['occurred_at' => SORT_ASC])
                ->all();
            $ingreso = null;
            $triage = null;
            $medico = null;
            foreach ($events as $ev) {
                if ($ev->tipo === CircuitoEventType::INGRESO && $ingreso === null) {
                    $ingreso = strtotime($ev->occurred_at);
                }
                if ($ev->tipo === CircuitoEventType::TRIAGE && $triage === null) {
                    $triage = strtotime($ev->occurred_at);
                }
                if ($ev->tipo === CircuitoEventType::INICIO_ATENCION && $medico === null) {
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
