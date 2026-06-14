<?php

namespace common\components\Domain\Scheduling\Service;

use common\components\Domain\Organization\Service\ProfesionalEfectorServicio\AgendaIntervaloMinutos;
use common\components\Domain\Organization\Service\ProfesionalEfectorServicio\AgendaSlotEngine;
use common\models\AgendaFeriados;
use common\models\Person\Persona;
use common\models\ProfesionalEfectorServicio;
use common\models\ProfesionalEfectorServicioAgenda;
use common\models\Scheduling\Turno;
use Yii;
use yii\helpers\Html;

/**
 * Ocupación de agenda por día para el calendario staff (HTML de slots mañana/tarde).
 */
final class TurnoCalendarioOcupacionDiaService
{
    /**
     * @param array<string, mixed> $params dia, id_servicio, id_profesional_efector_servicio, formato
     * @return array<string, mixed>
     */
    public function build(array $params): array
    {
        $dia = $params['dia'] ?? date('Y-m-d');
        $id_servicio = $params['id_servicio'] ?? null;
        $id_efector = Yii::$app->user->getIdEfector();

        $idPesReq = (int) ($params['id_profesional_efector_servicio'] ?? 0);
        $formatoSlots = (($params['formato'] ?? '') === 'slots');

        $turnosQuery = Turno::findActive();
        if ($idPesReq > 0) {
            $pesFiltro = ProfesionalEfectorServicio::findOne(['id' => $idPesReq, 'deleted_at' => null]);
            if ($pesFiltro && (int) $pesFiltro->id_efector === (int) $id_efector) {
                $turnosQuery->andWhere(['id_profesional_efector_servicio' => $idPesReq]);
            } else {
                $turnosQuery->andWhere(['id_efector' => $id_efector])
                    ->andWhere(['id_servicio_asignado' => $id_servicio]);
            }
        } else {
            $turnosQuery->andWhere(['id_efector' => $id_efector])
                ->andWhere(['id_servicio_asignado' => $id_servicio]);
        }

        $turnos = $turnosQuery->andWhere(['fecha' => $dia])
            ->andWhere(['estado' => Turno::ESTADOS_PARA_DESHABILITAR])
            ->orderBy('hora')
            ->all();

        $feriado = AgendaFeriados::getFeriadosPorFecha($dia);
        $mensajeFeriado = '';
        if ($feriado != null) {
            $mensajeFeriado = '<h5 class="ps-5"><u><strong>No se pueden asignar turnos para un dia feriado.</strong></u></h5>';
        }

        $horasTurnosOcupados = [];
        $pacientesTurnosOcupados = [];
        foreach ($turnos as $turno) {
            $horasTurnosOcupados[$turno->id_turnos] = $turno->hora;
            $pacientesTurnosOcupados[$turno->id_turnos] = $turno->paciente->id_persona;
        }

        $nroDiaDeSemana = date('N', strtotime($dia)) - 1;
        $columnasAgenda = ['lunes_2', 'martes_2', 'miercoles_2', 'jueves_2', 'viernes_2', 'sabado_2', 'domingo_2'];

        $slots = [];
        $formasAtencion = 'ORDEN_LLEGADA';

        if ($id_servicio && $idPesReq == 0) {
            $pesRows = ProfesionalEfectorServicio::findAllActivosPorServicioEfector((int) $id_servicio, (int) $id_efector);
            $idsPes = array_map(static function (ProfesionalEfectorServicio $p) {
                return (int) $p->id;
            }, $pesRows);
            $agendas = $idsPes !== []
                ? array_values(ProfesionalEfectorServicioAgenda::findPorIdsProfesionalEfectorServicio($idsPes))
                : [];

            $agendaDiaSeleccionado = false;
            $horariosAgenda = [];
            foreach ($agendas as $agenda) {
                if ($agenda->{$columnasAgenda[$nroDiaDeSemana]}) {
                    $agendaDiaSeleccionado = true;
                    $horariosAgenda = array_merge(
                        $horariosAgenda,
                        array_map('intval', explode(',', $agenda->{$columnasAgenda[$nroDiaDeSemana]}))
                    );
                }
            }

            if (!$agendaDiaSeleccionado) {
                return $this->emptyDayResponse($formatoSlots);
            }

            $horariosAgenda = array_unique($horariosAgenda, SORT_NUMERIC);
            sort($horariosAgenda);

            $mensajePorOrdendeLlegada = '<p class="ps-5"><u><strong>Los turnos se otorgan por orden de llegada.</strong></u></p>';
            if ($dia !== date('Y-m-d')) {
                return $this->messageDayResponse($mensajePorOrdendeLlegada, $formatoSlots);
            }

            $slots = ProfesionalEfectorServicioAgenda::crearSlotsDesdeHorarios(
                $horariosAgenda,
                AgendaIntervaloMinutos::DEFAULT,
                false
            );
        } else {
            $idPes = null;
            if ($idPesReq > 0 && $id_efector) {
                $pesAgenda = ProfesionalEfectorServicio::findOne(['id' => $idPesReq, 'deleted_at' => null]);
                if (
                    $pesAgenda
                    && (int) $pesAgenda->id_efector === (int) $id_efector
                    && (!$id_servicio || (int) $pesAgenda->id_servicio === (int) $id_servicio)
                ) {
                    $idPes = $idPesReq;
                }
            }
            $agenda = $idPes ? ProfesionalEfectorServicioAgenda::findActivaPorProfesionalEfectorServicio($idPes) : null;
            if ($agenda === null) {
                return $this->emptyDayResponse($formatoSlots);
            }

            $formasAtencion = $agenda->formas_atencion;

            if (!$agenda->{$columnasAgenda[$nroDiaDeSemana]} || $agenda->{$columnasAgenda[$nroDiaDeSemana]} == '') {
                return $this->emptyDayResponse($formatoSlots);
            }

            $mensajePorOrdendeLlegada = '<p class="ps-5"><u><strong>Los turnos se otorgan por orden de llegada.</strong></u></p>';
            if ($agenda->formas_atencion == 'ORDEN_LLEGADA' && $dia !== date('Y-m-d')) {
                return $this->messageDayResponse($mensajePorOrdendeLlegada, $formatoSlots);
            }

            $slots = AgendaSlotEngine::slotsParaDia(
                $agenda,
                $dia,
                $agenda->resolveIntervaloMinutosParaSlots()
            );
        }

        return $this->buildSlotsResponse($slots, $dia, $feriado, $mensajeFeriado, $formasAtencion, $horasTurnosOcupados, $pacientesTurnosOcupados, $formatoSlots);
    }

