<?php

namespace common\components\Domain\Clinical\Service;

use common\components\Domain\Clinical\Enum\RequestStatus;
use common\models\Clinical\Encounter;
use common\models\Clinical\ServiceRequest;
use common\models\ConsultaDerivaciones;

/**
 * Derivaciones clínicas (antes `consultas_derivaciones`) como ServiceRequest referral.
 */
final class ReferralRequestService
{
    public static function findPendingForPersonEfectorService(
        int $personaId,
        int $efectorId,
        int $serviceId
    ): array {
        return ConsultaDerivaciones::find()
            ->alias('sr')
            ->innerJoin(['enc' => Encounter::tableName()], 'enc.id = sr.encounter_id')
            ->where([
                'sr.category' => ConsultaDerivaciones::CATEGORY,
                'sr.referral_status' => ConsultaDerivaciones::ESTADO_EN_ESPERA,
                'sr.target_efector_id' => $efectorId,
                'sr.target_service_id' => $serviceId,
                'enc.subject_persona_id' => $personaId,
            ])
            ->andWhere(['sr.deleted_at' => null])
            ->all();
    }

    /**
     * @param int[] $serviceIds
     * @return ConsultaDerivaciones[]
     */
    public static function findPendingForPersonEfectorServices(
        int $personaId,
        array $serviceIds,
        int $efectorId
    ): array {
        if ($serviceIds === []) {
            return [];
        }

        return ConsultaDerivaciones::find()
            ->alias('sr')
            ->innerJoin(['enc' => Encounter::tableName()], 'enc.id = sr.encounter_id')
            ->where([
                'sr.category' => ConsultaDerivaciones::CATEGORY,
                'sr.referral_status' => ConsultaDerivaciones::ESTADO_EN_ESPERA,
                'sr.target_efector_id' => $efectorId,
            ])
            ->andWhere(['in', 'sr.target_service_id', $serviceIds])
            ->andWhere(['enc.subject_persona_id' => $personaId])
            ->andWhere(['sr.deleted_at' => null])
            ->all();
    }

    public static function markBooked(ConsultaDerivaciones $referral, ?int $respondedEncounterId = null): bool
    {
        $referral->referral_status = ConsultaDerivaciones::ESTADO_CON_TURNO;
        if ($respondedEncounterId !== null && $respondedEncounterId > 0) {
            $referral->responded_encounter_id = $respondedEncounterId;
        }

        return $referral->save(false);
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function createFromExtractedRow(Encounter $encounter, array $row): ServiceRequest
    {
        $sr = new ConsultaDerivaciones();
        $sr->encounter_id = (int) $encounter->id;
        $sr->subject_persona_id = (int) $encounter->subject_persona_id;
        $sr->category = ConsultaDerivaciones::CATEGORY;
        $sr->status = RequestStatus::ACTIVE;
        $sr->intent = 'order';
        $sr->referral_status = ConsultaDerivaciones::ESTADO_EN_ESPERA;
        $sr->code = isset($row['codigo']) ? (string) $row['codigo'] : null;
        $sr->display = $row['termino'] ?? $row['texto'] ?? $row['indicaciones'] ?? null;
        $sr->note = $row['indicaciones'] ?? null;
        $sr->target_efector_id = isset($row['id_efector']) ? (int) $row['id_efector'] : null;
        $sr->target_service_id = isset($row['id_servicio']) ? (int) $row['id_servicio'] : null;
        $sr->referral_kind = $row['tipo'] ?? null;
        $sr->request_kind = $row['tipo_solicitud'] ?? null;
        $sr->id_profesional_efector_servicio = $encounter->id_profesional_efector_servicio;

        if (!$sr->save()) {
            throw new \RuntimeException('Referral ServiceRequest: ' . json_encode($sr->getErrors()));
        }

        return $sr;
    }
}
