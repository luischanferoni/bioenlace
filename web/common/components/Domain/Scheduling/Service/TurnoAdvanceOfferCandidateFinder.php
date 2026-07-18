<?php

namespace common\components\Domain\Scheduling\Service;

use common\models\Scheduling\Turno;
use common\models\Scheduling\TurnoAdvanceCampaign;
use common\models\Scheduling\TurnoAdvanceOffer;
use common\models\TurnoResolucion;
use common\models\UserDevice;
use yii\db\Expression;
use yii\db\Query;

/**
 * Selección de candidatos para adelantar a un slot cancelado.
 */
final class TurnoAdvanceOfferCandidateFinder
{
    /**
     * @param array<string, mixed> $config
     * @return list<Turno>
     */
    public function findCandidates(TurnoAdvanceCampaign $campaign, array $config): array
    {
        $slotTs = strtotime($campaign->fecha . ' ' . $this->normalizeHora($campaign->hora) . ':00');
        if ($slotTs === false) {
            return [];
        }

        $horizonEnd = $this->horizonEndDate($campaign->fecha, (string) ($config['candidate_horizon'] ?? 'next_day_end'));
        $offeredTurnoIds = (new Query())
            ->from(TurnoAdvanceOffer::tableName())
            ->select('id_turno_candidate')
            ->where(['id_campaign' => (int) $campaign->id])
            ->column();

        $q = Turno::findActive()->alias('t')
            ->where(['t.estado' => Turno::ESTADO_PENDIENTE])
            ->andWhere(['t.id_efector' => (int) $campaign->id_efector])
            ->andWhere(['t.id_servicio_asignado' => (int) $campaign->id_servicio])
            ->andWhere(['t.id_profesional_efector_servicio' => (int) $campaign->id_profesional_efector_servicio])
            ->andWhere(['t.tipo_atencion' => (string) $campaign->modalidad])
            ->andWhere(['<=', 't.fecha', $horizonEnd])
            ->andWhere([
                'or',
                ['>', 't.fecha', $campaign->fecha],
                [
                    'and',
                    ['t.fecha' => $campaign->fecha],
                    ['>', 't.hora', $this->normalizeHora($campaign->hora) . ':00'],
                ],
            ])
            ->andWhere([
                'not exists',
                (new Query())
                    ->from(['r' => TurnoResolucion::tableName()])
                    ->where('r.id_turno = t.id_turnos')
                    ->andWhere(['r.estado' => TurnoResolucion::ESTADO_PENDIENTE]),
            ])
            ->orderBy(['t.fecha' => SORT_ASC, 't.hora' => SORT_ASC, 't.id_turnos' => SORT_ASC]);

        if ($offeredTurnoIds !== []) {
            $q->andWhere(['not in', 't.id_turnos', $offeredTurnoIds]);
        }

        if (!empty($config['require_active_push'])) {
            $q->andWhere([
                'exists',
                (new Query())
                    ->from(['d' => UserDevice::tableName()])
                    ->where('d.id_persona = t.id_persona')
                    ->andWhere(['d.is_active' => 1])
                    ->andWhere(['not', ['d.push_token' => null]])
                    ->andWhere(['<>', 'd.push_token', '']),
            ]);
        }

        /** @var list<Turno> $rows */
        $rows = $q->limit(100)->all();

        return $rows;
    }

    public function nextCandidate(TurnoAdvanceCampaign $campaign, array $config): ?Turno
    {
        $list = $this->findCandidates($campaign, $config);

        return $list[0] ?? null;
    }

    private function horizonEndDate(string $slotFecha, string $horizon): string
    {
        if ($horizon === 'same_day') {
            return $slotFecha;
        }
        if ($horizon === 'next_24h') {
            return date('Y-m-d', strtotime($slotFecha . ' +1 day'));
        }

        // next_day_end
        return date('Y-m-d', strtotime($slotFecha . ' +1 day'));
    }

    private function normalizeHora(string $hora): string
    {
        $n = TurnoResolucion::normalizarHora($hora);

        return $n !== '' ? $n : substr(trim($hora), 0, 5);
    }
}
