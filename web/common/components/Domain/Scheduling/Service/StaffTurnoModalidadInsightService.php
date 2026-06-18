<?php

namespace common\components\Domain\Scheduling\Service;

use common\models\ProfesionalEfectorServicioAgenda;
use common\models\Scheduling\Turno;

/**
 * Insight educativo (etapa 0): turnos presenciales que, según triage, podrían haberse atendido de forma remota.
 */
final class StaffTurnoModalidadInsightService
{
    public function __construct(
        private readonly TurnoReservaTriageDraftBuilder $draftBuilder = new TurnoReservaTriageDraftBuilder(),
        private readonly TeleconsultaElegibilidadService $elegibilidadService = new TeleconsultaElegibilidadService(),
        private readonly StaffModalidadInsightCatalogService $catalog = new StaffModalidadInsightCatalogService(),
    ) {
    }

    /**
     * @return array<string, mixed>|null payload para API/UI; null si no corresponde mostrar insight
     */
    public function insightParaTurno(Turno $turno): ?array
    {
        if ((string) ($turno->tipo_atencion ?? Turno::TIPO_ATENCION_PRESENCIAL) !== Turno::TIPO_ATENCION_PRESENCIAL) {
            return null;
        }

        if (!$this->draftBuilder->tieneTriagePersistido($turno)) {
            return null;
        }

        $draft = $this->draftBuilder->buildFromTurno($turno);
        $res = $this->elegibilidadService->resolverParaDraft($draft);
        $elegClinica = (string) ($res['elegibilidad_clinica'] ?? '');

        if (!in_array($elegClinica, $this->catalog->elegibilidadesConInsight(), true)) {
            return null;
        }

        $mensaje = $this->catalog->mensajeParaElegibilidad($elegClinica);
        $modalidades = $this->catalog->modalidadesParaElegibilidad($elegClinica);
        if ($mensaje === null || $modalidades === []) {
            return null;
        }

        $idPes = (int) ($turno->id_profesional_efector_servicio ?? 0);
        $agendaOnline = $idPes > 0 && $this->pesAceptaConsultasOnline($idPes);

        $payload = [
            'elegibilidad_clinica' => $elegClinica,
            'summary' => $mensaje['summary'],
            'tone' => $mensaje['tone'],
            'modalidades' => $modalidades,
            'agenda_online_habilitada' => $agendaOnline,
            'servicio_permite_teleconsulta' => (bool) ($res['ofrecible'] ?? false),
        ];

        if (!$agendaOnline) {
            $footer = $this->catalog->footerAgendaNoOnline();
            if ($footer !== '') {
                $payload['footer'] = $footer;
            }
        }

        return $payload;
    }

    private function pesAceptaConsultasOnline(int $idPes): bool
    {
        $agenda = ProfesionalEfectorServicioAgenda::findActivaPorProfesionalEfectorServicio($idPes);

        return $agenda !== null && (bool) $agenda->acepta_consultas_online;
    }
}
