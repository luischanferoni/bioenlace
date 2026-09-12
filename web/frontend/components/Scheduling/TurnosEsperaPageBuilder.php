<?php

namespace frontend\components\Scheduling;

use common\models\Organization\ProfesionalEfectorServicio;
use common\models\Person\Persona;
use Yii;
use yii\helpers\Url;

/**
 * View model de lista de espera ({@see TurnosController::actionEspera}).
 */
final class TurnosEsperaPageBuilder
{
    /**
     * @param list<\common\models\Scheduling\Turno> $turnos
     * @return array<string, mixed>
     */
    public static function build(string $fecha, $profesional, array $turnos): array
    {
        $tieneProfesional = $profesional instanceof ProfesionalEfectorServicio;
        $pesId = $tieneProfesional ? (int) $profesional->id : null;
        $fechaLabel = date('d-m-Y', strtotime($fecha) ?: time());
        $hoy = date('Y-m-d');

        $fechaAnterior = date('Y-m-d', strtotime($fecha . ' -1 day') ?: time());
        $fechaSiguiente = date('Y-m-d', strtotime($fecha . ' +1 day') ?: time());

        $urlAnteriorParams = ['turnos/espera', 'fecha' => $fechaAnterior];
        $urlSiguienteParams = ['/turnos/espera', 'fecha' => $fechaSiguiente];
        if ($pesId !== null) {
            $urlAnteriorParams['pes'] = $pesId;
            $urlSiguienteParams['pes'] = $pesId;
        }

        $cards = [];
        $i = 1;
        foreach ($turnos as $turno) {
            $edad = null;
            if (!empty($turno->paciente->fecha_nacimiento)) {
                try {
                    $edad = (int) $turno->paciente->getEdad();
                } catch (\Throwable $e) {
                    $edad = null;
                }
            }
            $cards[] = [
                'orden' => $i,
                'idTurnos' => (int) $turno->id_turnos,
                'hora' => substr((string) $turno->hora, 0, 5),
                'nombrePaciente' => $turno->paciente->apellido . ', ' . $turno->paciente->nombre,
                'edad' => $edad,
                'esReferencia' => (int) $turno->id_consulta_referencia !== 0,
                'confirmado' => $turno->confirmado && $turno->confirmado === 'SI',
                'programado' => (int) $turno->programado !== 0,
                'nHistoriaClinica' => $tieneProfesional
                    ? $turno->paciente->obtenerNHistoriaClinica(Yii::$app->user->getIdEfector())
                    : null,
            ];
            $i++;
        }

        $pageTitle = 'Lista de Espera';
        if ($tieneProfesional) {
            $personaPes = $profesional->persona;
            if ($personaPes instanceof Persona) {
                $pageTitle .= ' para ' . $personaPes->getNombreCompleto(Persona::FORMATO_NOMBRE_A_OA_N_ON);
            }
        }
        $pageTitle .= ' del ' . $fechaLabel;

        return [
            'pageTitle' => $pageTitle,
            'fecha' => $fecha,
            'fechaLabel' => $fechaLabel,
            'tieneProfesional' => $tieneProfesional,
            'pesId' => $pesId,
            'nombreEfector' => (string) Yii::$app->user->getNombreEfector(),
            'urlFechaAnterior' => Url::toRoute($urlAnteriorParams),
            'urlFechaSiguiente' => Url::toRoute($urlSiguienteParams),
            'mostrarFechaSiguiente' => $fechaSiguiente <= $hoy,
            'cards' => $cards,
            'emptyMessage' => count($turnos) === 0
                ? (Yii::$app->request->get('fecha') !== null && Yii::$app->request->get('fecha') !== ''
                    ? ('No existen turnos para la fecha ' . $fecha . '.')
                    : 'No existen turnos pendientes.')
                : null,
            'refreshSeconds' => 120,
            'logoMinisterioUrl' => Yii::getAlias('@web') . '/images/logo_ministerio_salud.png',
            'logoSmallUrl' => Yii::getAlias('@web') . '/images/logo_small.png',
        ];
    }
}
