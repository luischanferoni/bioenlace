<?php

namespace common\components\Domain\Clinical\Service;

use common\components\Domain\Clinical\CareCohort\Service\CarePackConfig;
use common\components\Domain\Clinical\CareCohort\Service\CarePackEncounterStaffService;
use common\components\Domain\Clinical\Presentation\EncounterStaffDocumentationViewService;
use common\components\Domain\Clinical\Service\EncounterJourney\EncounterMotivosIntakeStaffViewService;
use common\models\Clinical\Encounter;
use common\models\ConsultaMotivosMessage;
use common\models\Person\Persona;
use common\models\Scheduling\Turno;

/**
 * Vista de solo lectura de una consulta ya documentada (staff).
 * No es historia clínica del paciente: es el encounter + documentación del médico.
 */
final class StaffEncounterConsultaViewService
{
    /**
     * @return array{
     *   ok: bool,
     *   http_status?: int,
     *   message?: string,
     *   data?: array<string, mixed>
     * }
     */
    public function build(?int $turnoId, ?int $encounterId): array
    {
        $turnoId = $turnoId !== null && $turnoId > 0 ? $turnoId : 0;
        $encounterId = $encounterId !== null && $encounterId > 0 ? $encounterId : 0;
        if ($turnoId <= 0 && $encounterId <= 0) {
            return [
                'ok' => false,
                'http_status' => 400,
                'message' => 'Indicá turno_id o encounter_id.',
            ];
        }

        $lookup = new EncounterAppointmentReasonLookupService();
        $turno = null;
        $encounter = null;

        if ($turnoId > 0) {
            $turno = Turno::findActive()->andWhere(['id_turnos' => $turnoId])->one();
            if ($turno === null) {
                return [
                    'ok' => false,
                    'http_status' => 404,
                    'message' => 'Turno no encontrado.',
                ];
            }
            $resolvedId = $lookup->encounterIdParaTurno($turnoId);
            if ($resolvedId === null) {
                return [
                    'ok' => false,
                    'http_status' => 404,
                    'message' => 'No hay encounter documentado para este turno.',
                ];
            }
            $encounter = Encounter::findOne($resolvedId);
        } else {
            $encounter = Encounter::findOne($encounterId);
            if ($encounter === null) {
                return [
                    'ok' => false,
                    'http_status' => 404,
                    'message' => 'Encounter no encontrado.',
                ];
            }
            if ($encounter->appointment_id) {
                $turno = Turno::findActive()
                    ->andWhere(['id_turnos' => (int) $encounter->appointment_id])
                    ->one();
            }
            if (
                $turno === null
                && $encounter->parent_type === Encounter::PARENT_TURNO
                && $encounter->parent_id
            ) {
                $turno = Turno::findActive()
                    ->andWhere(['id_turnos' => (int) $encounter->parent_id])
                    ->one();
            }
        }

        if ($encounter === null) {
            return [
                'ok' => false,
                'http_status' => 404,
                'message' => 'Encounter no encontrado.',
            ];
        }

        $persona = Persona::findOne((int) $encounter->subject_persona_id);
        if ($persona === null) {
            return [
                'ok' => false,
                'http_status' => 404,
                'message' => 'Persona del encounter no encontrada.',
            ];
        }

        AppointmentReasonBatchService::ensureProcessedForMedico($encounter);
        $encounter->refresh();

        $documentacion = (new EncounterStaffDocumentationViewService())->buildForEncounter($encounter);
        $motivosPaciente = $this->buildMotivosPaciente($encounter, $turno);
        $carePack = null;
        if (CarePackConfig::isEnabled()) {
            $carePack = (new CarePackEncounterStaffService())->buildForEncounter((int) $encounter->id);
        }

        return [
            'ok' => true,
            'data' => [
                'persona' => [
                    'id' => (int) $persona->id_persona,
                    'nombre_completo' => $persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N),
                    'documento' => $persona->documento,
                    'edad' => $persona->edad,
                    'fecha_nacimiento' => $persona->fecha_nacimiento,
                ],
                'turno' => $this->formatTurno($turno),
                'encounter_id' => (int) $encounter->id,
                'documentacion_medico' => $documentacion,
                'motivos_consulta_paciente' => $motivosPaciente,
                'care_pack_cohorte' => $carePack,
                'care_cohort_habilitado' => CarePackConfig::isEnabled(),
                'captura' => $this->capturaFlagsForTurno($turno),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMotivosPaciente(Encounter $encounter, ?Turno $turno): array
    {
        $encounterId = (int) $encounter->id;
        $reason = trim((string) $encounter->reason_text);
        $mensajes = ConsultaMotivosMessage::find()
            ->where(['encounter_id' => $encounterId])
            ->orderBy(['created_at' => SORT_ASC])
            ->all();
        $insights = AppointmentReasonClinicalInsightsService::decodeInsights(
            $encounter->motivos_ia_insights_json ?? null
        );
        $imagenesAdjuntas = AppointmentReasonBatchService::imagenesAdjuntasFromMessages(
            $mensajes,
            $encounterId
        );
        $resumen = $reason !== '' ? $reason : null;
        $motivosIntake = (new EncounterMotivosIntakeStaffViewService())->buildForEncounter($encounter);

        return [
            'encounter_id' => $encounterId,
            'consulta_id' => $encounterId,
            'turno_id' => $turno !== null ? (int) $turno->id_turnos : null,
            'turno' => $this->formatTurno($turno),
            'contexto_explicito' => true,
            'ventana_medico' => AppointmentReasonWindowService::apiHistoriaClinicaGateState($encounter),
            'resumen' => $resumen,
            'resumen_ia' => $resumen,
            'resumen_pendiente' => $mensajes !== [] && $resumen === null,
            'resumen_ia_pendiente' => $mensajes !== [] && $resumen === null,
            'imagenes_adjuntas' => $imagenesAdjuntas,
            'sugerencias_clinicas' => $insights,
            'motivos_intake' => $motivosIntake,
            'messages' => [],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function formatTurno(?Turno $turno): ?array
    {
        if ($turno === null) {
            return null;
        }
        $hora = trim((string) ($turno->hora ?? ''));
        if (preg_match('/^(\d{1,2}):(\d{2})/', $hora, $m) === 1) {
            $hora = sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        } elseif (strlen($hora) >= 5) {
            $hora = substr($hora, 0, 5);
        }

        return [
            'id' => (int) $turno->id_turnos,
            'fecha' => (string) $turno->fecha,
            'hora' => $hora,
            'estado' => (string) $turno->estado,
            'estado_label' => Turno::ESTADOS[$turno->estado] ?? 'Sin estado',
        ];
    }

    /**
     * @return array{permitida: bool|null, motivo: string|null}
     */
    private function capturaFlagsForTurno(?Turno $turno): array
    {
        if ($turno === null) {
            return ['permitida' => null, 'motivo' => null];
        }
        $estado = (string) $turno->estado;
        if ($estado === Turno::ESTADO_PENDIENTE || $estado === Turno::ESTADO_EN_ATENCION) {
            return ['permitida' => true, 'motivo' => null];
        }
        if ($estado === Turno::ESTADO_ATENDIDO) {
            return [
                'permitida' => false,
                'motivo' => 'Este turno ya fue atendido. Consulta en solo lectura.',
            ];
        }
        if ($estado === Turno::ESTADO_CANCELADO) {
            return [
                'permitida' => false,
                'motivo' => 'Este turno está cancelado.',
            ];
        }
        if ($estado === Turno::ESTADO_SIN_ATENDER) {
            return [
                'permitida' => false,
                'motivo' => 'Este turno quedó sin atender.',
            ];
        }
        if ($estado === Turno::ESTADO_EN_RESOLUCION) {
            return [
                'permitida' => false,
                'motivo' => 'Este turno está en resolución de horario.',
            ];
        }

        return [
            'permitida' => false,
            'motivo' => 'Este turno no admite captura (estado: ' . $estado . ').',
        ];
    }
}
