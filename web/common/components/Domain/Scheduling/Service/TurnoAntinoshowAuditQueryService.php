<?php

namespace common\components\Domain\Scheduling\Service;

use common\models\Platform\AgentRun;
use common\models\TurnoEventoAudit;
use common\models\TurnoNotificacionProgramada;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;

/**
 * Lecturas admin del agente A04 anti no-show.
 */
final class TurnoAntinoshowAuditQueryService
{
    private const NOTIF_TIPOS = [
        TurnoNotificacionProgramada::TIPO_ANTINOSHOW_CHECKPOINT,
        TurnoNotificacionProgramada::TIPO_ANTINOSHOW_RELEASE,
    ];

    private const EVENT_TYPES = [
        TurnoEventoAudit::EVENT_CONFIRMATION_REQUESTED,
        TurnoEventoAudit::EVENT_CONFIRMATION_DELIVERY_CONFIRMED,
        TurnoEventoAudit::EVENT_CONFIRMATION_OPENED,
        TurnoEventoAudit::EVENT_CONFIRMED,
        TurnoEventoAudit::EVENT_SYSTEM_SLOT_RELEASED,
        TurnoEventoAudit::EVENT_APPOINTMENT_CANCELLED,
    ];

    /**
     * @param array<string, mixed> $filters
     */
    public function buildNotifListProvider(array $filters = [], int $pageSize = 40): ActiveDataProvider
    {
        return new ActiveDataProvider([
            'query' => $this->buildNotifQuery($filters),
            'pagination' => ['pageSize' => $pageSize],
            'sort' => [
                'defaultOrder' => ['run_at' => SORT_DESC, 'id' => SORT_DESC],
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function buildFailedNotifListProvider(array $filters = [], int $pageSize = 40): ActiveDataProvider
    {
        $filters['failed_only'] = true;

        return $this->buildNotifListProvider($filters, $pageSize);
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function buildNotifQuery(array $filters = []): ActiveQuery
    {
        $q = TurnoNotificacionProgramada::find()
            ->andWhere(['tipo' => self::NOTIF_TIPOS]);

        if (!empty($filters['failed_only'])) {
            $now = date('Y-m-d H:i:s');
            $q->andWhere([
                'or',
                ['estado' => TurnoNotificacionProgramada::ESTADO_FALLIDA],
                [
                    'and',
                    ['estado' => TurnoNotificacionProgramada::ESTADO_PENDIENTE],
                    ['<', 'run_at', $now],
                ],
            ]);
        }

        if (isset($filters['tipo']) && is_string($filters['tipo']) && $filters['tipo'] !== '') {
            $q->andWhere(['tipo' => $filters['tipo']]);
        }

        if (isset($filters['estado']) && is_string($filters['estado']) && $filters['estado'] !== '') {
            $q->andWhere(['estado' => $filters['estado']]);
        }

        if (isset($filters['id_turno']) && (int) $filters['id_turno'] > 0) {
            $q->andWhere(['id_turno' => (int) $filters['id_turno']]);
        }

        if (isset($filters['date_from']) && is_string($filters['date_from']) && trim($filters['date_from']) !== '') {
            $q->andWhere(['>=', 'run_at', trim($filters['date_from']) . ' 00:00:00']);
        }
        if (isset($filters['date_to']) && is_string($filters['date_to']) && trim($filters['date_to']) !== '') {
            $q->andWhere(['<=', 'run_at', trim($filters['date_to']) . ' 23:59:59']);
        }

        return $q;
    }

    /**
     * @return list<AgentRun>
     */
    public function listAgentRunsForTurno(int $idTurno): array
    {
        if ($idTurno <= 0) {
            return [];
        }

        /** @var list<AgentRun> $rows */
        $rows = AgentRun::find()
            ->where([
                'agent_id' => TurnoAntinoshowAgent::AGENT_ID,
                'trigger_id' => $idTurno,
            ])
            ->orderBy(['created_at' => SORT_ASC, 'id' => SORT_ASC])
            ->limit(100)
            ->all();

        return $rows;
    }

    /**
     * @return list<TurnoEventoAudit>
     */
    public function listRelatedEventsForTurno(int $idTurno): array
    {
        if ($idTurno <= 0) {
            return [];
        }

        /** @var list<TurnoEventoAudit> $rows */
        $rows = TurnoEventoAudit::find()
            ->where(['id_turno' => $idTurno])
            ->andWhere([
                'or',
                ['tipo_evento' => self::EVENT_TYPES],
                ['event_code' => self::EVENT_TYPES],
            ])
            ->orderBy(['occurred_at' => SORT_ASC, 'id' => SORT_ASC])
            ->limit(100)
            ->all();

        return $rows;
    }

    /**
     * @return list<TurnoNotificacionProgramada>
     */
    public function listSiblingNotifsForTurno(int $idTurno): array
    {
        if ($idTurno <= 0) {
            return [];
        }

        /** @var list<TurnoNotificacionProgramada> $rows */
        $rows = TurnoNotificacionProgramada::find()
            ->where(['id_turno' => $idTurno, 'tipo' => self::NOTIF_TIPOS])
            ->orderBy(['run_at' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        return $rows;
    }

    /**
     * @return list<string>
     */
    public static function notifTipos(): array
    {
        return self::NOTIF_TIPOS;
    }
}
