<?php

namespace frontend\components\Scheduling;

use common\models\Person\Persona;
use common\models\Scheduling\AgendaFeriados;
use Yii;

/**
 * View model del partial calendario de turnos ({@see views/turnos/_calendario.php}).
 */
final class TurnosCalendarioPageBuilder
{
    /**
     * @param list<mixed>|array $feriados
     * @return array{
     *   hoy: string,
     *   days: list<array{date: string, weekday: string, dayNum: string, month: string, bgClass: string, isToday: bool}>,
     *   jsConfig: array<string, mixed>
     * }
     */
    public static function build(?Persona $persona, $feriados): array
    {
        $hoy = (new \DateTime())->format('Y-m-d');
        $period = new \DatePeriod(
            new \DateTime('NOW -60 day'),
            new \DateInterval('P1D'),
            new \DateTime('NOW +90 day')
        );

        $formatter = Yii::$app->formatter;
        $days = [];
        foreach ($period as $value) {
            /** @var \DateTimeInterface $value */
            $date = $value->format('Y-m-d');
            $isToday = $date === $hoy;
            $weekdayShort = $formatter->asDate($value, 'EEE');
            $bgClass = 'bg-soft-secondary';
            if ($weekdayShort === 'sáb.' || $weekdayShort === 'dom.') {
                $bgClass = 'bg-soft-dark';
            } elseif ($isToday) {
                $bgClass = 'bg-soft-info';
            }
            if (AgendaFeriados::esFeriado($date, $feriados)) {
                $bgClass = 'bg-soft-danger';
            }

            $days[] = [
                'date' => $date,
                'weekday' => ucfirst((string) $weekdayShort),
                'dayNum' => (string) $formatter->asDate($value, 'dd'),
                'month' => ucwords((string) $formatter->asDate($value, 'MMMM')),
                'bgClass' => $bgClass,
                'isToday' => $isToday,
            ];
        }

        $idPes = Yii::$app->user->getIdProfesionalEfectorServicio();

        return [
            'hoy' => $hoy,
            'days' => $days,
            'jsConfig' => [
                'urlEventos' => '/api/v1/turnos/calendario-ocupacion-dia',
                'urlCreate' => '/api/v1/turnos/para-paciente',
                'urlCrearSobreturno' => '/api/v1/turnos/crear-sobreturno',
                'urlCancelarOperativoBase' => '/api/v1/turnos',
                'idEfector' => (int) Yii::$app->user->getIdEfector(),
                'idPersona' => $persona !== null ? (int) $persona->id_persona : 0,
                'idServicio' => 0,
                'pesSlotId' => 0,
                'idProfesionalEfectorServicio' => ($idPes !== null && $idPes !== '') ? (int) $idPes : 0,
            ],
        ];
    }
}
