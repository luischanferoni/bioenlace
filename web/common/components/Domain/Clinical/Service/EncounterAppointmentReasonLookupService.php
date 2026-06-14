<?php

namespace common\components\Domain\Clinical\Service;

use common\models\Clinical\Encounter;
use common\models\ConsultaMotivosMessage;
use common\models\Scheduling\Turno;
use yii\db\Expression;
use yii\db\Query;

/**
 * Motivos pre-consulta y último encounter ambulatorio ligado a turno (sin tabla `consultas`).
 */
final class EncounterAppointmentReasonLookupService
{
    /**
     * Último `reason_text` no vacío del encounter asociado al turno más reciente de la persona.
     */
    public function ultimoMotivoTextoDesdeTurno(int $personaId, ?int $idEfector = null): ?string
    {
        if ($personaId <= 0) {
            return null;
        }

        $query = $this->baseTurnoEncounterQuery($personaId, $idEfector)
            ->select([
                'motivo' => new Expression('NULLIF(TRIM(enc.reason_text), "")'),
            ])
            ->andWhere(new Expression('NULLIF(TRIM(enc.reason_text), "") IS NOT NULL'))
            ->limit(1);

        $motivo = $query->scalar();

        return is_string($motivo) ? trim($motivo) : null;
    }

    /**
     * Id del encounter del turno más reciente (aunque `reason_text` esté vacío — p. ej. mensajes en app paciente).
     */
    public function ultimoEncounterIdDesdeTurno(int $personaId, ?int $idEfector = null): ?int
    {
        if ($personaId <= 0) {
            return null;
        }

        $id = $this->baseTurnoEncounterQuery($personaId, $idEfector)
            ->select(['enc.id'])
            ->limit(1)
            ->scalar();

        return $id !== false && $id !== null ? (int) $id : null;
    }

    /**
     * Encounter abierto o más reciente para un turno (vía `appointment_id`).
     */
    public function encounterIdParaTurno(int $turnoId): ?int
    {
        if ($turnoId <= 0) {
            return null;
        }

        $encounter = Encounter::findActive()
            ->andWhere(['appointment_id' => $turnoId])
            ->orderBy(['id' => SORT_DESC])
            ->one();

        return $encounter !== null ? (int) $encounter->id : null;
    }

    /**
     * Encounter del turno más reciente (fecha/hora) que tenga mensajes en `interaccion_motivos_consulta`.
     */
    public function encounterIdConMensajesMotivosRecientes(int $personaId, ?int $idEfector = null): ?int
    {
        if ($personaId <= 0) {
            return null;
        }

        $id = $this->baseTurnoEncounterQuery($personaId, $idEfector)
            ->innerJoin(
                ['m' => ConsultaMotivosMessage::tableName()],
                'm.encounter_id = enc.id'
            )
            ->select(['enc.id'])
            ->limit(1)
            ->scalar();

        return $id !== false && $id !== null ? (int) $id : null;
    }

    /**
     * Turnos ambulatorios del paciente con encounter (para elegir contexto de motivos en HC).
     *
     * @return list<array{
     *   turno_id: int,
     *   encounter_id: int,
     *   fecha: string,
     *   hora: string,
     *   estado: string,
     *   mensajes_count: int
     * }>
     */
    public function listarTurnosConEncounterParaMotivos(int $personaId, ?int $idEfector = null): array
    {
        if ($personaId <= 0) {
            return [];
        }

        $subMensajes = (new Query())
            ->select(['encounter_id', 'cnt' => 'COUNT(*)'])
            ->from(ConsultaMotivosMessage::tableName())
            ->groupBy('encounter_id');

        $rows = $this->baseTurnoEncounterQuery($personaId, $idEfector)
            ->select([
                'turno_id' => 't.id_turnos',
                'encounter_id' => 'enc.id',
                'fecha' => 't.fecha',
                'hora' => 't.hora',
                'estado' => 't.estado',
                'mensajes_count' => 'COALESCE(m.cnt, 0)',
            ])
            ->leftJoin(['m' => $subMensajes], 'm.encounter_id = enc.id')
            ->asArray()
            ->all();

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'turno_id' => (int) $row['turno_id'],
                'encounter_id' => (int) $row['encounter_id'],
                'fecha' => (string) $row['fecha'],
                'hora' => (string) $row['hora'],
                'estado' => (string) $row['estado'],
                'mensajes_count' => (int) $row['mensajes_count'],
            ];
        }

        return $out;
    }

    /**
     * Mismos joins que el timeline legacy de consultas por turno.
     *
     * @return \yii\db\ActiveQuery
     */
    private function baseTurnoEncounterQuery(int $personaId, ?int $idEfector)
    {
        $query = Encounter::findActive()
            ->alias('enc')
            ->innerJoin(['t' => Turno::tableName()], 'enc.appointment_id = t.id_turnos')
            ->leftJoin(
                ['pes' => 'profesional_efector_servicio'],
                'pes.id = t.id_profesional_efector_servicio AND pes.deleted_at IS NULL'
            )
            ->innerJoin('servicios s', 's.id_servicio = t.id_servicio_asignado')
            ->innerJoin('efectores e', 'e.id_efector = t.id_efector')
            ->innerJoin(
                'servicios_efector se',
                'se.id_servicio = t.id_servicio_asignado AND se.id_efector = t.id_efector'
            )
            ->leftJoin('servicios pase_prev', 'pase_prev.id_servicio = se.pase_previo')
            ->where(['t.id_persona' => $personaId])
            ->andWhere(['or', ['is not', 'pes.id', null], ['>', 't.id_profesional_efector_servicio', 0]])
            ->andWhere('t.deleted_at IS NULL')
            ->andWhere(['enc.appointment_id' => new Expression('t.id_turnos')])
            ->orderBy(['t.fecha' => SORT_DESC, 't.hora' => SORT_DESC, 'enc.id' => SORT_DESC]);

        if ($idEfector !== null && $idEfector > 0) {
            $query->andWhere(['t.id_efector' => $idEfector]);
        }

        return $query;
    }
}
