<?php

namespace common\components\Domain\Scheduling\Service\BehaviorProfile;

use common\models\Scheduling\Turno;
use common\models\TurnoEventoAudit;
use Yii;
use yii\db\IntegrityException;

/**
 * Escritor único e idempotente del stream canónico de eventos de turno.
 */
final class TurnoCanonicalEventService
{
    private TurnoBehaviorProfileContract $contract;

    public function __construct(?TurnoBehaviorProfileContract $contract = null)
    {
        $this->contract = $contract ?? new TurnoBehaviorProfileContract();
    }

    public function record(TurnoCanonicalEventCommand $cmd): TurnoEventoAudit
    {
        $key = trim($cmd->idempotencyKey);
        if ($key === '') {
            throw new \InvalidArgumentException('idempotency_key es obligatorio');
        }
        if ($cmd->idTurno <= 0 || $cmd->idPersona <= 0) {
            throw new \InvalidArgumentException('id_turno e id_persona son obligatorios');
        }
        if (!in_array($cmd->eventCode, $this->contract->eventCodes(), true)) {
            throw new \InvalidArgumentException('event_code no está en el contrato: ' . $cmd->eventCode);
        }
        if (!in_array($cmd->actorType, TurnoEventoAudit::actorTypeValues(), true)) {
            throw new \InvalidArgumentException('actor_type inválido: ' . $cmd->actorType);
        }
        if (!in_array($cmd->attributionQuality, TurnoEventoAudit::attributionQualityValues(), true)) {
            throw new \InvalidArgumentException('attribution_quality inválida');
        }
        if ($cmd->eventCode === TurnoEventoAudit::EVENT_NO_SHOW_CORRECTED) {
            if ($cmd->correctedEventId === null) {
                throw new \InvalidArgumentException('NO_SHOW_CORRECTED requiere corrected_event_id');
            }
            $corrected = TurnoEventoAudit::findOne([
                'id' => $cmd->correctedEventId,
                'id_turno' => $cmd->idTurno,
                'id_persona' => $cmd->idPersona,
                'event_code' => TurnoEventoAudit::EVENT_NO_SHOW_RECORDED,
            ]);
            if ($corrected === null) {
                throw new \InvalidArgumentException('El evento corregido no es un no-show del mismo turno y persona');
            }
        }
        if ($cmd->eventCode === TurnoEventoAudit::EVENT_APPOINTMENT_RESCHEDULED
            && (!is_array($cmd->meta['before'] ?? null) || !is_array($cmd->meta['after'] ?? null))
        ) {
            throw new \InvalidArgumentException('APPOINTMENT_RESCHEDULED requiere snapshots before y after');
        }

        $existing = TurnoEventoAudit::findOne(['idempotency_key' => $key]);
        if ($existing !== null) {
            return $existing;
        }

        $legacyTipo = $cmd->legacyTipoEvento
            ?: $this->contract->legacyTipoForEvent($cmd->eventCode)
            ?: $cmd->eventCode;
        $turno = Turno::findOne(['id_turnos' => $cmd->idTurno]);

        $row = new TurnoEventoAudit();
        $row->id_turno = $cmd->idTurno;
        $row->id_persona = $cmd->idPersona;
        $row->tipo_evento = $legacyTipo;
        $row->event_code = $cmd->eventCode;
        $row->id_user = $cmd->idUser;
        $row->actor_type = $cmd->actorType;
        $row->channel = $cmd->channel;
        $row->origin = $cmd->origin;
        $row->motivo_normalizado = $cmd->motivoNormalizado;
        $row->idempotency_key = $key;
        $row->attribution_quality = $cmd->attributionQuality;
        $row->corrected_event_id = $cmd->correctedEventId;
        $row->id_turno_relacionado = $cmd->idTurnoRelacionado;
        $row->related_turno_role = $cmd->relatedTurnoRole;
        $row->occurred_at = $cmd->occurredAt ?: date('Y-m-d H:i:s');
        if ($turno !== null) {
            $fecha = trim((string) ($turno->fecha ?? ''));
            $hora = trim((string) ($turno->hora ?? ''));
            $row->appointment_at = $fecha !== ''
                ? trim($fecha . ' ' . ($hora !== '' ? $hora : '00:00:00'))
                : null;
            $row->id_efector = (int) ($turno->id_efector ?? 0) ?: null;
            $row->id_servicio = (int) ($turno->id_servicio_asignado ?? 0) ?: null;
            $row->id_profesional_efector_servicio =
                (int) ($turno->id_profesional_efector_servicio ?? 0) ?: null;
            $row->modalidad = trim((string) ($turno->tipo_atencion ?? '')) ?: null;
        }
        $row->meta_json = $cmd->meta !== []
            ? json_encode($cmd->meta, JSON_UNESCAPED_UNICODE)
            : null;

        try {
            if (!$row->save(false)) {
                throw new \RuntimeException('No se pudo persistir turno_evento_audit');
            }
        } catch (IntegrityException $e) {
            $again = TurnoEventoAudit::findOne(['idempotency_key' => $key]);
            if ($again !== null) {
                return $again;
            }
            throw $e;
        }

        return $row;
    }

