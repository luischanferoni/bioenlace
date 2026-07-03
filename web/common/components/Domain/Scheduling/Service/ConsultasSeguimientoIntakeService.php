<?php

namespace common\components\Domain\Scheduling\Service;

use common\models\Clinical\CarePlan;
use common\models\Clinical\Encounter;
use Yii;

/**
 * Reglas de intake consultas / seguimiento (sin turno async y preparación de draft).
 */
final class ConsultasSeguimientoIntakeService
{
    public const DRAFT_INTAKE_TIPO = 'intake_tipo';

    public const DRAFT_SEGUIMIENTO_NECESIDAD = 'seguimiento_necesidad';

    public const DRAFT_PREFERENCIA_TURNO = 'preferencia_profesional_turno';

    /**
     * @param array<string, mixed> $draft
     */
    public static function esIntakeConsultasSeguimiento(array $draft): bool
    {
        $tipo = trim((string) ($draft[self::DRAFT_INTAKE_TIPO] ?? ''));

        return in_array($tipo, [
            ConsultasSeguimientoIntakeCatalogService::INTAKE_CONSULTA_GENERAL,
            ConsultasSeguimientoIntakeCatalogService::INTAKE_SEGUIMIENTO,
        ], true);
    }

    /**
     * @param array<string, mixed> $draft mutado in-place
     */
    public function prepararDraft(array &$draft, int $idPersona): void
    {
        $tipo = trim((string) ($draft[self::DRAFT_INTAKE_TIPO] ?? ''));
        if ($tipo === ConsultasSeguimientoIntakeCatalogService::INTAKE_SEGUIMIENTO) {
            $draft['triage_raiz'] = 'seguimiento_cronico';
            $this->aplicarCarePlanEnDraft($draft, $idPersona);
        } elseif ($tipo === ConsultasSeguimientoIntakeCatalogService::INTAKE_CONSULTA_GENERAL) {
            $draft['triage_raiz'] = 'seguimiento_cronico';
        }

        $necesidad = trim((string) ($draft[self::DRAFT_SEGUIMIENTO_NECESIDAD] ?? ''));
        $pref = trim((string) ($draft[self::DRAFT_PREFERENCIA_TURNO] ?? ''));
        if ($pref !== '') {
            foreach ((new ConsultasSeguimientoIntakeCatalogService())->opcionesPreferenciaTurno() as $row) {
                if (($row['code'] ?? '') === $pref && ($row['tipo_atencion'] ?? '') !== '') {
                    $draft['tipo_atencion'] = (string) $row['tipo_atencion'];
                    break;
                }
            }
        }
        if ($necesidad === 'solicitar_turno' && $pref === 'teleconsulta') {
            $draft['tipo_atencion'] = 'teleconsulta';
        }
    }

    /**
     * @param array<string, mixed> $draft
     */
    public function assertPuedeSolicitarAsync(array $draft, int $idPersona): void
    {
        if (!self::esIntakeConsultasSeguimiento($draft)) {
            return;
        }

        $tipo = trim((string) ($draft[self::DRAFT_INTAKE_TIPO] ?? ''));
        if ($tipo === ConsultasSeguimientoIntakeCatalogService::INTAKE_SEGUIMIENTO) {
            $carePlanId = (int) ($draft['care_plan_id'] ?? 0);
            if ($carePlanId <= 0) {
                throw new \InvalidArgumentException('Elegí tu plan de tratamiento para continuar.');
            }
            $plan = (new ReservaTriageCarePlanServicioService())->findPlanForPersona($carePlanId, $idPersona);
            if ($plan === null) {
                throw new \InvalidArgumentException('El plan de tratamiento no está disponible.');
            }
            $necesidad = trim((string) ($draft[self::DRAFT_SEGUIMIENTO_NECESIDAD] ?? ''));
            if ($necesidad !== '') {
                $def = (new ConsultasSeguimientoIntakeCatalogService())->necesidad($necesidad);
                if ($def === null) {
                    throw new \InvalidArgumentException('Tipo de seguimiento no válido.');
                }
                if (!$def['permite_async']) {
                    throw new \InvalidArgumentException('Para pedir turno usá la opción Solicitar turno.');
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $draft
     * @return array<string, mixed>
     */
    public function compilarMetaAsync(array $draft): array
    {
        $catalog = new ReservaTurnoTriageCatalogService();
        $compiled = $catalog->compileSelections($draft);

        return [
            'tipo' => 'consulta_async_solicitud',
            'intake_tipo' => trim((string) ($draft[self::DRAFT_INTAKE_TIPO] ?? '')),
            'seguimiento_necesidad' => trim((string) ($draft[self::DRAFT_SEGUIMIENTO_NECESIDAD] ?? '')) ?: null,
            'care_plan_id' => (int) ($draft['care_plan_id'] ?? 0) ?: null,
            'reserva_triage_code' => $compiled['reserva_triage_code'],
            'urgency_band' => $compiled['urgency_band'],
            'reserva_triage_meta_json' => $compiled['reserva_triage_meta_json'],
        ];
    }

    /**
     * @param array<string, mixed> $draft mutado in-place
     */
    private function aplicarCarePlanEnDraft(array &$draft, int $idPersona): void
    {
        $carePlanId = (int) ($draft['care_plan_id'] ?? 0);
        if ($carePlanId <= 0 || $idPersona <= 0) {
            return;
        }

        $carePlanSvc = new ReservaTriageCarePlanServicioService();
        $plan = $carePlanSvc->findPlanForPersona($carePlanId, $idPersona);
        if ($plan === null) {
            return;
        }

        $ids = $carePlanSvc->idsServicioReservaDesdePlan($plan);
        if ($ids !== [] && (int) ($draft['id_servicio_asignado'] ?? 0) <= 0) {
            $draft['id_servicio_asignado'] = (string) $ids[0];
        }

        $pref = trim((string) ($draft[self::DRAFT_PREFERENCIA_TURNO] ?? ''));
        if ($pref === 'mismo_medico') {
            $this->aplicarMismoMedicoDesdePlan($draft, $plan);
        }
    }

    /**
     * @param array<string, mixed> $draft mutado in-place
     */
    private function aplicarMismoMedicoDesdePlan(array &$draft, CarePlan $plan): void
    {
        $encounterId = (int) ($plan->encounter_id ?? 0);
        if ($encounterId <= 0) {
            return;
        }
        $encounter = Encounter::findOne(['id' => $encounterId, 'deleted_at' => null]);
        if ($encounter === null) {
            return;
        }
        $idPes = (int) ($encounter->id_profesional_efector_servicio ?? 0);
        $idEfector = (int) ($encounter->efector_id ?? 0);
        $serviceId = (int) ($encounter->service_id ?? 0);
        if ($idPes > 0) {
            $draft['id_profesional_efector_servicio'] = (string) $idPes;
        }
        if ($idEfector > 0) {
            $draft['id_efector'] = (string) $idEfector;
        }
        if ($serviceId > 0) {
            $draft['id_servicio_asignado'] = (string) $serviceId;
        }
        if (trim((string) ($draft['tipo_atencion'] ?? '')) === '') {
            $draft['tipo_atencion'] = 'presencial';
        }
    }
}
