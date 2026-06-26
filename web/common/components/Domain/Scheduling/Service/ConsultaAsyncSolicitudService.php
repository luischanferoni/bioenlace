<?php

namespace common\components\Domain\Scheduling\Service;

use common\components\Domain\Clinical\Enum\EncounterStatus;
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

        $mensaje = trim((string) ($input['mensaje'] ?? $input['async_mensaje'] ?? ''));
        if (mb_strlen($mensaje) < 10) {
            throw new \InvalidArgumentException('Contanos tu consulta con al menos 10 caracteres.');
        }

        $draft = $this->draftDesdeInput($input);
        $draft['tipo_atencion'] = ReservaModalidadAtencionCatalogService::CODE_ASYNC;

        $triageCatalog = new ReservaTurnoTriageCatalogService();
        $triageCatalog->assertCanPersistBooking($draft);
        $compiled = $triageCatalog->compileSelections($draft);

        $modalidadService = new ReservaModalidadAtencionService();
        $opciones = $modalidadService->opcionesParaDraft($draft);
        $codes = array_column($opciones, 'code');
        if (!in_array(ReservaModalidadAtencionCatalogService::CODE_ASYNC, $codes, true)) {
            throw new \InvalidArgumentException(
                'La consulta por mensaje no está disponible para este caso. Elegí otro tipo de atención.'
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
            'note' => json_encode([
                'tipo' => 'consulta_async_solicitud',
                'reserva_triage_code' => $compiled['reserva_triage_code'],
                'urgency_band' => $compiled['urgency_band'],
                'reserva_triage_meta_json' => $compiled['reserva_triage_meta_json'],
                'care_plan_id' => (int) ($draft['care_plan_id'] ?? 0) ?: null,
            ], JSON_UNESCAPED_UNICODE),
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
            'data' => [
                'encounter_id' => (int) $encounter->id,
            ],
            'message' => 'Recibimos tu consulta. El equipo de salud te responderá por mensaje cuando pueda.',
        ];
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
        ] as $key) {
            $v = trim((string) ($input[$key] ?? ''));
            if ($v !== '') {
                $draft[$key] = $v;
            }
        }

        return $draft;
    }

    /**
     * @param array<string, mixed> $draft
     */
    private function resolverIdServicio(array $draft): int
    {
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