    /**
     * Puente compat: {@see TurnoEventoAudit::registrar()}.
     *
     * @param array<string, mixed> $meta
     */
    public function recordFromLegacy(int $idTurno, string $tipo, ?int $idUser, array $meta = []): TurnoEventoAudit
    {
        $turno = Turno::findOne(['id_turnos' => $idTurno]);
        $idPersona = $turno !== null ? (int) $turno->id_persona : 0;
        if ($idPersona <= 0) {
            // Fallback mínimo para no romper callers si el turno ya no está.
            $idPersona = (int) ($meta['id_persona'] ?? 0);
        }
        if ($idPersona <= 0) {
            Yii::warning("TurnoCanonicalEventService: sin id_persona para turno {$idTurno}", __METHOD__);
            $idPersona = 0;
        }

        $eventCode = $this->contract->eventCodeForLegacyTipo($tipo) ?: $tipo;
        $actor = $this->inferActorFromLegacy($tipo, $meta);
        $channel = isset($meta['canal']) ? (string) $meta['canal'] : null;
        $motivo = isset($meta['razon_cancelacion']) ? (string) $meta['razon_cancelacion'] : null;
        $key = 'legacy:' . $idTurno . ':' . $tipo . ':' . md5(json_encode($meta, JSON_UNESCAPED_UNICODE) ?: '');

        // Sin persona no usamos el path canónico estricto: escritura directa legacy.
        if ($idPersona <= 0) {
            $r = new TurnoEventoAudit();
            $r->id_turno = $idTurno;
            $r->tipo_evento = $tipo;
            $r->event_code = $eventCode;
            $r->id_user = $idUser;
            $r->meta_json = $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null;
            $r->occurred_at = date('Y-m-d H:i:s');
            $r->attribution_quality = TurnoEventoAudit::QUALITY_NATIVE;
            $r->actor_type = $actor;
            $r->channel = $channel;
            $r->motivo_normalizado = $motivo;
            $r->idempotency_key = $key;
            if ($turno !== null) {
                $r->appointment_at = trim((string) $turno->fecha . ' ' . (string) $turno->hora);
                $r->id_efector = (int) ($turno->id_efector ?? 0) ?: null;
                $r->id_servicio = (int) ($turno->id_servicio_asignado ?? 0) ?: null;
                $r->id_profesional_efector_servicio =
                    (int) ($turno->id_profesional_efector_servicio ?? 0) ?: null;
                $r->modalidad = trim((string) ($turno->tipo_atencion ?? '')) ?: null;
            }
            $r->save(false);

            return $r;
        }

        return $this->record(TurnoCanonicalEventCommand::create(
            $idTurno,
            $idPersona,
            $eventCode,
            $actor,
            $key,
            TurnoEventoAudit::QUALITY_NATIVE,
            $idUser,
            $channel,
            isset($meta['origin']) ? (string) $meta['origin'] : 'legacy_registrar',
            $motivo,
            null,
            $meta,
            $tipo
        ));
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function inferActorFromLegacy(string $tipo, array $meta): string
    {
        if (isset($meta['actor_type']) && in_array((string) $meta['actor_type'], TurnoEventoAudit::actorTypeValues(), true)) {
            return (string) $meta['actor_type'];
        }
        if ($tipo === TurnoEventoAudit::TIPO_CANCEL_PAC) {
            return TurnoEventoAudit::ACTOR_PACIENTE;
        }
        if ($tipo === TurnoEventoAudit::TIPO_CANCEL_MED || $tipo === TurnoEventoAudit::TIPO_BULK_DAY_CANCEL) {
            return TurnoEventoAudit::ACTOR_STAFF;
        }
        if ($tipo === TurnoEventoAudit::TIPO_CONFIRMED) {
            return TurnoEventoAudit::ACTOR_PACIENTE;
        }
        if (($meta['canal'] ?? '') === 'sistema') {
            return TurnoEventoAudit::ACTOR_SISTEMA;
        }

        return TurnoEventoAudit::ACTOR_STAFF;
    }
}
