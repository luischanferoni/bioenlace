<?php

namespace common\components\Domain\Scheduling\Service\BehaviorProfile;

use common\models\Scheduling\Turno;
use common\models\TurnoEventoAudit;
use yii\db\Query;

/**
 * Backfill idempotente de eventos canónicos desde snapshots de {@see Turno}.
 * No inventa actor ni entrega de notificaciones; marca LEGACY_INFERRED.
 */
final class TurnoBehaviorProfileBackfillService
{
    private TurnoCanonicalEventService $events;

    public function __construct(?TurnoCanonicalEventService $events = null)
    {
        $this->events = $events ?? new TurnoCanonicalEventService();
    }

    /**
     * @return array{processed: int, written: int, skipped: int}
     */
    public function backfill(?int $idPersona = null, ?int $limit = null, int $offset = 0): array
    {
        $q = (new Query())
            ->from(Turno::tableName())
            ->select([
                'id_turnos',
                'id_persona',
                'estado',
                'estado_motivo',
                'fecha',
                'hora',
                'confirmado',
                'confirmado_en',
                'created_at',
                'deleted_at',
                'updated_at',
                'fecha_alta',
            ])
            ->where(['>', 'id_persona', 0])
            ->orderBy(['id_turnos' => SORT_ASC]);

        if ($idPersona !== null && $idPersona > 0) {
            $q->andWhere(['id_persona' => $idPersona]);
        }
        if ($offset > 0) {
            $q->offset($offset);
        }
        if ($limit !== null && $limit > 0) {
            $q->limit($limit);
        }

        $processed = 0;
        $written = 0;
        $skipped = 0;

        foreach ($q->each(200) as $row) {
            $processed++;
            $n = $this->backfillTurnoRow($row);
            $written += $n;
            if ($n === 0) {
                $skipped++;
            }
        }

        return [
            'processed' => $processed,
            'written' => $written,
            'skipped' => $skipped,
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    public function backfillTurnoRow(array $row): int
    {
        $idTurno = (int) ($row['id_turnos'] ?? 0);
        $idPersona = (int) ($row['id_persona'] ?? 0);
        if ($idTurno <= 0 || $idPersona <= 0) {
            return 0;
        }

        $written = 0;
        $createdAt = $this->pickTimestamp($row, ['created_at', 'fecha_alta']);
        $cmd = TurnoCanonicalEventCommand::create(
            $idTurno,
            $idPersona,
            TurnoEventoAudit::EVENT_APPOINTMENT_CREATED,
            TurnoEventoAudit::ACTOR_SISTEMA,
            'backfill:' . $idTurno . ':' . TurnoEventoAudit::EVENT_APPOINTMENT_CREATED,
            TurnoEventoAudit::QUALITY_LEGACY_INFERRED,
            null,
            null,
            'backfill',
            null,
            $createdAt,
            ['source' => 'turnos_snapshot'],
            TurnoEventoAudit::TIPO_CREATE
        );
        $before = TurnoEventoAudit::findOne(['idempotency_key' => $cmd->idempotencyKey]);
        $this->events->record($cmd);
        if ($before === null) {
            $written++;
        }

        $estado = (string) ($row['estado'] ?? '');
        $motivo = (string) ($row['estado_motivo'] ?? '');
        $outcomeAt = $this->pickTimestamp($row, ['deleted_at', 'updated_at', 'created_at']) ?: $createdAt;

        if ($estado === Turno::ESTADO_CANCELADO) {
            $actor = $this->actorFromHistoricalAudit($idTurno)
                ?: $this->actorFromCancelMotivo($motivo);
            $cancelCmd = TurnoCanonicalEventCommand::create(
                $idTurno,
                $idPersona,
                TurnoEventoAudit::EVENT_APPOINTMENT_CANCELLED,
                $actor,
                'backfill:' . $idTurno . ':' . TurnoEventoAudit::EVENT_APPOINTMENT_CANCELLED,
                TurnoEventoAudit::QUALITY_LEGACY_INFERRED,
                null,
                null,
                'backfill',
                $motivo !== '' ? $motivo : null,
                $outcomeAt,
                [
                    'source' => 'turnos_snapshot',
                    'estado_motivo' => $motivo,
                ],
                $actor === TurnoEventoAudit::ACTOR_PACIENTE
                    ? TurnoEventoAudit::TIPO_CANCEL_PAC
                    : TurnoEventoAudit::TIPO_CANCEL_MED
            );
            $before = TurnoEventoAudit::findOne(['idempotency_key' => $cancelCmd->idempotencyKey]);
            $this->events->record($cancelCmd);
            if ($before === null) {
                $written++;
            }
        } elseif ($estado === Turno::ESTADO_ATENDIDO) {
            $attCmd = TurnoCanonicalEventCommand::create(
                $idTurno,
                $idPersona,
                TurnoEventoAudit::EVENT_ATTENDED,
                TurnoEventoAudit::ACTOR_STAFF,
                'backfill:' . $idTurno . ':' . TurnoEventoAudit::EVENT_ATTENDED,
                TurnoEventoAudit::QUALITY_LEGACY_INFERRED,
                null,
                null,
                'backfill',
                null,
                $outcomeAt,
                ['source' => 'turnos_snapshot'],
                null
            );
            $before = TurnoEventoAudit::findOne(['idempotency_key' => $attCmd->idempotencyKey]);
            $this->events->record($attCmd);
            if ($before === null) {
                $written++;
            }
        } elseif ($estado === Turno::ESTADO_SIN_ATENDER) {
            $actor = $motivo === Turno::ESTADO_MOTIVO_SIN_ATENDER_PACIENTE
                ? TurnoEventoAudit::ACTOR_PACIENTE
                : TurnoEventoAudit::ACTOR_STAFF;
            // Solo no-show atribuible a paciente entra al perfil conductual; staff se registra igual con actor STAFF.
            $nsCmd = TurnoCanonicalEventCommand::create(
                $idTurno,
                $idPersona,
                TurnoEventoAudit::EVENT_NO_SHOW_RECORDED,
                $actor,
                'backfill:' . $idTurno . ':' . TurnoEventoAudit::EVENT_NO_SHOW_RECORDED,
                TurnoEventoAudit::QUALITY_LEGACY_INFERRED,
                null,
                null,
                'backfill',
                $motivo !== '' ? $motivo : null,
                $outcomeAt,
                [
                    'source' => 'turnos_snapshot',
                    'estado_motivo' => $motivo,
                ],
                TurnoEventoAudit::TIPO_NO_SHOW
            );
            $before = TurnoEventoAudit::findOne(['idempotency_key' => $nsCmd->idempotencyKey]);
            $this->events->record($nsCmd);
            if ($before === null) {
                $written++;
            }
        }

        if (!empty($row['confirmado']) || !empty($row['confirmado_en'])) {
            $confAt = $this->pickTimestamp($row, ['confirmado_en', 'updated_at']) ?: $createdAt;
            $confCmd = TurnoCanonicalEventCommand::create(
                $idTurno,
                $idPersona,
                TurnoEventoAudit::EVENT_CONFIRMED,
                TurnoEventoAudit::ACTOR_PACIENTE,
                'backfill:' . $idTurno . ':' . TurnoEventoAudit::EVENT_CONFIRMED,
                TurnoEventoAudit::QUALITY_LEGACY_INFERRED,
                null,
                null,
                'backfill',
                null,
                $confAt,
                ['source' => 'turnos_snapshot'],
                TurnoEventoAudit::TIPO_CONFIRMED
            );
            $before = TurnoEventoAudit::findOne(['idempotency_key' => $confCmd->idempotencyKey]);
            $this->events->record($confCmd);
            if ($before === null) {
                $written++;
            }
        }

        return $written;
    }

    private function actorFromCancelMotivo(string $motivo): string
    {
        switch ($motivo) {
            case Turno::ESTADO_MOTIVO_CANCELADO_PACIENTE:
                return TurnoEventoAudit::ACTOR_PACIENTE;
            case Turno::ESTADO_MOTIVO_CANCELADO_SISTEMA:
                return TurnoEventoAudit::ACTOR_SISTEMA;
            case Turno::ESTADO_MOTIVO_CANCELADO_EFECTOR:
                return TurnoEventoAudit::ACTOR_EFECTOR;
            case Turno::ESTADO_MOTIVO_CANCELADO_MEDICO:
            case Turno::ESTADO_MOTIVO_ERROR_CARGA:
            default:
                // Sin motivo confiable: no atribuir al paciente.
                return TurnoEventoAudit::ACTOR_STAFF;
        }
    }

    private function actorFromHistoricalAudit(int $idTurno): ?string
    {
        $rows = TurnoEventoAudit::find()
            ->select(['actor_type', 'meta_json'])
            ->where(['id_turno' => $idTurno])
            ->andWhere(['in', 'tipo_evento', [
                TurnoEventoAudit::TIPO_CANCEL_PAC,
                TurnoEventoAudit::TIPO_CANCEL_MED,
                TurnoEventoAudit::TIPO_BULK_DAY_CANCEL,
            ]])
            ->orderBy(['id' => SORT_DESC])
            ->asArray()
            ->all();

        foreach ($rows as $row) {
            $actor = (string) ($row['actor_type'] ?? '');
            if (in_array($actor, TurnoEventoAudit::actorTypeValues(), true)) {
                return $actor;
            }
            $meta = json_decode((string) ($row['meta_json'] ?? ''), true);
            if (is_array($meta) && (string) ($meta['canal'] ?? '') === 'sistema') {
                return TurnoEventoAudit::ACTOR_SISTEMA;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string> $keys
     */
    private function pickTimestamp(array $row, array $keys): ?string
    {
        foreach ($keys as $k) {
            $v = trim((string) ($row[$k] ?? ''));
            if ($v !== '' && $v !== '0000-00-00 00:00:00') {
                return $v;
            }
        }

        return null;
    }
}
