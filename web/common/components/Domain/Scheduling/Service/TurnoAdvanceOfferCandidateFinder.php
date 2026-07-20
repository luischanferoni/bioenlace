<?php

namespace common\components\Domain\Scheduling\Service;

use common\models\Scheduling\Turno;
use common\models\Scheduling\TurnoAdvanceCampaign;
use common\models\Scheduling\TurnoAdvanceOffer;
use common\models\TurnoResolucion;
use common\models\UserDevice;
use yii\db\Query;

/**
 * Selección de candidatos para adelantar a un slot cancelado.
 *
 * Política declarativa (turno-advance-offer.yaml):
 * - D+2 misma franja (orden horario), luego D+1 misma franja.
 * - Sin mismo día (D+0).
 * - Días calendario (fines de semana incluidos; sin agenda ⇒ sin candidatos).
 */
final class TurnoAdvanceOfferCandidateFinder
{
    /**
     * @param array<string, mixed> $config
     * @return list<Turno>
     */
    public function findCandidates(TurnoAdvanceCampaign $campaign, array $config): array
    {
        $slotFecha = trim((string) $campaign->fecha);
        $slotHora = $this->normalizeHora((string) $campaign->hora);
        if ($slotFecha === '' || $slotHora === '') {
            return [];
        }

        $d1 = date('Y-m-d', strtotime($slotFecha . ' +1 day'));
        $d2 = date('Y-m-d', strtotime($slotFecha . ' +2 day'));
        if ($d1 === false || $d2 === false || $d1 === '' || $d2 === '') {
            return [];
        }

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
            ->andWhere(['t.fecha' => [$d1, $d2]])
            ->andWhere([
                'not exists',
                (new Query())
                    ->from(['r' => TurnoResolucion::tableName()])
                    ->where('r.id_turno = t.id_turnos')
                    ->andWhere(['r.estado' => TurnoResolucion::ESTADO_PENDIENTE]),
            ]);

        $this->applySameHalfdayFilter($q, $slotHora, $config);

        // D+2 antes que D+1: fecha DESC; dentro de cada día, horario ASC.
        $q->orderBy(['t.fecha' => SORT_DESC, 't.hora' => SORT_ASC, 't.id_turnos' => SORT_ASC]);

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

    /**
     * @param \yii\db\ActiveQuery $q
     * @param array<string, mixed> $config
     */
    private function applySameHalfdayFilter($q, string $slotHoraHhMm, array $config): void
    {
        $splitHour = (int) ($config['halfday_split_hour'] ?? 13);
        if ($splitHour < 0 || $splitHour > 23) {
            $splitHour = 13;
        }
        $splitHhMm = sprintf('%02d:00', $splitHour);
        $splitHhMmSs = $splitHhMm . ':00';

        if ($slotHoraHhMm < $splitHhMm) {
            // Mañana: hora < split.
            $q->andWhere(['<', 't.hora', $splitHhMmSs]);
            return;
        }
        // Tarde: hora >= split.
        $q->andWhere(['>=', 't.hora', $splitHhMmSs]);
    }

    private function normalizeHora(string $hora): string
    {
        $n = TurnoResolucion::normalizarHora($hora);

        return $n !== '' ? substr($n, 0, 5) : substr(trim($hora), 0, 5);
    }
}
