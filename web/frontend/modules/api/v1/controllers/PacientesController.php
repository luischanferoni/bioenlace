<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use common\models\Cirugia;
use common\models\Consulta;
use common\models\Guardia;
use common\models\InfraestructuraPiso;
use common\models\Persona;
use common\models\QuirofanoSala;
use common\models\RrhhEfector;
use common\models\ProfesionalEfectorServicio;
use common\models\Servicio;
use common\models\ServiciosEfector;
use common\models\Turno;
use common\models\Alergias;
use common\models\PersonasAntecedente;
use common\models\DiagnosticoConsultaRepository as DCRepo;
use common\models\ConsultaMotivosMessage;
use common\components\Services\Persona\PersonaSignosVitalesService;
use common\components\Services\Consulta\ConsultaAccessService;
/**
 * Listado de pacientes por modalidad (encounter): ambulatorio, internación, guardia.
 */
class PacientesController extends BaseController
{
    /**
     * Listado de pacientes según encounter del usuario (turnos / internados / guardia).
     * GET /api/v1/pacientes?fecha=YYYY-MM-DD
     */
    public function actionListar()
    {
        $fecha = Yii::$app->request->get('fecha', date('Y-m-d'));
        $encounterClass = Yii::$app->user->getEncounterClass();

        if (!$encounterClass) {
            return [
                'success' => false,
                'message' => 'No hay encounter configurado en la sesión.',
                'encounter_class' => null,
                'kind' => null,
                'data' => [],
                'fecha' => $fecha,
            ];
        }

        switch ($encounterClass) {
            case Consulta::ENCOUNTER_CLASS_AMB:
                $conPrueba = Yii::$app->request->get('prueba') === '1';
                $payload = $this->turnosAmbulatorioMedico($fecha, null, $conPrueba);
                return [
                    'success' => true,
                    'message' => 'OK',
                    'encounter_class' => $encounterClass,
                    'kind' => 'turnos',
                    'data' => $payload['turnos'],
                    'fecha' => $payload['fecha'],
                    'total' => $payload['total'],
                ];
            case Consulta::ENCOUNTER_CLASS_IMP:
                $idServicioSesion = (int) Yii::$app->user->getServicioActual();
                if ($idServicioSesion && Servicio::esServicioAgendaQuirurgica($idServicioSesion)) {
                    $data = $this->cirugiasAgendadasPorEfectorYFecha($fecha);
                    return [
                        'success' => true,
                        'message' => 'OK',
                        'encounter_class' => $encounterClass,
                        'kind' => 'cirugias',
                        'data' => $data,
                        'fecha' => $fecha,
                        'total' => count($data),
                    ];
                }
                $data = $this->internadosPorEfector();
                return [
                    'success' => true,
                    'message' => 'OK',
                    'encounter_class' => $encounterClass,
                    'kind' => 'internados',
                    'data' => $data,
                    'fecha' => $fecha,
                ];
            case Consulta::ENCOUNTER_CLASS_EMER:
                $data = $this->guardiasPendientesPorEfector();
                return [
                    'success' => true,
                    'message' => 'OK',
                    'encounter_class' => $encounterClass,
                    'kind' => 'guardias',
                    'data' => $data,
                    'fecha' => $fecha,
                ];
            default:
                return [
                    'success' => false,
                    'message' => 'Encounter no soportado.',
                    'encounter_class' => $encounterClass,
                    'kind' => null,
                    'data' => [],
                    'fecha' => $fecha,
                ];
        }
    }

