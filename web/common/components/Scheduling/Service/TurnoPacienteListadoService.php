<?php

namespace common\components\Scheduling\Service;

use common\components\Clinical\CareCohort\Service\CarePackConfig;
use common\components\Clinical\Service\AppointmentReasonWindowService;
use common\components\Person\Representation\Enum\RepresentationPermission;
use common\components\Person\Representation\Service\PersonRepresentationSubjectService;
use common\models\Clinical\Encounter;
use common\models\Person\Persona;
use common\models\Scheduling\Turno;
use common\models\TurnoResolucion;
use Yii;
use yii\db\Expression;

/**
 * Listados de turnos del paciente (autogestión / panel de inicio).
 */
final class TurnoPacienteListadoService
{
    /**
     * @param array<string, mixed> $params subject_persona_id, alcance, limit, offset, fecha_desde, fecha_hasta
     * @return array<string, mixed>
     */
    public function list(array $params): array
    {
        $idPersona = (new PersonRepresentationSubjectService())->resolveAndAuthorize(
            $params,
            RepresentationPermission::SCHEDULING_TURNO
        );
        $alcance = isset($params['alcance']) ? (string) $params['alcance'] : '';

        if ($alcance === 'pendientes' || $alcance === 'pasados' || $alcance === 'en_resolucion') {
            $limit = isset($params['limit']) && $params['limit'] !== '' ? (int) $params['limit'] : 20;
            $limit = max(1, min(100, $limit));
            $offset = isset($params['offset']) && $params['offset'] !== '' ? (int) $params['offset'] : 0;
            $offset = max(0, $offset);

            $ahoraLocal = $this->ahoraLocalParaComparacionTurno();
            $hoyProducto = $this->hoyProductoParaTurnos();

            if ($alcance === 'en_resolucion') {
                $turnosQ = Turno::findActive()->alias('t')
                    ->where(['t.id_persona' => $idPersona])
                    ->andWhere(['t.estado' => Turno::ESTADO_EN_RESOLUCION])
                    ->andWhere(['>=', 't.fecha', $hoyProducto])
                    ->orderBy(['t.fecha' => SORT_ASC, 't.hora' => SORT_ASC]);
            } elseif ($alcance === 'pendientes') {
                $turnosQ = Turno::findActive()->alias('t')
                    ->where(['t.id_persona' => $idPersona])
                    ->andWhere(['t.estado' => Turno::ESTADO_PENDIENTE])
                    ->andWhere(['>=', 't.fecha', $hoyProducto]);

                if (isset($params['fecha_hasta']) && $params['fecha_hasta'] !== '') {
                    $turnosQ->andWhere(['<=', 't.fecha', $params['fecha_hasta']]);
                } else {
                    $turnosQ->andWhere(['<=', 't.fecha', date('Y-m-d', strtotime($hoyProducto . ' +3 months'))]);
                }
                $turnosQ->orderBy(['t.fecha' => SORT_ASC, 't.hora' => SORT_ASC]);
            } else {
                $turnosQ = Turno::findActive()->alias('t')
                    ->where(['t.id_persona' => $idPersona])
                    ->andWhere(['<', new Expression('TIMESTAMP(t.fecha, t.hora)'), $ahoraLocal])
                    ->andWhere([
                        'or',
                        ['<', 't.fecha', $hoyProducto],
                        ['not', ['t.estado' => Turno::ESTADO_PENDIENTE]],
                        ['not', ['t.estado' => Turno::ESTADO_EN_RESOLUCION]],
                    ]);

                if (isset($params['fecha_hasta']) && $params['fecha_hasta'] !== '') {
                    $turnosQ->andWhere(['<=', 't.fecha', $params['fecha_hasta']]);
                }
                if (isset($params['fecha_desde']) && $params['fecha_desde'] !== '') {
                    $turnosQ->andWhere(['>=', 't.fecha', $params['fecha_desde']]);
                }
                $turnosQ->orderBy(['t.fecha' => SORT_DESC, 't.hora' => SORT_DESC]);
            }

            $total = (int) (clone $turnosQ)->count('*');
            $turnos = $turnosQ->limit($limit)->offset($offset)->all();

            $policySvc = new TurnoCancellationPolicyService();
            $anticipSvc = new TurnoAutogestionAnticipacionService();
            $policyOkPorEfector = [];

            $formattedTurnos = [];
            foreach ($turnos as $turno) {
                $row = $this->formatTurnoPacienteListadoRow($turno);
                if (
                    ($alcance === 'pendientes' && $turno->estado === Turno::ESTADO_PENDIENTE)
                    || ($alcance === 'en_resolucion' && $turno->estado === Turno::ESTADO_EN_RESOLUCION)
                ) {
                    if ($alcance === 'en_resolucion') {
                        $row['puede_cancelar_autogestion_app'] = true;
                        $row['puede_reprogramar_autogestion_app'] = true;
                        $formattedTurnos[] = $row;
                        continue;
                    }
                    $idEf = (int) ($turno->id_efector ?? 0);
                    if (!array_key_exists($idEf, $policyOkPorEfector)) {
                        $policyOkPorEfector[$idEf] = $idEf <= 0 || !$policySvc->autogestionBloqueada($idPersona, $idEf);
                    }
                    $hC = $anticipSvc->minHorasAntesCancelarParaEfector($idEf);
                    $hR = $anticipSvc->minHorasAntesReprogramarParaEfector($idEf);
                    $row['puede_cancelar_autogestion_app'] = $policyOkPorEfector[$idEf]
                        && $anticipSvc->ahoraEsAntesDeLimite($turno, $hC);
                    $row['puede_reprogramar_autogestion_app'] = $policyOkPorEfector[$idEf]
                        && $anticipSvc->ahoraEsAntesDeLimite($turno, $hR);
                } else {
                    $row['puede_cancelar_autogestion_app'] = false;
                    $row['puede_reprogramar_autogestion_app'] = false;
                }
                $formattedTurnos[] = $row;
            }

            return [
                'turnos' => $formattedTurnos,
                'total' => $total,
                'alcance' => $alcance,
                'limit' => $limit,
                'offset' => $offset,
            ];
        }

        $fechaDesde = isset($params['fecha_desde']) && $params['fecha_desde'] !== ''
            ? $params['fecha_desde'] : date('Y-m-d');
        $fechaHasta = isset($params['fecha_hasta']) && $params['fecha_hasta'] !== ''
            ? $params['fecha_hasta'] : date('Y-m-d', strtotime('+3 months'));

        $turnosQ = Turno::findActive()->where(['id_persona' => $idPersona])
            ->andWhere(['>=', 'fecha', $fechaDesde])
            ->andWhere(['<=', 'fecha', $fechaHasta])
            ->andWhere(['estado' => Turno::ESTADO_PENDIENTE])
            ->orderBy(['fecha' => SORT_ASC, 'hora' => SORT_ASC]);
        $turnos = $turnosQ->all();

        $formattedTurnos = [];
        foreach ($turnos as $turno) {
            $formattedTurnos[] = $this->formatTurnoPacienteListadoRow($turno);
        }

        return [
            'turnos' => $formattedTurnos,
            'total' => count($formattedTurnos),
        ];
    }

