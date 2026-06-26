<?php

namespace common\components\Domain\Scheduling\Service;

use common\components\Platform\Core\Product\AutonomousAgentMetadata;
use common\models\Scheduling\Turno;
use common\models\Scheduling\TurnoWaitlistEntry;
use Yii;

/**
 * Alta/baja y consulta de inscripciones a lista de espera.
 */
final class TurnoWaitlistService
{
    /**
     * @return array{id: int, enrolled_at: string}
     */
    public function enroll(
        int $subjectPersonaId,
        int $idEfector,
        int $idServicio,
        ?int $idPes = null,
        ?string $urgencyBand = null
    ): array {
        if ($subjectPersonaId <= 0 || $idEfector <= 0 || $idServicio <= 0) {
            throw new \InvalidArgumentException('Faltan datos para inscribirse en lista de espera.');
        }

        if ($this->hasActiveEnrollment($subjectPersonaId, $idEfector, $idServicio)) {
            throw new \InvalidArgumentException('Ya estás inscripto en la lista de espera de este servicio.');
        }

        $band = $urgencyBand !== null ? strtoupper(trim($urgencyBand)) : null;
        if ($band === '') {
            $band = null;
        }

        $now = date('Y-m-d H:i:s');
        $row = new TurnoWaitlistEntry();
        $row->subject_persona_id = $subjectPersonaId;
        $row->id_efector = $idEfector;
        $row->id_servicio = $idServicio;
        $row->id_profesional_efector_servicio = $idPes !== null && $idPes > 0 ? $idPes : null;
        $row->urgency_band = $band;
        $row->estado = TurnoWaitlistEntry::ESTADO_ACTIVE;
        $row->enrolled_at = $now;
        $row->created_at = $now;
        if (!$row->save()) {
            throw new \RuntimeException('No se pudo inscribir en lista de espera: ' . json_encode($row->errors));
        }

        return ['id' => (int) $row->id, 'enrolled_at' => $now];
    }

    public function cancelEnrollment(int $entryId, int $subjectPersonaId): bool
    {
        $row = TurnoWaitlistEntry::findOne(['id' => $entryId, 'subject_persona_id' => $subjectPersonaId]);
        if ($row === null) {
            throw new \InvalidArgumentException('Inscripción no encontrada.');
        }
        if (!in_array($row->estado, [TurnoWaitlistEntry::ESTADO_ACTIVE, TurnoWaitlistEntry::ESTADO_OFFERED], true)) {
            return false;
        }
        $row->estado = TurnoWaitlistEntry::ESTADO_CANCELLED;
        $row->offer_token = null;
        $row->offer_expires_at = null;
        $row->updated_at = date('Y-m-d H:i:s');

        return (bool) $row->save(false);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getActiveEnrollmentForPersona(int $subjectPersonaId, int $idEfector, int $idServicio): ?array
    {
        $row = TurnoWaitlistEntry::find()
            ->where([
                'subject_persona_id' => $subjectPersonaId,
                'id_efector' => $idEfector,
                'id_servicio' => $idServicio,
            ])
            ->andWhere(['in', 'estado', [
                TurnoWaitlistEntry::ESTADO_ACTIVE,
                TurnoWaitlistEntry::ESTADO_OFFERED,
            ]])
            ->orderBy(['id' => SORT_DESC])
            ->one();

        return $row instanceof TurnoWaitlistEntry ? $this->serializeEntry($row) : null;
    }

    public function hasActiveEnrollment(int $subjectPersonaId, int $idEfector, int $idServicio): bool
    {
        return TurnoWaitlistEntry::find()
            ->where([
                'subject_persona_id' => $subjectPersonaId,
                'id_efector' => $idEfector,
                'id_servicio' => $idServicio,
            ])
            ->andWhere(['in', 'estado', [
                TurnoWaitlistEntry::ESTADO_ACTIVE,
                TurnoWaitlistEntry::ESTADO_OFFERED,
            ]])
            ->exists();
    }

    /**
     * @return TurnoWaitlistEntry|null
     */
    public function findNextFifoCandidate(
        int $idEfector,
        int $idServicio,
        int $idPes,
        int $slotOfferId,
        ?array $agentConfig = null
    ): ?TurnoWaitlistEntry {
        $config = $agentConfig ?? AutonomousAgentMetadata::loadAgent(TurnoWaitlistFillAgent::AGENT_ID) ?? [];
        $excludeBands = array_map('strtoupper', $config['exclude_urgency_bands_from_offers'] ?? []);

        $alreadyOfferedPersonaIds = TurnoWaitlistEntry::find()
            ->select(['subject_persona_id'])
            ->where(['slot_offer_id' => $slotOfferId])
            ->andWhere(['in', 'estado', [
                TurnoWaitlistEntry::ESTADO_OFFERED,
                TurnoWaitlistEntry::ESTADO_EXPIRED,
                TurnoWaitlistEntry::ESTADO_FULFILLED,
            ]])
            ->column();

        $query = TurnoWaitlistEntry::find()
            ->where([
                'id_efector' => $idEfector,
                'id_servicio' => $idServicio,
                'estado' => TurnoWaitlistEntry::ESTADO_ACTIVE,
            ])
            ->andWhere([
                'or',
                ['id_profesional_efector_servicio' => null],
                ['id_profesional_efector_servicio' => $idPes],
            ])
            ->orderBy(['enrolled_at' => SORT_ASC]);

        if ($alreadyOfferedPersonaIds !== []) {
            $query->andWhere(['not in', 'subject_persona_id', $alreadyOfferedPersonaIds]);
        }

        /** @var TurnoWaitlistEntry[] $candidates */
        $candidates = $query->limit(50)->all();
        foreach ($candidates as $candidate) {
            $band = strtoupper(trim((string) ($candidate->urgency_band ?? '')));
            if ($band !== '' && in_array($band, $excludeBands, true)) {
                continue;
            }

            return $candidate;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeEntry(TurnoWaitlistEntry $row): array
    {
        return [
            'id' => (int) $row->id,
            'estado' => (string) $row->estado,
            'id_efector' => (int) $row->id_efector,
            'id_servicio' => (int) $row->id_servicio,
            'id_profesional_efector_servicio' => $row->id_profesional_efector_servicio !== null
                ? (int) $row->id_profesional_efector_servicio
                : null,
            'enrolled_at' => (string) $row->enrolled_at,
            'offer_expires_at' => $row->offer_expires_at,
            'offer_token' => $row->offer_token,
        ];
    }

    /**
     * @param Turno $cancelled
     * @return array{id_profesional_efector_servicio: int, fecha: string, hora: string, slot_id: string}|null
     */
    public static function slotPayloadFromTurno(Turno $cancelled): ?array
    {
        $idPes = (int) ($cancelled->id_profesional_efector_servicio ?? 0);
        $fecha = trim((string) ($cancelled->fecha ?? ''));
        $hora = substr(trim((string) ($cancelled->hora ?? '')), 0, 5);
        if ($idPes <= 0 || $fecha === '' || $hora === '') {
            return null;
        }
        $intervalo = (int) ($cancelled->intervalo_minutos_reserva ?? 0);
        $slotId = 'pes:' . $idPes . '|' . $fecha . '|' . $hora;
        if ($intervalo > 0) {
            $slotId .= '|' . $intervalo;
        }

        return [
            'id_profesional_efector_servicio' => $idPes,
            'fecha' => $fecha,
            'hora' => $hora,
            'slot_id' => $slotId,
            'id_efector' => (int) ($cancelled->id_efector ?? 0),
            'id_servicio' => (int) ($cancelled->id_servicio_asignado ?? 0),
        ];
    }
}
