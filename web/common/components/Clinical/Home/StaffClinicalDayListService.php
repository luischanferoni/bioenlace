<?php

namespace common\components\Clinical\Home;

use Yii;
use common\components\Clinical\Service\EncounterAppointmentReasonLookupService;
use common\models\Cirugia;
use common\models\InfraestructuraPiso;
use common\models\Person\Persona;
use common\models\ProfesionalEfectorServicio;
use common\models\QuirofanoSala;
use common\models\ServiciosEfector;
use common\models\Scheduling\Turno;

/**
 * Listados clínicos del día para staff (AMB / IMP piso / IMP quirófano).
 */
final class StaffClinicalDayListService
{
    /**
     * @return array{turnos: array<int, array<string, mixed>>, fecha: string, total: int}
     */
    public function turnosAmbulatorioMedico(
        string $fecha,
        ?int $idContextoProfesional,
        bool $agregarTurnoPruebaSiHoy,
        ?int $pesIdParam = null
    ): array {
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

        if ($idContextoProfesional === null || (int) $idContextoProfesional <= 0) {
            $rh = Yii::$app->user->getIdProfesionalEfectorServicio();
            $idContextoProfesional = $rh !== null && $rh !== '' ? (int) $rh : 0;
        } else {
            $idContextoProfesional = (int) $idContextoProfesional;
        }

        $contextoProfesionalOk = false;
        if ($idContextoProfesional > 0) {
            $resolved = ProfesionalEfectorServicio::resolveIdPersonaFromStaffContextId($idContextoProfesional);
            $contextoProfesionalOk = $resolved !== null && (int) $resolved === $idPersona;
        }

        if ($pesId <= 0 && !$contextoProfesionalOk) {
            return ['turnos' => [], 'fecha' => $fecha, 'total' => 0];
        }

        $turnosQuery = Turno::findActive()
            ->andWhere(['fecha' => $fecha])
            ->andWhere(['estado' => Turno::ESTADO_PENDIENTE])
            ->andWhere(['is', 'atendido', null])
            ->orderBy('hora');

        if ($pesId > 0 && $idContextoProfesional > 0) {
            $turnosQuery->andWhere([
                'or',
                ['id_profesional_efector_servicio' => $pesId],
                ['id_profesional_efector_servicio' => $idContextoProfesional],
            ]);
        } elseif ($pesId > 0) {
            $turnosQuery->andWhere(['id_profesional_efector_servicio' => $pesId]);
        } elseif ($idContextoProfesional > 0 && $contextoProfesionalOk) {
            $turnosQuery->andWhere(['id_profesional_efector_servicio' => $idContextoProfesional]);
        }

        $turnos = $turnosQuery->all();
        $motivosLookup = new EncounterAppointmentReasonLookupService();

        $formattedTurnos = [];
        foreach ($turnos as $turno) {
            $paciente = $turno->paciente;
            $servicioNombre = $turno->getNombreServicioParaDisplay();
            $servicioObj = $turno->getServicioEmbebidoParaApi();
            $encounterId = $motivosLookup->encounterIdParaTurno((int) $turno->id_turnos);
            $pesTurno = (int) ($turno->id_profesional_efector_servicio ?? 0) ?: null;
            $formattedTurnos[] = [
                'id' => $turno->id_turnos,
                'id_persona' => $turno->id_persona,
                'id_profesional_efector_servicio' => $pesTurno,
                'paciente' => [
                    'id' => $paciente ? $paciente->id_persona : null,
                    'nombre_completo' => $paciente ? $paciente->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N_D) : 'Sin paciente',
                    'documento' => $paciente ? $paciente->documento : null,
                ],
                'fecha' => $turno->fecha,
                'hora' => $turno->hora,
                'servicio' => $servicioNombre,
                'servicio_detalle' => $servicioObj,
                'id_servicio_asignado' => $turno->id_servicio_asignado,
                'estado' => $turno->estado,
                'estado_label' => Turno::ESTADOS[$turno->estado] ?? 'Sin estado',
                'tipo_atencion' => isset($turno->tipo_atencion) ? $turno->tipo_atencion : Turno::TIPO_ATENCION_PRESENCIAL,
                'encounter_id' => $encounterId,
                'id_consulta' => $encounterId,
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
                } elseif ($idContextoProfesional > 0) {
                    if (ProfesionalEfectorServicio::resolveIdPersonaFromStaffContextId($idContextoProfesional) === $idPersona) {
                        $pesPrueba = ProfesionalEfectorServicio::find()
                            ->where(['id_persona' => $idPersona, 'deleted_at' => null])
                            ->orderBy(['id_efector' => SORT_ASC, 'id' => SORT_ASC])
                            ->one();
                        if ($pesPrueba !== null) {
                            $idEfectorPrueba = (int) $pesPrueba->id_efector;
                        }
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
    public function internadosPorEfector(): array
    {
        $idEfector = Yii::$app->user->getIdEfector();
        if (!$idEfector) {
            return [];
        }

        return InfraestructuraPiso::getInternadosPorEfector($idEfector);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function cirugiasAgendadasPorEfectorYFecha(string $fecha): array
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
}