    /**
     * Bloque resumido para panel de inicio paciente (en_resolucion + pendientes).
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function listForHomePanel(array $params): array
    {
        $base = $params;
        $base['limit'] = $params['limit'] ?? 100;
        $base['offset'] = 0;

        $enResolucion = $this->list(array_merge($base, ['alcance' => 'en_resolucion']));
        $pendientes = $this->list(array_merge($base, ['alcance' => 'pendientes']));

        return [
            'en_resolucion' => $enResolucion,
            'pendientes' => $pendientes,
        ];
    }

    private function ahoraLocalParaComparacionTurno(): string
    {
        try {
            $tz = new \DateTimeZone(Yii::$app->timeZone);
        } catch (\Exception $e) {
            $tz = new \DateTimeZone('UTC');
        }

        return (new \DateTimeImmutable('now', $tz))->format('Y-m-d H:i:s');
    }

    private function hoyProductoParaTurnos(): string
    {
        try {
            $tz = new \DateTimeZone(Yii::$app->timeZone);
        } catch (\Exception $e) {
            $tz = new \DateTimeZone('UTC');
        }

        return (new \DateTimeImmutable('now', $tz))->format('Y-m-d');
    }

    private function formatHoraTurnoPacienteCorta(?string $hora): string
    {
        if ($hora === null || trim($hora) === '') {
            return '';
        }
        $t = trim($hora);
        if (preg_match('/^(\d{1,2}):(\d{2})/', $t, $m) === 1) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }
        if (strlen($t) >= 5) {
            return substr($t, 0, 5);
        }

        return $t;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatTurnoPacienteListadoRow(Turno $turno): array
    {
        $servicioNombre = $turno->getNombreServicioParaDisplay();
        $servicioObj = $turno->getServicioEmbebidoParaApi();
        $encounter = Encounter::findOne(['appointment_id' => $turno->id_turnos]);
        $encounterId = $encounter ? (int) $encounter->id : null;
        $profPersona = $turno->getProfesionalPersonaParaDisplay();
        $profesional = $profPersona
            ? $profPersona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N_D)
            : null;
        $idPes = (int) ($turno->id_profesional_efector_servicio ?? 0);
        $idEf = $turno->id_efector !== null && (int) $turno->id_efector > 0 ? (int) $turno->id_efector : null;

        $resolucion = TurnoResolucion::findPendientePorTurno((int) $turno->id_turnos);

        return [
            'id' => $turno->id_turnos,
            'id_persona' => $turno->id_persona,
            'fecha' => $turno->fecha,
            'hora' => $this->formatHoraTurnoPacienteCorta($turno->hora),
            'servicio' => $servicioNombre,
            'servicio_detalle' => $servicioObj,
            'id_servicio_asignado' => $turno->id_servicio_asignado,
            'id_profesional_efector_servicio' => $idPes > 0 ? $idPes : null,
            'id_efector' => $idEf,
            'estado' => $turno->estado,
            'estado_label' => Turno::ESTADOS[$turno->estado] ?? 'Sin estado',
            'tipo_atencion' => isset($turno->tipo_atencion) ? $turno->tipo_atencion : Turno::TIPO_ATENCION_PRESENCIAL,
            'encounter_id' => $encounterId,
            'id_consulta' => $encounterId,
            'motivos_input_abierto' => $encounterId !== null
                && AppointmentReasonWindowService::isInputOpen($encounterId),
            'motivos_cierre_minutos' => AppointmentReasonWindowService::minutesBeforeClose(),
            'asistencia_cohorte_disponible' => CarePackConfig::isEnabled()
                && $encounterId !== null
                && AppointmentReasonWindowService::isInputOpen($encounterId),
            'profesional' => $profesional,
            'created_at' => $turno->created_at,
            'en_resolucion' => $turno->estado === Turno::ESTADO_EN_RESOLUCION,
            'turno_resolucion' => $resolucion !== null ? $resolucion->toPacienteApiArray() : null,
        ];
    }
}
