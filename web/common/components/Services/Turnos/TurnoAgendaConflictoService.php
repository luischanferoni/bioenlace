<?php

namespace common\components\Services\Turnos;

use common\components\Services\ProfesionalEfectorServicio\ProfesionalEfectorServicioAgendaVersionService;
use common\models\Persona;
use common\models\Turno;
use common\models\TurnoAgendaConflicto;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

/**
 * Listado y resolución de conflictos de agenda (paciente y staff).
 */
final class TurnoAgendaConflictoService
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function listarPendientesParaPaciente(int $idPersona, bool $soloConConflicto = false): array
    {
        if ($idPersona <= 0) {
            return [];
        }

        $query = Turno::findActive()->alias('t')
            ->where([
                't.id_persona' => $idPersona,
                't.estado' => Turno::ESTADO_PENDIENTE,
            ])
            ->andWhere(['>=', 't.fecha', date('Y-m-d')])
            ->orderBy(['t.fecha' => SORT_ASC, 't.hora' => SORT_ASC]);

        if ($soloConConflicto) {
            $query->innerJoin(
                ['c' => TurnoAgendaConflicto::tableName()],
                'c.id_turno = t.id_turnos AND c.estado = :estadoConf',
                [':estadoConf' => TurnoAgendaConflicto::ESTADO_PENDIENTE]
            );
        }

        $out = [];
        foreach ($query->all() as $turno) {
            $out[] = self::formatTurnoListItem($turno);
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listarConflictosPendientesStaff(int $idEfector, ?int $idPes = null): array
    {
        if ($idEfector <= 0) {
            throw new BadRequestHttpException('id_efector requerido.');
        }

        $query = TurnoAgendaConflicto::find()
            ->alias('c')
            ->innerJoin(['t' => Turno::tableName()], 't.id_turnos = c.id_turno')
            ->where([
                'c.estado' => TurnoAgendaConflicto::ESTADO_PENDIENTE,
                't.estado' => Turno::ESTADO_PENDIENTE,
                't.id_efector' => $idEfector,
            ])
            ->andWhere(['>=', 't.fecha', date('Y-m-d')])
            ->orderBy(['t.fecha' => SORT_ASC, 't.hora' => SORT_ASC]);

        if ($idPes !== null && $idPes > 0) {
            $query->andWhere(['t.id_profesional_efector_servicio' => $idPes]);
        }

        $out = [];
        foreach ($query->all() as $conf) {
            $turno = $conf->turno;
            if ($turno === null) {
                continue;
            }
            $row = self::formatTurnoListItem($turno);
            $row['agenda_conflicto'] = $conf->toPacienteApiArray();
            $paciente = $turno->paciente;
            if ($paciente !== null) {
                $row['paciente'] = [
                    'id_persona' => (int) $paciente->id_persona,
                    'nombre' => $paciente->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N_D),
                ];
            }
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public static function resolverConflictoStaff(int $idTurno, int $idEfector, string $eleccion): array
    {
        if ($idEfector <= 0) {
            throw new BadRequestHttpException('Sesión operativa sin efector.');
        }

        $turno = Turno::findOne($idTurno);
        if ($turno === null || (int) $turno->id_efector !== $idEfector) {
            throw new NotFoundHttpException('Turno no encontrado en este efector.');
        }

        $idPersona = (int) $turno->id_persona;
        if ($idPersona <= 0) {
            throw new BadRequestHttpException('Turno sin paciente asociado.');
        }

        return ProfesionalEfectorServicioAgendaVersionService::resolverConflictoPaciente(
            $idTurno,
            $idPersona,
            $eleccion
        );
    }

    /**
     * @return array{id: string, name: string, meta?: array<string, mixed>}
     */
    public static function toListPickerItem(array $row): array
    {
        $id = isset($row['id']) ? (string) $row['id'] : '';
        $fecha = isset($row['fecha']) ? (string) $row['fecha'] : '';
        $hora = isset($row['hora']) ? substr((string) $row['hora'], 0, 5) : '';
        $svc = isset($row['servicio']) ? (string) $row['servicio'] : '';
        $prof = isset($row['profesional']) ? (string) $row['profesional'] : '';
        $paciente = isset($row['paciente']['nombre']) ? (string) $row['paciente']['nombre'] : '';

        $parts = array_filter([$fecha, $hora, $paciente, $svc, $prof]);
        $label = implode(' · ', $parts);
        if (!empty($row['agenda_conflicto_pendiente'])) {
            $label = '⚠ ' . $label;
        }
        if ($label === '') {
            $label = 'Turno #' . $id;
        }

        return [
            'id' => $id,
            'name' => $label,
            'meta' => $row,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function formatTurnoListItem(Turno $turno): array
    {
        $conflicto = TurnoAgendaConflicto::findPendientePorTurno((int) $turno->id_turnos);
        $profPersona = $turno->getProfesionalPersonaParaDisplay();
        $profesional = $profPersona
            ? $profPersona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N_D)
            : null;

        return [
            'id' => (int) $turno->id_turnos,
            'fecha' => $turno->fecha,
            'hora' => $turno->hora,
            'servicio' => $turno->getNombreServicioParaDisplay(),
            'profesional' => $profesional,
            'id_profesional_efector_servicio' => (int) ($turno->id_profesional_efector_servicio ?? 0) ?: null,
            'agenda_conflicto_pendiente' => $conflicto !== null,
            'agenda_conflicto' => $conflicto !== null ? $conflicto->toPacienteApiArray() : null,
        ];
    }
}
