<?php

namespace common\components\Domain\Scheduling\Service;

use common\components\Domain\Clinical\Enum\EncounterStatus;
use common\components\Domain\Clinical\Service\CarePlanMedicationListService;
use common\components\Domain\Clinical\Service\EncounterLifecycleService;
use common\models\Clinical\Encounter;
use Yii;

/**
 * Alta de solicitud de consulta async (encounter VR planificado, sin turno).
 */
final class ConsultaAsyncSolicitudService
{
    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function solicitarComoPaciente(int $idPersona, array $input): array
    {
        if ($idPersona <= 0) {
            throw new \InvalidArgumentException('Sesión sin persona.');
        }

        $draft = $this->draftDesdeInput($input);
        $draft['tipo_atencion'] = ReservaModalidadAtencionCatalogService::CODE_ASYNC;

        $intakeSvc = new ConsultasSeguimientoIntakeService();
        if (ConsultasSeguimientoIntakeService::esIntakeConsultasSeguimiento($draft)) {
            $intakeSvc->prepararDraft($draft, $idPersona);
            $intakeSvc->assertPuedeSolicitarAsync($draft, $idPersona);
            $this->assertNoRenovacionAbiertaParaPlan($idPersona, $draft);
            $meta = $intakeSvc->compilarMetaAsync($draft);
        } else {
            $triageCatalog = new ReservaTurnoTriageCatalogService();
            $triageCatalog->assertCanPersistBooking($draft);
            $compiled = $triageCatalog->compileSelections($draft);
            $meta = [
                'tipo' => 'consulta_async_solicitud',
                'reserva_triage_code' => $compiled['reserva_triage_code'],
                'urgency_band' => $compiled['urgency_band'],
                'reserva_triage_meta_json' => $compiled['reserva_triage_meta_json'],
                'care_plan_id' => (int) ($draft['care_plan_id'] ?? 0) ?: null,
            ];
        }

        $mensaje = $this->resolverMensajeSolicitud($input, $draft);

        $modalidadService = new ReservaModalidadAtencionService();
        $opciones = $modalidadService->opcionesParaDraft($draft);
        $codes = array_column($opciones, 'code');
        if (!in_array(ReservaModalidadAtencionCatalogService::CODE_ASYNC, $codes, true)) {
            throw new \InvalidArgumentException(
                'La consulta clínica por mensaje no está disponible para este caso. Elegí otro tipo de atención.'
            );
        }

        $serviceId = $this->resolverIdServicio($draft);
        if ($serviceId <= 0) {
            throw new \InvalidArgumentException(
                'No pudimos asignar un servicio para tu solicitud. Intentá con un turno presencial.'
            );
        }

        $encounters = new EncounterLifecycleService();
        $encounter = $encounters->start([
            'subject_persona_id' => $idPersona,
            'encounter_class' => Encounter::ENCOUNTER_CLASS_VR,
            'service_id' => $serviceId,
            'parent_type' => Encounter::PARENT_SOLICITUD_ASYNC,
            'parent_id' => null,
            'reason_text' => $mensaje,
            'note' => json_encode($meta, JSON_UNESCAPED_UNICODE),
        ]);
        $encounter->status = EncounterStatus::PLANNED;
        $encounter->save(false, ['status', 'updated_at', 'updated_by']);

        (new ConsultaAsyncInitialChatService())->seedMensajePaciente($encounter, $idPersona, $mensaje);

        try {
            (new ConsultaAsyncBandejaPrioridadAgent())->onNuevaSolicitud($encounter);
        } catch (\Throwable $e) {
            Yii::warning('Prioridad async nueva solicitud: ' . $e->getMessage(), 'consulta-async-prioridad');
        }

        return [
            'success' => true,
            'data' => $this->buildSuccessData($encounter, $meta),
            'message' => $this->mensajeExitoParaMeta($meta),
        ];
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private function buildSuccessData(Encounter $encounter, array $meta): array
    {
        $mensaje = $this->mensajeExitoParaMeta($meta);
        $data = [
            'encounter_id' => (int) $encounter->id,
            'mensaje' => $mensaje,
            'message' => $mensaje,
        ];
        $op = trim((string) ($meta['medicacion_operacion'] ?? ''));
        if ($op !== '') {
            $data['medicacion_operacion'] = $op;
        }
        $labels = $meta['medication_labels'] ?? null;
        if (is_array($labels) && $labels !== []) {
            $data['medication_labels'] = array_values(array_filter(array_map(
                static fn ($l): string => trim((string) $l),
                $labels
            )));
        }
        $carePlanId = (int) ($meta['care_plan_id'] ?? 0);
        if ($carePlanId > 0) {
            $data['care_plan_id'] = $carePlanId;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function mensajeExitoParaMeta(array $meta): string
    {
        $catalog = new ConsultaAsyncBandejaCatalogService();
        $operacion = trim((string) ($meta['medicacion_operacion'] ?? ''));
        if ($operacion === ConsultasSeguimientoIntakeService::MEDICACION_OP_RENOVACION) {
            $lines = [];
            $intro = $catalog->mensajeExitoRenovacion();
            if ($intro !== '') {
                $lines[] = $intro;
            }
            $labels = $meta['medication_labels'] ?? null;
            if (is_array($labels)) {
                foreach ($labels as $label) {
                    $s = trim((string) $label);
                    if ($s !== '') {
                        $lines[] = '• ' . $s;
                    }
                }
            }
            $cierre = $catalog->mensajeExitoRenovacionCierre();
            if ($cierre !== '') {
                $lines[] = $cierre;
            }
            if ($lines !== []) {
                return implode("\n", $lines);
            }
        }

        $generico = $catalog->mensajeExitoGenerico();
        if ($generico !== '') {
            return $generico;
        }

        return 'Recibimos tu consulta clínica por mensaje. El equipo de salud te responderá cuando pueda.';
    }

    /**
     * @param array<string, mixed> $draft
     */
    private function assertNoRenovacionAbiertaParaPlan(int $idPersona, array $draft): void
    {
        $operacion = trim((string) ($draft[ConsultasSeguimientoIntakeService::DRAFT_MEDICACION_OPERACION] ?? ''));
        if ($operacion !== ConsultasSeguimientoIntakeService::MEDICACION_OP_RENOVACION) {
            return;
        }
        $carePlanId = (int) ($draft['care_plan_id'] ?? 0);
        if ($carePlanId <= 0) {
            return;
        }

        $encounters = Encounter::find()
            ->where([
                'subject_persona_id' => $idPersona,
                'parent_type' => Encounter::PARENT_SOLICITUD_ASYNC,
                'encounter_class' => Encounter::ENCOUNTER_CLASS_VR,
            ])
            ->andWhere(['status' => [
                EncounterStatus::PLANNED,
                EncounterStatus::IN_PROGRESS,
                EncounterStatus::ON_HOLD,
            ]])
            ->andWhere(['deleted_at' => null])
            ->all();

        foreach ($encounters as $encounter) {
            $meta = $this->parseEncounterNote($encounter->note);
            if ((int) ($meta['care_plan_id'] ?? 0) !== $carePlanId) {
                continue;
            }
            if (trim((string) ($meta['medicacion_operacion'] ?? ''))
                !== ConsultasSeguimientoIntakeService::MEDICACION_OP_RENOVACION) {
                continue;
            }
            throw new \InvalidArgumentException((new ConsultaAsyncBandejaCatalogService())->mensajeRenovacionDuplicada());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function parseEncounterNote(?string $note): array
    {
        if ($note === null || trim($note) === '') {
            return [];
        }
        $decoded = json_decode($note, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $draft
     */
    private function resolverMensajeSolicitud(array $input, array $draft): string
    {
        $mensajeLibre = trim((string) ($input['mensaje'] ?? $input['async_mensaje'] ?? $draft['mensaje'] ?? ''));
        $operacion = trim((string) ($draft[ConsultasSeguimientoIntakeService::DRAFT_MEDICACION_OPERACION] ?? ''));
        $ids = CarePlanMedicationListService::parseIds(
            $draft[ConsultasSeguimientoIntakeService::DRAFT_MEDICATION_REQUEST_IDS]
                ?? $input['medication_request_ids']
                ?? ''
        );

        if ($operacion === ConsultasSeguimientoIntakeService::MEDICACION_OP_RENOVACION) {
            if ($ids === []) {
                throw new \InvalidArgumentException('Seleccioná al menos un medicamento para renovar.');
            }
            $labels = (new CarePlanMedicationListService())->labelsForIds($ids);
            $lines = $labels !== [] ? $labels : array_map(static fn (int $id): string => 'Medicación #' . $id, $ids);

            return "Solicitud de renovación de medicación:\n- " . implode("\n- ", $lines);
        }

        if ($operacion === ConsultasSeguimientoIntakeService::MEDICACION_OP_AJUSTE) {
            if ($ids === []) {
                throw new \InvalidArgumentException('Seleccioná al menos un medicamento para ajustar.');
            }
            $motivo = trim((string) (
                $input[ConsultasSeguimientoIntakeService::DRAFT_AJUSTE_MOTIVO]
                    ?? $draft[ConsultasSeguimientoIntakeService::DRAFT_AJUSTE_MOTIVO]
                    ?? $mensajeLibre
            ));
            if (mb_strlen($motivo) < 10) {
                throw new \InvalidArgumentException('Indicá el cambio solicitado con al menos 10 caracteres.');
            }
            $labels = (new CarePlanMedicationListService())->labelsForIds($ids);
            $lines = $labels !== [] ? $labels : array_map(static fn (int $id): string => 'Medicación #' . $id, $ids);

            return "Solicitud de ajuste de medicación:\n- " . implode("\n- ", $lines)
                . "\n\nMotivo: " . $motivo;
        }

        if (mb_strlen($mensajeLibre) < 10) {
            throw new \InvalidArgumentException('Contanos tu consulta con al menos 10 caracteres.');
        }

        return $mensajeLibre;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function draftDesdeInput(array $input): array
    {
        $draft = [];
        foreach ([
            'triage_raiz',
            'triage_alarmas',
            'triage_zona',
            'triage_detalle',
            'triage_evolucion',
            'triage_nota',
            'care_plan_id',
            'encounter_id',
            'intake_tipo',
            'seguimiento_necesidad',
            'medicacion_operacion',
            'ajuste_motivo',
            'mensaje',
        ] as $key) {
            $v = trim((string) ($input[$key] ?? ''));
            if ($v !== '') {
                $draft[$key] = $v;
            }
        }

        $mrRaw = $input['medication_request_ids'] ?? null;
        if ($mrRaw !== null && $mrRaw !== '') {
            $ids = CarePlanMedicationListService::parseIds($mrRaw);
            if ($ids !== []) {
                $draft[ConsultasSeguimientoIntakeService::DRAFT_MEDICATION_REQUEST_IDS] = implode(',', $ids);
            }
        }

        return $draft;
    }

    /**
     * @param array<string, mixed> $draft
     */
    private function resolverIdServicio(array $draft): int
    {
        $assigned = (int) ($draft['id_servicio_asignado'] ?? 0);
        if ($assigned > 0) {
            return $assigned;
        }

        $carePlanSvc = new ReservaTriageCarePlanServicioService();
        $carePlanId = (int) ($draft['care_plan_id'] ?? 0);
        if ($carePlanId > 0) {
            $idPersona = (int) (Yii::$app->user->getIdPersona() ?? 0);
            $plan = $carePlanSvc->findPlanForPersona($carePlanId, $idPersona);
            if ($plan !== null) {
                $ids = $carePlanSvc->idsServicioReservaDesdePlan($plan);
                if ($ids !== []) {
                    return (int) $ids[0];
                }
            }
        }

        $servicioSugerido = new ReservaTriageServicioSugeridoService();
        $res = $servicioSugerido->resolverParaDraft($draft, false);
        if ($res['id_servicios'] !== []) {
            return (int) $res['id_servicios'][0];
        }

        $hub = $servicioSugerido->resolverParaDraft($draft, true);
        if ($hub['id_servicios'] !== []) {
            return (int) $hub['id_servicios'][0];
        }

        return 0;
    }
}