    /**
     * @param list<string> $slots
     * @param mixed $feriado
     * @param array<int|string, string> $horasTurnosOcupados
     * @param array<int|string, int> $pacientesTurnosOcupados
     * @return array<string, mixed>
     */
    private function buildSlotsResponse(
        array $slots,
        string $dia,
        $feriado,
        string $mensajeFeriado,
        string $formasAtencion,
        array $horasTurnosOcupados,
        array $pacientesTurnosOcupados,
        bool $formatoSlots
    ): array {
        $botonesTurnosManiana = [];
        $botonesTurnosTarde = [];
        $slotsDisponibles = [];
        $todosTomados = true;

        foreach ($slots as $slot) {
            $break = false;
            $options = ['class' => 'hora btn btn-outline-primary rounded-pill mt-2 me-1 '];
            $hora = $slot;
            $deshabilitado = false;

            if ($dia == date('Y-m-d')) {
                if ($hora <= date('H:i')) {
                    $options['class'] = 'btn btn-outline-secondary rounded-pill mt-2 me-1 ';
                    $deshabilitado = true;
                } elseif ($formasAtencion == 'ORDEN_LLEGADA') {
                    $break = true;
                }
            }

            if ($feriado != null) {
                $options['class'] = 'btn btn-outline-secondary rounded-pill mt-2 me-1 ';
                $deshabilitado = true;
            }

            $horario = \DateTime::createFromFormat('H:i', $hora);
            $id_turno = array_search($hora . ':00', $horasTurnosOcupados, true);
            if ($id_turno !== false) {
                $break = false;
                $paciente = Persona::findOne($pacientesTurnosOcupados[$id_turno]);
                $turno = Turno::findOne($id_turno);
                $nombrePaciente = $paciente->getNombreCompleto(Persona::FORMATO_NOMBRE_A_OA_N_ON);

                $hora = '<del>' . $hora . '</del>';
                $options['id'] = $id_turno;
                $options['id-persona'] = $pacientesTurnosOcupados[$id_turno];
                $options['estado-turno'] = $turno->estado;
                $options['data-bs-toggle'] = 'tooltip';
                $options['tabindex'] = '0';
                $options['data-bs-placement'] = 'top';
                $options['title'] = $nombrePaciente;

                if ($feriado != null) {
                    $options['class'] .= 'hora';
                }
            } else {
                $todosTomados = false;
                if (!$deshabilitado) {
                    $slotsDisponibles[] = $slot;
                }
            }

            $unaDeLaManiana = \DateTime::createFromFormat('H:i', '00:00');
            $unaDeLaTarde = \DateTime::createFromFormat('H:i', '13:00');

            if ($horario >= $unaDeLaManiana && $horario <= $unaDeLaTarde) {
                $botonesTurnosManiana[] = Html::tag('span', $hora, $options);
            } else {
                $botonesTurnosTarde[] = Html::tag('span', $hora, $options);
            }

            if ($break) {
                return $this->finalizeResponse(
                    $botonesTurnosManiana,
                    $botonesTurnosTarde,
                    $todosTomados,
                    $mensajeFeriado,
                    $slotsDisponibles,
                    $formatoSlots
                );
            }
        }

        return $this->finalizeResponse(
            $botonesTurnosManiana,
            $botonesTurnosTarde,
            $todosTomados,
            $mensajeFeriado,
            $slotsDisponibles,
            $formatoSlots
        );
    }

    /**
     * @param list<string> $maniana
     * @param list<string> $tarde
     * @param list<string> $slotsDisponibles
     * @return array<string, mixed>
     */
    private function finalizeResponse(
        array $maniana,
        array $tarde,
        bool $todosTomados,
        string $mensajeFeriado,
        array $slotsDisponibles,
        bool $formatoSlots
    ): array {
        $resp = [
            'turnos' => [
                'maniana' => $maniana,
                'tarde' => $tarde,
                'todosTomados' => $todosTomados,
                'mensajeFeriado' => $mensajeFeriado,
            ],
        ];
        if ($formatoSlots) {
            $resp['results'] = array_map(static function ($h) {
                return ['id' => $h, 'text' => $h];
            }, $slotsDisponibles);
        }

        return $resp;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDayResponse(bool $formatoSlots): array
    {
        $mensaje = '<p class="fst-italic ps-5">Sin turnos disponibles.</p>';

        return $this->messageDayResponse($mensaje, $formatoSlots);
    }

    /**
     * @return array<string, mixed>
     */
    private function messageDayResponse(string $html, bool $formatoSlots): array
    {
        $ret = ['turnos' => ['maniana' => $html, 'tarde' => $html]];
        if ($formatoSlots) {
            $ret['results'] = [];
        }

        return $ret;
    }
}
