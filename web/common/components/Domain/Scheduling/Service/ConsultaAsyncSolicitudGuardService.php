<?php

namespace common\components\Domain\Scheduling\Service;

use common\components\Domain\Clinical\Enum\EncounterStatus;
use common\models\Clinical\Encounter;

/**
 * Anti-duplicados, rate limit y reglas renovación vs ajuste al crear solicitud async.
 */
final class ConsultaAsyncSolicitudGuardService
{
    /** @var list<string> */
    private const OPEN_STATUSES = [
        EncounterStatus::PLANNED,
        EncounterStatus::IN_PROGRESS,
        EncounterStatus::ON_HOLD,
    ];

    /**
     * @param array<string, mixed> $draft
     */
    public function assertPuedeCrear(int $idPersona, array $draft): void
    {
        if (!ConsultasSeguimientoIntakeService::esIntakeConsultasSeguimiento($draft)) {
            return;
        }

        $carePlanId = (int) ($draft['care_plan_id'] ?? 0);
        $operacion = trim((string) ($draft[ConsultasSeguimientoIntakeService::DRAFT_MEDICACION_OPERACION] ?? ''));
        $necesidad = trim((string) ($draft[ConsultasSeguimientoIntakeService::DRAFT_SEGUIMIENTO_NECESIDAD] ?? ''));

        if ($carePlanId > 0 && $this->esOperacionMedicacion($operacion, $necesidad)) {
            $this->assertNoMedicacionAbierta($idPersona, $carePlanId, $operacion);
            $this->assertRateLimitMedicacion($idPersona, $carePlanId);
        }
    }

    /**
     * ¿Hay solicitud async de medicación abierta para el plan?
     */
    public function tieneMedicacionAbiertaParaPlan(int $idPersona, int $carePlanId): bool
    {
        return $this->findOpenMedicacionForPlan($idPersona, $carePlanId) !== null;
    }

    private function assertNoMedicacionAbierta(int $idPersona, int $carePlanId, string $operacionSolicitada): void
    {
        $catalog = new ConsultaAsyncChatPolicyCatalogService();
        $dup = $catalog->duplicateConfig();
        if (($dup['block_open_medicacion_por_plan'] ?? true) !== true) {
            return;
        }

        $open = $this->findOpenMedicacionForPlan($idPersona, $carePlanId);
        if ($open === null) {
            return;
        }

        $metaSvc = new ConsultaAsyncEncounterMetaService();
        $meta = $metaSvc->fromEncounter($open);
        $opAbierta = $metaSvc->medicacionOperacion($meta);

        if ($operacionSolicitada === ConsultasSeguimientoIntakeService::MEDICACION_OP_RENOVACION
            && ($dup['block_renovacion_si_ajuste_abierto'] ?? true) === true
            && $opAbierta === ConsultasSeguimientoIntakeService::MEDICACION_OP_AJUSTE) {
            $msg = $catalog->duplicateMessage('renovacion_con_ajuste_pendiente');
            throw new \InvalidArgumentException(
                $msg !== '' ? $msg : 'Tenés un ajuste pendiente; esperá su resolución antes de renovar.'
            );
        }

        if ($opAbierta === ConsultasSeguimientoIntakeService::MEDICACION_OP_RENOVACION) {
            $msg = $catalog->duplicateMessage('open_renovacion');
        } elseif ($opAbierta === ConsultasSeguimientoIntakeService::MEDICACION_OP_AJUSTE) {
            $msg = $catalog->duplicateMessage('open_ajuste');
        } else {
            $msg = $catalog->duplicateMessage('open_medicacion');
        }
        if ($msg === '') {
            $msg = 'Ya tenés una consulta sobre medicación pendiente para este tratamiento.';
        }
        throw new \InvalidArgumentException($msg);
    }

    private function assertRateLimitMedicacion(int $idPersona, int $carePlanId): void
    {
        $catalog = new ConsultaAsyncChatPolicyCatalogService();
        $cfg = $catalog->limitsRateLimit();
        $windowDays = max(1, (int) ($cfg['window_days'] ?? 30));
        $max = max(1, (int) ($cfg['max_solicitudes_medicacion_por_plan'] ?? 2));
        $since = date('Y-m-d H:i:s', time() - ($windowDays * 86400));

        $encounters = Encounter::find()
            ->where([
                'subject_persona_id' => $idPersona,
                'parent_type' => Encounter::PARENT_SOLICITUD_ASYNC,
                'encounter_class' => Encounter::ENCOUNTER_CLASS_VR,
            ])
            ->andWhere(['>=', 'created_at', $since])
            ->andWhere(['deleted_at' => null])
            ->all();

        $metaSvc = new ConsultaAsyncEncounterMetaService();
        $count = 0;
        foreach ($encounters as $encounter) {
            $meta = $metaSvc->fromEncounter($encounter);
            if ($metaSvc->carePlanId($meta) !== $carePlanId) {
                continue;
            }
            if ($metaSvc->medicacionOperacion($meta) === '') {
                continue;
            }
            $count++;
        }
        if ($count >= $max) {
            $msg = $catalog->duplicateMessage('rate_limit_medicacion');
            throw new \InvalidArgumentException(
                $msg !== '' ? $msg : 'Superaste el límite de solicitudes de medicación para este tratamiento.'
            );
        }
    }

    private function findOpenMedicacionForPlan(int $idPersona, int $carePlanId): ?Encounter
    {
        $encounters = Encounter::find()
            ->where([
                'subject_persona_id' => $idPersona,
                'parent_type' => Encounter::PARENT_SOLICITUD_ASYNC,
                'encounter_class' => Encounter::ENCOUNTER_CLASS_VR,
            ])
            ->andWhere(['status' => self::OPEN_STATUSES])
            ->andWhere(['deleted_at' => null])
            ->orderBy(['created_at' => SORT_DESC])
            ->all();

        $metaSvc = new ConsultaAsyncEncounterMetaService();
        foreach ($encounters as $encounter) {
            $meta = $metaSvc->fromEncounter($encounter);
            if ($metaSvc->carePlanId($meta) !== $carePlanId) {
                continue;
            }
            if ($metaSvc->medicacionOperacion($meta) !== '') {
                return $encounter;
            }
        }

        return null;
    }

    private function esOperacionMedicacion(string $operacion, string $necesidad): bool
    {
        if (in_array($operacion, [
            ConsultasSeguimientoIntakeService::MEDICACION_OP_RENOVACION,
            ConsultasSeguimientoIntakeService::MEDICACION_OP_AJUSTE,
        ], true)) {
            return true;
        }

        return in_array($necesidad, ['renovar_medicacion', 'solicitar_ajuste'], true);
    }
}
