<?php

namespace common\components\Domain\Scheduling\Service\BehaviorProfile;

use common\models\Scheduling\PersonaTurnosPerfil;
use common\models\Scheduling\PersonaTurnosPerfilMaterializacion;
use common\models\Scheduling\PersonaTurnosPerfilMetrica;
use common\models\Scheduling\Turno;
use common\models\TurnoEventoAudit;
use Yii;
use yii\db\Query;

/**
 * Materializa snapshots inmutables del perfil factual (sin risk_level).
 */
final class TurnoBehaviorProfileMaterializerService
{
    private TurnoBehaviorProfileContract $contract;
    private TurnoBehaviorProfileCalculator $calculator;

    public function __construct(
        ?TurnoBehaviorProfileContract $contract = null,
        ?TurnoBehaviorProfileCalculator $calculator = null
    ) {
        $this->contract = $contract ?? new TurnoBehaviorProfileContract();
        $this->calculator = $calculator ?? new TurnoBehaviorProfileCalculator($this->contract);
    }

    /**
     * Incremental: personas con eventos posteriores al watermark.
     *
     * @return array{personas: int, perfiles: int, watermark: int|null}
     */
    public function materializeIncremental(?int $limitPersonas = null): array
    {
        $state = $this->ensureState(PersonaTurnosPerfilMaterializacion::STATUS_RUNNING);
        $afterId = (int) ($state->last_watermark_event_id ?? 0);

        try {
            // El límite se aplica a eventos, no a personas: así el cursor nunca salta
            // eventos intercalados de una persona excluida por LIMIT DISTINCT.
            $eventLimit = max(1, $limitPersonas ?? 500);
            $eventRows = (new Query())
                ->select(['id', 'id_persona'])
                ->from(TurnoEventoAudit::tableName())
                ->where(['>', 'id', $afterId])
                ->andWhere(['>', 'id_persona', 0])
                ->orderBy(['id' => SORT_ASC])
                ->limit($eventLimit)
                ->all();
            if ($eventRows === []) {
                $state->last_status = PersonaTurnosPerfilMaterializacion::STATUS_OK;
                $state->last_error = null;
                $state->last_run_at = date('Y-m-d H:i:s');
                $state->updated_at = $state->last_run_at;
                $state->save(false);

                return [
                    'personas' => 0,
                    'perfiles' => 0,
                    'watermark' => $state->last_watermark_event_id !== null
                        ? (int) $state->last_watermark_event_id
                        : null,
                ];
            }

            $personaIds = [];
            $maxEventId = $afterId;
            foreach ($eventRows as $eventRow) {
                $personaIds[(int) $eventRow['id_persona']] = true;
                $maxEventId = max($maxEventId, (int) $eventRow['id']);
            }
            $personaIds = array_keys($personaIds);

            $created = 0;
            foreach ($personaIds as $idPersona) {
                $this->rebuildPersona($idPersona, $maxEventId > 0 ? $maxEventId : null);
                $created++;
            }

            $state->last_watermark_event_id = $maxEventId > 0 ? $maxEventId : $state->last_watermark_event_id;
            $state->last_status = PersonaTurnosPerfilMaterializacion::STATUS_OK;
            $state->last_error = null;
            $state->last_run_at = date('Y-m-d H:i:s');
            $state->updated_at = $state->last_run_at;
            $state->save(false);

            return [
                'personas' => count($personaIds),
                'perfiles' => $created,
                'watermark' => $state->last_watermark_event_id !== null ? (int) $state->last_watermark_event_id : null,
            ];
        } catch (\Throwable $e) {
            $state->last_status = PersonaTurnosPerfilMaterializacion::STATUS_FAILED;
            $state->last_error = $e->getMessage();
            $state->last_run_at = date('Y-m-d H:i:s');
            $state->updated_at = $state->last_run_at;
            $state->save(false);
            throw $e;
        }
    }

