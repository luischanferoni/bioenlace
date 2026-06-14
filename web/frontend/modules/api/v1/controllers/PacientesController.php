<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use common\components\Domain\Clinical\CareCohort\Service\CarePackConfig;
use common\components\Domain\Clinical\CareCohort\Service\CarePackEncounterStaffService;
use common\components\Domain\Clinical\Service\AppointmentReasonBatchService;
use common\components\Domain\Clinical\Service\AppointmentReasonClinicalInsightsService;
use common\components\Domain\Clinical\Service\AppointmentReasonWindowService;
use common\components\Domain\Clinical\Service\EncounterAppointmentReasonLookupService;
use common\components\Domain\Clinical\Home\StaffClinicalDayListService;
use common\models\Clinical\Encounter;
use common\models\Person\Persona;
use common\models\ProfesionalEfectorServicio;
use common\models\Scheduling\Turno;
use common\models\Clinical\AllergyIntolerance;
use common\models\PersonasAntecedente;
use common\models\DiagnosticoConsultaRepository as DCRepo;
use common\models\ConsultaMotivosMessage;
use common\components\Domain\Person\Service\PersonaSignosVitalesService;
use frontend\modules\api\v1\controllers\clinical\ClinicalAccessTrait;
/**
 * Historia clínica staff (listado del día: GET /api/v1/home/panel).
 */
class PacientesController extends BaseController
{
    use ClinicalAccessTrait;

