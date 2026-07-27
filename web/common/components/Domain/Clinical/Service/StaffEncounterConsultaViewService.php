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
 * Payload mínimo: sin HC/estado del paciente, sin flags de captura ni ventanas HC.
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
        $data = [
            'persona' => [
                'id' => (int) $persona->id_persona,
                'nombre_completo' => $persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N),
                'documento' => $persona->documento,
            ],
            'turno' => $this->formatTurno($turno),
            'encounter_id' => (int) $encounter->id,
            'documentacion_medico' => $documentacion,
        ];

        $preconsulta = $this->buildPreconsultaSiAporta($encounter, $documentacion);
        if ($preconsulta !== null) {
            $data['motivos_consulta_paciente'] = $preconsulta;
        }

        $carePack = $this->buildCarePackSiAporta((int) $encounter->id);
        if ($carePack !== null) {
            $data['care_pack_cohorte'] = $carePack;
        }

        return [
            'ok' => true,
            'data' => $data,
        ];
    }

    /**
     * Preconsulta solo si aporta algo que no esté ya en documentación del médico.
     *
     * @param array{secciones?: list<array{titulo?: string, items?: list<string>}>} $documentacion
     * @return array<string, mixed>|null
     */
    private function buildPreconsultaSiAporta(Encounter $encounter, array $documentacion): ?array
    {
        $encounterId = (int) $encounter->id;
        $reason = trim((string) $encounter->reason_text);
        $mensajes = ConsultaMotivosMessage::find()
            ->where(['encounter_id' => $encounterId])
            ->orderBy(['created_at' => SORT_ASC])
            ->all();
        $imagenesAdjuntas = AppointmentReasonBatchService::imagenesAdjuntasFromMessages(
            $mensajes,
            $encounterId
        );
        $motivosIntake = (new EncounterMotivosIntakeStaffViewService())->buildForEncounter($encounter);
        $intakeUtil = is_array($motivosIntake) && $this->intakeTieneContenido($motivosIntake);
        $resumen = $reason !== '' ? $reason : null;
        $resumenYaEnDoc = $resumen !== null && $this->textoYaEnDocumentacion($resumen, $documentacion);

        if ($resumenYaEnDoc) {
            $resumen = null;
        }

        if ($resumen === null && $imagenesAdjuntas === [] && !$intakeUtil) {
            return null;
        }

        $out = [];
        if ($resumen !== null) {
            $out['resumen'] = $resumen;
        }
        if ($imagenesAdjuntas !== []) {
            $out['imagenes_adjuntas'] = $imagenesAdjuntas;
        }
        if ($intakeUtil) {
            $out['motivos_intake'] = $motivosIntake;
        }

        return $out;
    }

    /**
     * @param array{secciones?: list<array{titulo?: string, items?: list<string>}>} $documentacion
     */
    private function textoYaEnDocumentacion(string $texto, array $documentacion): bool
    {
        $norm = mb_strtolower(trim($texto));
        if ($norm === '') {
            return false;
        }
        foreach ($documentacion['secciones'] ?? [] as $sec) {
            foreach ($sec['items'] ?? [] as $item) {
                if (mb_strtolower(trim((string) $item)) === $norm) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $intake
     */
    private function intakeTieneContenido(array $intake): bool
    {
        foreach (['respuestas', 'answers', 'items', 'preguntas'] as $key) {
            if (!empty($intake[$key]) && is_array($intake[$key])) {
                return true;
            }
        }

        return !empty($intake['tiene_contenido']) || !empty($intake['tieneContenido']);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildCarePackSiAporta(int $encounterId): ?array
    {
        if (!CarePackConfig::isEnabled()) {
            return null;
        }
        $carePack = (new CarePackEncounterStaffService())->buildForEncounter($encounterId);
        if ($carePack === null) {
            return null;
        }
        $assistance = is_array($carePack['assistance'] ?? null) ? $carePack['assistance'] : [];
        $answers = $assistance['answers'] ?? [];
        $notes = trim((string) ($assistance['notes_for_staff'] ?? ''));
        if ((!is_array($answers) || $answers === []) && $notes === '') {
            return null;
        }

        return [
            'encounter_id' => (int) ($carePack['encounter_id'] ?? $encounterId),
            'assistance' => [
                'status' => (string) ($assistance['status'] ?? ''),
                'notes_for_staff' => $notes !== '' ? $notes : null,
                'submitted_at' => $assistance['submitted_at'] ?? null,
                'delta_requested' => !empty($assistance['delta_requested']),
                'answers' => is_array($answers) ? $answers : [],
            ],
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
}