    /**
     * Reconstrucción completa (todas las personas con eventos, o una sola).
     *
     * @return array{personas: int, perfiles: int, watermark: int|null}
     */
    public function rebuild(?int $idPersona = null, ?int $limitPersonas = null): array
    {
        $state = $this->ensureState(PersonaTurnosPerfilMaterializacion::STATUS_RUNNING);

        try {
            $q = (new Query())
                ->select(['id_persona'])
                ->from(TurnoEventoAudit::tableName())
                ->where(['>', 'id_persona', 0])
                ->distinct()
                ->orderBy(['id_persona' => SORT_ASC]);
            if ($idPersona !== null && $idPersona > 0) {
                $q->andWhere(['id_persona' => $idPersona]);
            }
            if ($limitPersonas !== null && $limitPersonas > 0) {
                $q->limit($limitPersonas);
            }
            $personaIds = array_map('intval', $q->column());

            $maxEventId = (int) (new Query())
                ->from(TurnoEventoAudit::tableName())
                ->max('id');

            $created = 0;
            foreach ($personaIds as $pid) {
                $this->rebuildPersona($pid, $maxEventId > 0 ? $maxEventId : null);
                $created++;
            }

            if ($idPersona === null) {
                $state->last_watermark_event_id = $maxEventId > 0 ? $maxEventId : null;
            }
            $state->last_status = PersonaTurnosPerfilMaterializacion::STATUS_OK;
            $state->last_error = null;
            $state->last_run_at = date('Y-m-d H:i:s');
            $state->updated_at = $state->last_run_at;
            $state->save(false);

            return [
                'personas' => count($personaIds),
                'perfiles' => $created,
                'watermark' => $state->last_watermark_event_id !== null ? (int) $state->last_watermark_event_id : null,
            ];
        } catch (\Throwable $e) {
            $state->last_status = PersonaTurnosPerfilMaterializacion::STATUS_FAILED;
            $state->last_error = $e->getMessage();
            $state->last_run_at = date('Y-m-d H:i:s');
            $state->updated_at = $state->last_run_at;
            $state->save(false);
            throw $e;
        }
    }