    /**
     * Resumen de historia clínica (persona + información médica + signos vitales + mensajes de motivos de la app del paciente). No arma lista de eventos aquí.
     *
     * GET /api/v1/personas/{id}/historia-clinica
     * Query: `turno_id` o `encounter_id` — motivos del turno/consulta indicado (recomendado en app médico).
     * Sin query: motivos del turno con mensajes más reciente en el efector (no el turno futuro vacío).
     * Query (solo YII_DEBUG): simular_signos=1 — misma semántica que GET .../signos-vitales.
     * RBAC: /api/pacientes/historia-clinica
     */
    public function actionHistoriaClinica($id)
    {
        $persona = Persona::findOne((int) $id);
        if (!$persona) {
            return $this->error('Persona no encontrada', null, 404);
        }

        $idEfector = (int) Yii::$app->user->getIdEfector();
        $motivosLookup = new EncounterAppointmentReasonLookupService();
        $motivosCtx = $this->buildMotivosHistoriaClinicaContext(
            $persona,
            $motivosLookup,
            $idEfector
        );
        if ($motivosCtx['http_error'] !== null) {
            return $motivosCtx['http_error'];
        }

        $motivosConsulta = $motivosCtx['motivos_consulta'];
        $motivosConsultaPaciente = $motivosCtx['motivos_consulta_paciente'];

        $carePackCohorte = null;
        if (CarePackConfig::isEnabled()) {
            $encounterIdCare = (int) ($motivosConsultaPaciente['encounter_id'] ?? 0);
            if ($encounterIdCare > 0) {
                $carePackCohorte = (new CarePackEncounterStaffService())->buildForEncounter($encounterIdCare);
            }
        }

        // Diagnósticos recientes/crónicos
        [$condActivas, $condCronicas] = DCRepo::getCondicionesPaciente((int) $persona->id_persona);
        $condicionesActivas = [];
        foreach ($condActivas as $c) {
            $term = isset($c->codigoSnomed) ? (string) $c->codigoSnomed->term : null;
            $code = isset($c->codigoSnomed) ? (string) $c->codigoSnomed->conceptId : null;
            $condicionesActivas[] = ['codigo' => $code, 'termino' => $term];
        }
        $condicionesCronicas = [];
        foreach ($condCronicas as $c) {
            $term = isset($c->codigoSnomed) ? (string) $c->codigoSnomed->term : null;
            $code = isset($c->codigoSnomed) ? (string) $c->codigoSnomed->conceptId : null;
            $condicionesCronicas[] = ['codigo' => $code, 'termino' => $term];
        }

        // Alergias (FHIR allergy_intolerance)
        $hallazgos = [];
        foreach (AllergyIntolerance::findActiveBySubject((int) $persona->id_persona) as $ai) {
            $term = trim((string) ($ai->display ?? ''));
            if ($term === '' && !empty($ai->code)) {
                $term = (string) $ai->code;
            }
            $hallazgos[] = [
                'id' => (int) $ai->id,
                'codigo' => $ai->code !== null && $ai->code !== '' ? (string) $ai->code : null,
                'termino' => $term !== '' ? $term : null,
            ];
        }

        // Antecedentes
        $antecedentesPersonales = [];
        $antecedentesFamiliares = [];
        $ants = PersonasAntecedente::find()
            ->where(['id_persona' => (int) $persona->id_persona])
            ->all();
        foreach ($ants as $ant) {
            $term = isset($ant->snomedSituacion) ? (string) $ant->snomedSituacion->term : null;
            $row = ['id' => (int) ($ant->id ?? 0), 'termino' => $term];
            if (($ant->tipo_antecedente ?? null) === 'Familiar') {
                $antecedentesFamiliares[] = $row;
            } else {
                $antecedentesPersonales[] = $row;
            }
        }

        $simularSignos = false;
        if (defined('YII_DEBUG') && YII_DEBUG) {
            $simularSignos = (bool) Yii::$app->request->get('simular_signos', false);
        }
        $signosVitales = (new PersonaSignosVitalesService())->getSignosVitalesData($persona, $simularSignos);

        // La línea de tiempo / eventos agregados no se construye aquí (otro endpoint o servicio cuando corresponda).

        return $this->success([
            'persona' => [
                'id' => (int) $persona->id_persona,
                'nombre_completo' => $persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N_D),
                'documento' => $persona->documento,
                'edad' => $persona->edad,
                'fecha_nacimiento' => $persona->fecha_nacimiento,
            ],
            'informacion_medica' => [
                'condiciones_activas' => $condicionesActivas,
                'condiciones_cronicas' => $condicionesCronicas,
                'hallazgos' => $hallazgos,
                'antecedentes_personales' => $antecedentesPersonales,
                'antecedentes_familiares' => $antecedentesFamiliares,
                'motivos_consulta' => $motivosConsulta,
            ],
            'signos_vitales' => $signosVitales,
            'motivos_consulta_paciente' => $motivosConsultaPaciente,
            'care_pack_cohorte' => $carePackCohorte,
            'care_cohort_habilitado' => CarePackConfig::isEnabled(),
            'turnos_con_encounter' => $motivosCtx['turnos_con_encounter'],
            'historia_clinica' => [],
            'total_historia_clinica' => 0,
        ], 'OK');
    }

    /**
     * Misma respuesta que GET /api/v1/profesional-agenda/dia (incl. turno prueba si aplica).
     *
     * @return array{turnos: array, fecha: string, total: int}
     */
    public static function agendaAmbulatorioJson(string $fecha, int $idContextoProfesional, bool $conTurnoPrueba, ?int $pesId = null): array
    {
        return (new StaffClinicalDayListService())->turnosAmbulatorioMedico(
            $fecha,
            $idContextoProfesional,
            $conTurnoPrueba,
            $pesId
        );
    }

    /**
     * Motivos pre-consulta + contexto de turno/encounter para historia clínica del médico.
     *
     * Query:
     * - `turno_id` o `encounter_id`: contexto explícito (obligatorio en flujo móvil desde agenda).
     * - Sin query: encounter con mensajes del paciente más reciente; si no hay, último turno con encounter.
     *
     * @return array{
     *   motivos_consulta: string|null,
     *   motivos_consulta_paciente: array<string, mixed>,
     *   turnos_con_encounter: list<array<string, mixed>>,
     *   http_error: array<string, mixed>|null
     * }
     */
    private function buildMotivosHistoriaClinicaContext(
        Persona $persona,
        EncounterAppointmentReasonLookupService $motivosLookup,
        int $idEfector
    ): array {
        $personaId = (int) $persona->id_persona;
        $req = Yii::$app->request;
        $turnoIdParam = (int) $req->get('turno_id', 0);
        $encounterIdParam = (int) $req->get('encounter_id', 0);
        $contextoExplicito = $turnoIdParam > 0 || $encounterIdParam > 0;

        $emptyPaciente = [
            'encounter_id' => null,
            'consulta_id' => null,
            'turno_id' => null,
            'turno' => null,
            'contexto_explicito' => $contextoExplicito,
            'messages' => [],
        ];

        $turnosConEncounter = $idEfector > 0
            ? $this->filtrarTurnosConEncounterAccesibles(
                $motivosLookup->listarTurnosConEncounterParaMotivos($personaId, $idEfector)
            )
            : [];

        if ($idEfector > 0 && $turnosConEncounter === [] && $contextoExplicito) {
            return [
                'motivos_consulta' => null,
                'motivos_consulta_paciente' => $emptyPaciente,
                'turnos_con_encounter' => [],
                'http_error' => $this->error(
                    'No hay turnos con consulta asociada para este paciente en su efector.',
                    null,
                    403
                ),
            ];
        }

        $encounter = null;
        $turno = null;

        if ($turnoIdParam > 0) {
            $turno = Turno::findActive()
                ->andWhere(['id_turnos' => $turnoIdParam, 'id_persona' => $personaId])
                ->one();
            if ($turno === null) {
                return [
                    'motivos_consulta' => null,
                    'motivos_consulta_paciente' => $emptyPaciente,
                    'turnos_con_encounter' => $turnosConEncounter,
                    'http_error' => $this->error('Turno no encontrado para este paciente.', null, 404),
                ];
            }
            $encounterId = $motivosLookup->encounterIdParaTurno($turnoIdParam);
            if ($encounterId === null) {
                return [
                    'motivos_consulta' => null,
                    'motivos_consulta_paciente' => $emptyPaciente,
                    'turnos_con_encounter' => $turnosConEncounter,
                    'http_error' => $this->error(
                        'El turno no tiene encounter clínico asociado.',
                        null,
                        404
                    ),
                ];
            }
            $encounter = Encounter::findOne($encounterId);
        } elseif ($encounterIdParam > 0) {
            $encounter = Encounter::findOne($encounterIdParam);
            if (
                $encounter === null
                || (int) $encounter->subject_persona_id !== $personaId
            ) {
                return [
                    'motivos_consulta' => null,
                    'motivos_consulta_paciente' => $emptyPaciente,
                    'turnos_con_encounter' => $turnosConEncounter,
                    'http_error' => $this->error('Encounter no encontrado para este paciente.', null, 404),
                ];
            }
            if ($encounter->appointment_id) {
                $turno = Turno::findActive()
                    ->andWhere(['id_turnos' => (int) $encounter->appointment_id])
                    ->one();
            }
        } elseif ($idEfector > 0) {
            $encounterIdAuto = $motivosLookup->encounterIdConMensajesMotivosRecientes($personaId, $idEfector)
                ?? $motivosLookup->ultimoEncounterIdDesdeTurno($personaId, $idEfector);
            if ($encounterIdAuto !== null) {
                $encounter = Encounter::findOne($encounterIdAuto);
                if ($encounter !== null && $encounter->appointment_id) {
                    $turno = Turno::findActive()
                        ->andWhere(['id_turnos' => (int) $encounter->appointment_id])
                        ->one();
                }
            }
        }

        if ($encounter === null) {
            return [
                'motivos_consulta' => null,
                'motivos_consulta_paciente' => $emptyPaciente,
                'turnos_con_encounter' => $turnosConEncounter,
                'http_error' => null,
            ];
        }

        if (!$this->canAccessEncounterDomain($encounter, 'Atencion.view_mine')) {
            return [
                'motivos_consulta' => null,
                'motivos_consulta_paciente' => $emptyPaciente,
                'turnos_con_encounter' => $turnosConEncounter,
                'http_error' => $this->error(
                    'No tiene permiso para ver los motivos de consulta de este encounter.',
                    null,
                    403
                ),
            ];
        }

        if (!AppointmentReasonWindowService::isHistoriaClinicaVisibleForEncounter($encounter)) {
            $gate = AppointmentReasonWindowService::apiHistoriaClinicaGateState($encounter);
            $min = (int) $gate['minutos_antes_apertura'];

            return [
                'motivos_consulta' => null,
                'motivos_consulta_paciente' => $emptyPaciente,
                'turnos_con_encounter' => $turnosConEncounter,
                'http_error' => $this->error(
                    "La historia clínica estará disponible {$min} minuto(s) antes del turno.",
                    [
                        'codigo' => 'HC_ANTES_DE_VENTANA',
                        'ventana_medico' => $gate,
                    ],
                    403
                ),
            ];
        }

        AppointmentReasonBatchService::ensureProcessedForMedico($encounter);
        $encounter->refresh();

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

        $motivosPaciente = [
            'encounter_id' => $encounterId,
            'consulta_id' => $encounterId,
            'turno_id' => $turno !== null ? (int) $turno->id_turnos : null,
            'turno' => $this->formatTurnoMotivosContext($turno),
            'contexto_explicito' => $contextoExplicito,
            'ventana_medico' => AppointmentReasonWindowService::apiHistoriaClinicaGateState($encounter),
            'resumen' => $resumen,
            'resumen_ia' => $resumen,
            'resumen_pendiente' => $mensajes !== [] && $resumen === null,
            'resumen_ia_pendiente' => $mensajes !== [] && $resumen === null,
            'imagenes_adjuntas' => $imagenesAdjuntas,
            'sugerencias_clinicas' => $insights,
            'messages' => [],
        ];

        return [
            'motivos_consulta' => $reason !== '' ? $reason : null,
            'motivos_consulta_paciente' => $motivosPaciente,
            'turnos_con_encounter' => $turnosConEncounter,
            'http_error' => null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function formatTurnoMotivosContext(?Turno $turno): ?array
    {
        if ($turno === null) {
            return null;
        }

        return [
            'id' => (int) $turno->id_turnos,
            'fecha' => (string) $turno->fecha,
            'hora' => self::formatHoraTurnoCorta($turno->hora),
            'estado' => (string) $turno->estado,
            'estado_label' => Turno::ESTADOS[$turno->estado] ?? 'Sin estado',
        ];
    }

    /**
     * @param list<array{
     *   turno_id: int,
     *   encounter_id: int,
     *   fecha: string,
     *   hora: string,
     *   estado: string,
     *   mensajes_count: int
     * }> $rows
     * @return list<array<string, mixed>>
     */
    private function filtrarTurnosConEncounterAccesibles(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $encounter = Encounter::findOne((int) $row['encounter_id']);
            if ($encounter === null || !$this->canAccessEncounterDomain($encounter, 'Atencion.view_mine')) {
                continue;
            }
            $out[] = $row;
        }

        return $out;
    }

    private static function formatHoraTurnoCorta(?string $hora): string
    {
        if ($hora === null || trim($hora) === '') {
            return '';
        }
        $t = trim($hora);
        if (preg_match('/^(\d{1,2}):(\d{2})/', $t, $m) === 1) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }

        return strlen($t) >= 5 ? substr($t, 0, 5) : $t;
    }
}
