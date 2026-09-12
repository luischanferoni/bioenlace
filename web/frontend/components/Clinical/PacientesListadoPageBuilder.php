<?php

namespace frontend\components\Clinical;

use common\components\Domain\Clinical\Emergency\Service\GuardiaBoardCapabilityService;
use common\models\Clinical\Encounter;
use common\models\Organization\Servicio;
use yii\helpers\Url;

/**
 * View model del panel inicio / listado pacientes ({@see SiteController::renderPanelInicio}).
 */
final class PacientesListadoPageBuilder
{
    private const ENCOUNTER_LABELS = [
        Encounter::ENCOUNTER_CLASS_AMB => 'Ambulatorio',
        Encounter::ENCOUNTER_CLASS_IMP => 'Internación',
        Encounter::ENCOUNTER_CLASS_EMER => 'Guardia',
        Encounter::ENCOUNTER_CLASS_VR => 'Virtual',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function build(string $fecha, ?string $encounterClass, int $idServicioActual, bool $esImpPiso): array
    {
        $ec = (string) $encounterClass;
        $esAmbulatorio = $ec === Encounter::ENCOUNTER_CLASS_AMB;
        $esVirtual = $ec === Encounter::ENCOUNTER_CLASS_VR;
        $esGuardia = $ec === Encounter::ENCOUNTER_CLASS_EMER;
        $esImpQuirurgico = $ec === Encounter::ENCOUNTER_CLASS_IMP
            && $idServicioActual > 0
            && Servicio::esServicioAgendaQuirurgica($idServicioActual);
        $esPacienteHome = $ec === '';

        $guardiaCaps = new GuardiaBoardCapabilityService();
        $puedeTriageGuardia = $esGuardia && $guardiaCaps->canTriage();
        $puedeIngresarGuardia = $esGuardia && $guardiaCaps->canIngresar();
        $puedeIngresarDniGuardia = $puedeIngresarGuardia && $guardiaCaps->canIngresarConDni();
        $puedeAtenderGuardia = $esGuardia && $guardiaCaps->canAtender();
        $puedeDocumentarGuardia = $esGuardia && $guardiaCaps->canDocumentar();

        $pageTitle = $esGuardia
            ? 'Tablero de guardia'
            : ($esVirtual
                ? 'Consultas clínicas por mensaje'
                : ($esPacienteHome ? 'Inicio' : 'Pacientes'));

        $fechaTs = strtotime($fecha) ?: time();

        return [
            'fecha' => $fecha,
            'fechaAnterior' => date('Y-m-d', strtotime('-1 day', $fechaTs)),
            'fechaSiguiente' => date('Y-m-d', strtotime('+1 day', $fechaTs)),
            'hoy' => date('Y-m-d'),
            'encounterClass' => $ec,
            'encounterLabel' => self::ENCOUNTER_LABELS[$ec] ?? '',
            'pageTitle' => $pageTitle,
            'esAmbulatorio' => $esAmbulatorio,
            'esVirtual' => $esVirtual,
            'esGuardia' => $esGuardia,
            'esImpQuirurgico' => $esImpQuirurgico,
            'esImpPiso' => $esImpPiso,
            'esPacienteHome' => $esPacienteHome,
            'mostrarNavFechas' => $esAmbulatorio || $esImpQuirurgico,
            'puedeTriageGuardia' => $puedeTriageGuardia,
            'puedeIngresarGuardia' => $puedeIngresarGuardia,
            'puedeIngresarDniGuardia' => $puedeIngresarDniGuardia,
            'puedeAtenderGuardia' => $puedeAtenderGuardia,
            'puedeDocumentarGuardia' => $puedeDocumentarGuardia,
            'registerGuardiaAssets' => $esGuardia,
            'loadingMessage' => $esPacienteHome ? 'Cargando tu panel…' : 'Cargando listado de pacientes…',
            'urls' => [
                'historia' => Url::to(['/paciente/historia'], true),
                'verConsulta' => Url::to(['/paciente/ver-consulta'], true),
                'asistente' => Url::to(['/site/asistente'], true),
                'fechaAnterior' => Url::to(['site/index', 'fecha' => date('Y-m-d', strtotime('-1 day', $fechaTs))]),
                'fechaHoy' => Url::to(['site/index', 'fecha' => date('Y-m-d')]),
                'fechaSiguiente' => Url::to(['site/index', 'fecha' => date('Y-m-d', strtotime('+1 day', $fechaTs))]),
            ],
            'messages' => [
                'emptyTurnos' => 'No hay pacientes con turno en la fecha seleccionada.',
                'emptyInternados' => 'No hay pacientes internados para mostrar.',
                'emptyGuardias' => 'No hay pacientes en el tablero de guardia.',
                'emptyCirugias' => 'No hay cirugías agendadas para la fecha seleccionada.',
            ],
        ];
    }
}