    /**
     * Resumen de historia clínica (persona + información médica + signos vitales + mensajes de motivos de la app del paciente). No arma lista de eventos aquí.
     *
     * GET /api/v1/personas/{id}/historia-clinica
     * Query (solo YII_DEBUG): simular_signos=1 — misma semántica que GET .../signos-vitales.
     * RBAC: /api/pacientes/historia-clinica
     */
    public function actionHistoriaClinica($id)
    {
        $persona = Persona::findOne((int) $id);
        if (!$persona) {
            return $this->error('Persona no encontrada', null, 404);
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

        // Alergias
        $hallazgos = [];
        $alergias = Alergias::find()->where(['id_persona' => (int) $persona->id_persona])->all();
        foreach ($alergias as $a) {
            $term = isset($a->codigoSnomed) ? (string) $a->codigoSnomed->term : null;
            $code = isset($a->codigoSnomed) ? (string) $a->codigoSnomed->conceptId : null;
            $hallazgos[] = ['id' => (int) ($a->id ?? 0), 'codigo' => $code, 'termino' => $term];
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

        $idEfector = Yii::$app->user->getIdEfector();
        $motivosConsulta = $idEfector ? Consulta::getUltimoMotivoConsultaTurno((int) $persona->id_persona, (int) $idEfector) : null;

        $motivosConsultaPaciente = [
            'consulta_id' => null,
            'messages' => [],
        ];
        if ($idEfector) {
            $idConsultaMotivos = Consulta::getUltimaConsultaIdDesdeTurno((int) $persona->id_persona, (int) $idEfector);
            if ($idConsultaMotivos) {
                $consultaMotivos = Consulta::findOne($idConsultaMotivos);
                if ($consultaMotivos && $this->canAccessConsultaMotivos($consultaMotivos)) {
                    $mensajes = ConsultaMotivosMessage::find()
                        ->where(['consulta_id' => $idConsultaMotivos])
                        ->orderBy(['created_at' => SORT_ASC])
                        ->all();
                    $hostBase = Yii::$app->request->hostInfo . (Yii::getAlias('@web') ?: '');
                    $motivosConsultaPaciente = [
                        'consulta_id' => $idConsultaMotivos,
                        'messages' => ConsultaMotivosMessage::serializeForApi($mensajes, $hostBase),
                    ];
                }
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
            'historia_clinica' => [],
            'total_historia_clinica' => 0,
        ], 'OK');
    }

    /**
     * Misma regla que {@see MotivosConsultaController::canAccessConsulta}.
     */
    private function canAccessConsultaMotivos(Consulta $consulta): bool
    {
        return ConsultaAccessService::userCanAccessConsultaApi($consulta);
    }

    /**
     * Misma respuesta que GET /api/v1/profesional-agenda/dia (incl. turno prueba si aplica).
     *
     * @return array{turnos: array, fecha: string, total: int}
     */
    public static function agendaAmbulatorioJson(string $fecha, int $rrhhId, bool $conTurnoPrueba, ?int $pesId = null): array
    {
        $c = new self('pacientes', Yii::$app->getModule('v1'));
        return $c->turnosAmbulatorioMedico($fecha, $rrhhId, $conTurnoPrueba, $pesId);
    }

    /**
     * @return array{turnos: array, fecha: string, total: int}
     */
    private function turnosAmbulatorioMedico(string $fecha, ?int $rrhhId, bool $agregarTurnoPruebaSiHoy, ?int $pesIdParam = null): array
    {
        $idPersona = (int) Yii::$app->user->getIdPersona();

        $pesId = $pesIdParam !== null && (int) $pesIdParam > 0 ? (int) $pesIdParam : 0;
        if ($pesId <= 0) {
            $s = Yii::$app->user->getIdProfesionalEfectorServicio();
            $pesId = $s !== null && $s !== '' ? (int) $s : 0;
        }

        $pes = null;
        if ($pesId > 0) {
            $pes = ProfesionalEfectorServicio::findOne(['id' => $pesId, 'deleted_at' => null]);
            if (!$pes || (int) $pes->id_persona !== $idPersona) {
                $pes = null;
                $pesId = 0;
            }
        }

        if ($rrhhId === null || (int) $rrhhId <= 0) {
            $rh = Yii::$app->user->getIdRecursoHumano();
            $rrhhId = $rh !== null && $rh !== '' ? (int) $rh : 0;
        } else {
            $rrhhId = (int) $rrhhId;
        }

        $rrhhOk = $rrhhId > 0 && RrhhEfector::find()
            ->where(['id_rr_hh' => $rrhhId, 'id_persona' => $idPersona])
            ->andWhere(['deleted_at' => null])
            ->exists();

        if ($pesId <= 0 && !$rrhhOk) {
            return ['turnos' => [], 'fecha' => $fecha, 'total' => 0];
        }

        $turnosQuery = Turno::findActive()
            ->andWhere(['fecha' => $fecha])
            ->andWhere(['estado' => Turno::ESTADO_PENDIENTE])
            ->andWhere(['is', 'atendido', null])
            ->orderBy('hora');

        if ($pesId > 0 && $rrhhId > 0) {
            $turnosQuery->andWhere([
                'or',
                ['id_profesional_efector_servicio' => $pesId],
                ['id_rr_hh' => $rrhhId],
            ]);
        } elseif ($pesId > 0) {
            $turnosQuery->andWhere(['id_profesional_efector_servicio' => $pesId]);
        } else {
            $turnosQuery->andWhere(['id_rr_hh' => $rrhhId]);
        }

        $turnos = $turnosQuery->all();

        $formattedTurnos = [];
        foreach ($turnos as $turno) {
            $paciente = $turno->persona;
            $servicio = $turno->getNombreServicioParaDisplay();
            $consulta = Consulta::findOne(['id_turnos' => $turno->id_turnos]);
            $formattedTurnos[] = [
                'id' => $turno->id_turnos,
                'id_persona' => $turno->id_persona,
                // Contexto profesional (canónico vs legacy)
                'id_profesional_efector_servicio' => (int) ($turno->id_profesional_efector_servicio ?? 0) ?: null,
                'id_rr_hh' => (int) ($turno->id_rr_hh ?? 0) ?: null,
                'paciente' => [
                    'id' => $paciente ? $paciente->id_persona : null,
                    'nombre_completo' => $paciente ? $paciente->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N_D) : 'Sin paciente',
                    'documento' => $paciente ? $paciente->documento : null,
                ],
                'fecha' => $turno->fecha,
                'hora' => $turno->hora,
                'servicio' => $servicio,
                'id_servicio_asignado' => $turno->id_servicio_asignado,
                'estado' => $turno->estado,
                'estado_label' => Turno::ESTADOS[$turno->estado] ?? 'Sin estado',
                'tipo_atencion' => isset($turno->tipo_atencion) ? $turno->tipo_atencion : Turno::TIPO_ATENCION_PRESENCIAL,
                'id_consulta' => $consulta ? $consulta->id_consulta : null,
                'atendido' => $turno->atendido,
                'created_at' => $turno->created_at,
                'observaciones' => $turno->hasAttribute('observaciones') ? $turno->observaciones : null,
            ];
        }

        if ($agregarTurnoPruebaSiHoy && $fecha === date('Y-m-d')) {
            $pacientePrueba = Persona::findOne(920779);
            if ($pacientePrueba) {
                $servicioPrueba = null;
                $idServicioAsignado = null;
                $idEfectorPrueba = null;
                if ($pes !== null) {
                    $idEfectorPrueba = (int) $pes->id_efector;
                } elseif ($rrhhId > 0) {
                    $rrhhEfector = RrhhEfector::find()
                        ->where(['id_rr_hh' => $rrhhId, 'id_persona' => $idPersona, 'deleted_at' => null])
                        ->one();
                    if ($rrhhEfector) {
                        $idEfectorPrueba = (int) $rrhhEfector->id_efector;
                    }
                }
                if ($idEfectorPrueba) {
                    $servicioEfector = ServiciosEfector::find()->where(['id_efector' => $idEfectorPrueba])->one();
                    if ($servicioEfector) {
                        $servicioPrueba = $servicioEfector->servicio ? $servicioEfector->servicio->nombre : 'Consulta General';
                        $idServicioAsignado = $servicioEfector->id_servicio;
                    }
                }
                if (!$servicioPrueba) {
                    $servicioPrueba = 'Consulta General';
                }
                array_unshift($formattedTurnos, [
                    'id' => 999999,
                    'id_persona' => 920779,
                    'paciente' => [
                        'id' => $pacientePrueba->id_persona,
                        'nombre_completo' => $pacientePrueba->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N_D),
                        'documento' => $pacientePrueba->documento,
                    ],
                    'fecha' => $fecha,
                    'hora' => '10:00',
                    'servicio' => $servicioPrueba,
                    'id_servicio_asignado' => $idServicioAsignado,
                    'estado' => Turno::ESTADO_PENDIENTE,
                    'estado_label' => Turno::ESTADOS[Turno::ESTADO_PENDIENTE] ?? 'Pendiente',
                    'atendido' => null,
                    'created_at' => date('Y-m-d H:i:s'),
                    'observaciones' => null,
                ]);
            }
        }

        return [
            'turnos' => $formattedTurnos,
            'fecha' => $fecha,
            'total' => count($formattedTurnos),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function internadosPorEfector(): array
    {
        $idEfector = Yii::$app->user->getIdEfector();
        if (!$idEfector) {
            return [];
        }
        return InfraestructuraPiso::getInternadosPorEfector($idEfector);
    }

    /**
     * Cirugías del efector en la fecha indicada (ventana calendario del día local).
     *
     * @return array<int, array<string, mixed>>
     */
    private function cirugiasAgendadasPorEfectorYFecha(string $fecha): array
    {
        $idEfector = (int) Yii::$app->user->getIdEfector();
        if (!$idEfector) {
            return [];
        }
        $inicio = $fecha . ' 00:00:00';
        $fin = $fecha . ' 23:59:59';
        $rows = Cirugia::find()->alias('c')
            ->innerJoin(['s' => QuirofanoSala::tableName()], 's.id = c.id_quirofano_sala')
            ->where(['s.id_efector' => $idEfector])
            ->andWhere(['s.deleted_at' => null])
            ->andWhere(['>=', 'c.fecha_hora_inicio', $inicio])
            ->andWhere(['<=', 'c.fecha_hora_inicio', $fin])
            ->orderBy(['c.fecha_hora_inicio' => SORT_ASC])
            ->all();

        $out = [];
        foreach ($rows as $c) {
            /** @var Cirugia $c */
            $paciente = $c->persona;
            $sala = $c->sala;
            $out[] = [
                'id' => (int) $c->id,
                'id_persona' => (int) $c->id_persona,
                'paciente' => [
                    'id' => $paciente ? (int) $paciente->id_persona : null,
                    'nombre_completo' => $paciente ? $paciente->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N_D) : 'Sin paciente',
                    'documento' => $paciente ? $paciente->documento : null,
                ],
                'sala_nombre' => $sala ? $sala->nombre : '',
                'fecha_hora_inicio' => $c->fecha_hora_inicio,
                'fecha_hora_fin_estimada' => $c->fecha_hora_fin_estimada,
                'estado' => $c->estado,
                'estado_label' => $c->getEstadoLabel(),
            ];
        }

        return $out;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function guardiasPendientesPorEfector(): array
    {
        $idEfector = Yii::$app->user->getIdEfector();
        if (!$idEfector) {
            return [];
        }
        $out = [];
        foreach (Guardia::pacientesPendientesPorEfector($idEfector) as $guardia) {
            $paciente = $guardia->paciente;
            $out[] = [
                'id' => $guardia->id,
                'id_persona' => $guardia->id_persona,
                'nombre_completo' => $paciente ? $paciente->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N) : 'Sin nombre',
                'documento' => $paciente ? $paciente->documento : null,
                'tipo_documento' => $paciente && $paciente->tipoDocumento ? $paciente->tipoDocumento->nombre : null,
                'fecha' => $guardia->fecha,
                'hora' => $guardia->hora,
                'estado' => $guardia->estado,
            ];
        }
        return $out;
    }
}