    public function rebuildPersona(int $idPersona, ?int $watermarkEventId = null): PersonaTurnosPerfil
    {
        if ($idPersona <= 0) {
            throw new \InvalidArgumentException('id_persona inválido');
        }

        $asOf = date('Y-m-d H:i:s');
        $events = $this->loadEventsForPersona($idPersona, $watermarkEventId);
        $result = $this->calculator->calculate($events, $asOf);

        $tx = Yii::$app->db->beginTransaction();
        try {
            PersonaTurnosPerfil::updateAll(
                ['superseded_at' => $asOf, 'is_current' => null],
                [
                    'and',
                    ['id_persona' => $idPersona],
                    ['profile_contract_version' => $this->contract->version()],
                    ['is_current' => 1],
                ]
            );

            $perfil = new PersonaTurnosPerfil();
            $perfil->id_persona = $idPersona;
            $perfil->profile_contract_version = $this->contract->version();
            $perfil->source_watermark_event_id = $watermarkEventId;
            $perfil->as_of = $asOf;
            $perfil->completeness_status = $result['completeness_status'];
            $perfil->generated_at = $asOf;
            $perfil->superseded_at = null;
            $perfil->is_current = 1;
            if (!$perfil->save(false)) {
                throw new \RuntimeException('No se pudo guardar persona_turnos_perfil');
            }

            foreach ($result['metrics'] as $m) {
                $row = new PersonaTurnosPerfilMetrica();
                $row->id_perfil = (int) $perfil->id;
                $row->scope_type = (string) $m['scope_type'];
                $row->scope_id = $m['scope_id'] !== null && $m['scope_id'] !== ''
                    ? (string) $m['scope_id']
                    : '';
                $row->window_days = (int) $m['window_days'];
                $row->metric_code = (string) $m['metric_code'];
                $row->numerator = (int) $m['numerator'];
                $row->denominator = $m['denominator'];
                $row->value = $m['value'];
                $row->sample_size = (int) $m['sample_size'];
                $row->confidence_status = (string) $m['confidence_status'];
                if (!$row->save(false)) {
                    throw new \RuntimeException('No se pudo guardar persona_turnos_perfil_metrica');
                }
            }

            $tx->commit();

            return $perfil;
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadEventsForPersona(int $idPersona, ?int $watermarkEventId = null): array
    {
        $query = (new Query())
            ->select([
                'e.id',
                'e.id_turno',
                'e.event_code',
                'e.tipo_evento',
                'e.actor_type',
                'e.attribution_quality',
                'e.occurred_at',
                'e.created_at',
                'e.corrected_event_id',
                'e.appointment_at',
                'e.id_efector AS event_id_efector',
                'e.id_servicio AS event_id_servicio',
                'e.modalidad AS event_modalidad',
                't.id_efector',
                't.id_servicio_asignado',
                't.tipo_atencion',
                't.fecha',
                't.hora',
            ])
            ->from(['e' => TurnoEventoAudit::tableName()])
            ->leftJoin(['t' => Turno::tableName()], 't.id_turnos = e.id_turno')
            ->where(['e.id_persona' => $idPersona])
            ->orderBy(['e.id' => SORT_ASC]);
        if ($watermarkEventId !== null && $watermarkEventId > 0) {
            $query->andWhere(['<=', 'e.id', $watermarkEventId]);
        }
        $rows = $query->all();

        $out = [];
        foreach ($rows as $r) {
            $citaAt = trim((string) ($r['appointment_at'] ?? ''));
            if ($citaAt === '') {
                $fecha = (string) ($r['fecha'] ?? '');
                $hora = (string) ($r['hora'] ?? '00:00:00');
                $citaAt = $fecha !== '' ? trim($fecha . ' ' . $hora) : '';
            }
            $code = (string) ($r['event_code'] ?: $r['tipo_evento']);
            $out[] = [
                'id' => (int) $r['id'],
                'id_turno' => (int) $r['id_turno'],
                'event_code' => $code,
                'actor_type' => (string) ($r['actor_type'] ?? ''),
                'attribution_quality' => (string) ($r['attribution_quality'] ?? TurnoEventoAudit::QUALITY_NATIVE),
                'occurred_at' => (string) ($r['occurred_at'] ?: $r['created_at']),
                'cita_at' => $citaAt,
                'id_efector' => !empty($r['event_id_efector'])
                    ? (int) $r['event_id_efector']
                    : (isset($r['id_efector']) ? (int) $r['id_efector'] : null),
                'id_servicio' => !empty($r['event_id_servicio'])
                    ? (int) $r['event_id_servicio']
                    : (isset($r['id_servicio_asignado']) ? (int) $r['id_servicio_asignado'] : null),
                'modalidad' => trim((string) ($r['event_modalidad'] ?? '')) !== ''
                    ? (string) $r['event_modalidad']
                    : (string) ($r['tipo_atencion'] ?? ''),
                'corrected_event_id' => isset($r['corrected_event_id']) ? (int) $r['corrected_event_id'] : null,
            ];
        }

        return $out;
    }

    private function ensureState(string $status): PersonaTurnosPerfilMaterializacion
    {
        $version = $this->contract->version();
        $state = PersonaTurnosPerfilMaterializacion::findOne(['profile_contract_version' => $version]);
        if ($state === null) {
            $state = new PersonaTurnosPerfilMaterializacion();
            $state->profile_contract_version = $version;
            $state->last_watermark_event_id = null;
            $state->last_error = null;
        } elseif (
            $status === PersonaTurnosPerfilMaterializacion::STATUS_RUNNING
            && $state->last_status === PersonaTurnosPerfilMaterializacion::STATUS_RUNNING
            && strtotime((string) $state->updated_at) > time() - 1800
        ) {
            throw new \RuntimeException('Ya existe una materialización activa para este contrato');
        }
        $state->last_status = $status;
        $state->updated_at = date('Y-m-d H:i:s');
        $state->save(false);

        return $state;
    }
}
