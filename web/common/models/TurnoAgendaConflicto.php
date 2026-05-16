<?php

namespace common\models;

use yii\db\ActiveRecord;

/**
 * Turno existente desalineado tras un cambio de agenda; el paciente debe elegir horario vecino.
 *
 * @property int $id
 * @property int $id_turno
 * @property int $id_agenda_version
 * @property string $estado pendiente|resuelto_reprogramado|resuelto_cancelado
 * @property string|null $opcion_hora_antes
 * @property string|null $opcion_hora_despues
 * @property string|null $hora_elegida
 * @property string $created_at
 * @property string $updated_at
 */
class TurnoAgendaConflicto extends ActiveRecord
{
    public const ESTADO_PENDIENTE = 'pendiente';
    public const ESTADO_REPROGRAMADO = 'resuelto_reprogramado';
    public const ESTADO_CANCELADO = 'resuelto_cancelado';

    public static function tableName()
    {
        return 'turno_agenda_conflicto';
    }

    public function rules()
    {
        return [
            [['id_turno', 'id_agenda_version', 'estado'], 'required'],
            [['id_turno', 'id_agenda_version'], 'integer'],
            [['opcion_hora_antes', 'opcion_hora_despues', 'hora_elegida'], 'safe'],
            [['estado'], 'in', 'range' => [
                self::ESTADO_PENDIENTE,
                self::ESTADO_REPROGRAMADO,
                self::ESTADO_CANCELADO,
            ]],
        ];
    }

    public function getTurno()
    {
        return $this->hasOne(Turno::class, ['id_turnos' => 'id_turno']);
    }

    public function getAgendaVersion()
    {
        return $this->hasOne(ProfesionalEfectorServicioAgendaVersion::class, ['id' => 'id_agenda_version']);
    }

    public static function findPendientePorTurno(int $idTurno): ?self
    {
        if ($idTurno <= 0) {
            return null;
        }
        /** @var self|null $row */
        $row = static::find()
            ->where([
                'id_turno' => $idTurno,
                'estado' => self::ESTADO_PENDIENTE,
            ])
            ->orderBy(['id' => SORT_DESC])
            ->one();

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    public function toPacienteApiArray(): array
    {
        $antes = $this->opcion_hora_antes !== null ? substr((string) $this->opcion_hora_antes, 0, 5) : null;
        $despues = $this->opcion_hora_despues !== null ? substr((string) $this->opcion_hora_despues, 0, 5) : null;

        return [
            'id' => (int) $this->id,
            'id_turno' => (int) $this->id_turno,
            'opcion_antes' => $antes,
            'opcion_despues' => $despues,
        ];
    }

    public static function existePendienteParaPesEnFranja(int $idPes, string $fecha, string $horaInicio, string $horaFin): bool
    {
        $horaInicio = self::normalizarHora($horaInicio);
        $horaFin = self::normalizarHora($horaFin);
        if ($idPes <= 0 || $fecha === '' || $horaInicio === '' || $horaFin === '') {
            return false;
        }

        $pendientes = static::find()
            ->alias('c')
            ->innerJoin(['t' => Turno::tableName()], 't.id_turnos = c.id_turno')
            ->where([
                'c.estado' => self::ESTADO_PENDIENTE,
                't.id_profesional_efector_servicio' => $idPes,
                't.fecha' => $fecha,
            ])
            ->all();

        foreach ($pendientes as $c) {
            foreach (self::franjasBloqueadasDeConflicto($c) as $franja) {
                if (self::rangosSeSolapan($horaInicio, $horaFin, $franja['inicio'], $franja['fin'])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return list<array{inicio: string, fin: string}>
     */
    public static function franjasBloqueadasDeConflicto(self $c): array
    {
        $turno = $c->turno;
        if ($turno === null) {
            return [];
        }
        $version = $c->agendaVersion;
        $intervalo = $version !== null ? $version->getIntervaloMinutosEfectivo() : 15;
        $out = [];
        if ($c->opcion_hora_antes !== null && trim((string) $c->opcion_hora_antes) !== '') {
            $h = self::normalizarHora((string) $c->opcion_hora_antes);
            $out[] = ['inicio' => $h, 'fin' => self::sumarMinutos($h, $intervalo)];
        }
        if ($c->opcion_hora_despues !== null && trim((string) $c->opcion_hora_despues) !== '') {
            $h = self::normalizarHora((string) $c->opcion_hora_despues);
            $out[] = ['inicio' => $h, 'fin' => self::sumarMinutos($h, $intervalo)];
        }
        $horaTurno = self::normalizarHora((string) $turno->hora);
        if ($horaTurno !== '') {
            $out[] = ['inicio' => $horaTurno, 'fin' => self::sumarMinutos($horaTurno, $intervalo)];
        }

        return $out;
    }

    public static function normalizarHora(string $hora): string
    {
        $hora = trim($hora);
        if ($hora === '') {
            return '';
        }
        if (strlen($hora) === 5) {
            return $hora . ':00';
        }

        return $hora;
    }

    public static function sumarMinutos(string $hora, int $minutos): string
    {
        $dt = \DateTimeImmutable::createFromFormat('H:i:s', self::normalizarHora($hora));
        if ($dt === false) {
            return $hora;
        }

        return $dt->modify('+' . $minutos . ' minutes')->format('H:i:s');
    }

    private static function rangosSeSolapan(string $a1, string $a2, string $b1, string $b2): bool
    {
        return $a1 < $b2 && $b1 < $a2;
    }
}
