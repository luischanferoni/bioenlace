<?php

namespace common\components\Organization\Service\ProfesionalEfectorServicio;

use common\models\ProfesionalEfectorServicioAgenda;
use common\models\ProfesionalEfectorServicioAgendaVersion;
use common\models\Turno;
use common\components\Scheduling\Service\TurnoResolucionService;
use Yii;
use yii\web\BadRequestHttpException;

/**
 * Alta de versiones de agenda, preview de impacto y conflictos con turnos futuros.
 */
final class ProfesionalEfectorServicioAgendaVersionService
{
    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public static function previewImpacto(int $idPes, int $idEfector, array $post): array
    {
        $vigenteDesde = self::parseVigenteDesde($post);
        $intervaloNuevo = AgendaIntervaloMinutos::normalize((int) ($post['intervalo_minutos'] ?? 0));
        $versionPropuesta = self::buildVersionFromPost($idPes, $idEfector, $post, $vigenteDesde, $intervaloNuevo);

        $versionActual = ProfesionalEfectorServicioAgendaVersion::findVigenteParaPesEnFecha($idPes, $vigenteDesde);
        $intervaloActual = $versionActual !== null
            ? $versionActual->getIntervaloMinutosEfectivo()
            : self::intervaloDesdeAgendaEspejo($idPes);

        $slotsAntes = $versionActual !== null
            ? AgendaSlotEngine::estimarSlotsPorDiaPromedio($versionActual, $intervaloActual)
            : 0;
        $slotsDespues = AgendaSlotEngine::estimarSlotsPorDiaPromedio($versionPropuesta, $intervaloNuevo);

        $turnosFuturos = self::turnosPendientesFuturos($idPes, $vigenteDesde);
        $alineados = 0;
        $conflictos = [];
        foreach ($turnosFuturos as $turno) {
            $fecha = (string) $turno->fecha;
            $hora = substr((string) $turno->hora, 0, 5);
            if (AgendaSlotEngine::horaEstaEnGrilla($versionPropuesta, $fecha, $hora, $intervaloNuevo)) {
                $alineados++;
                continue;
            }
            $vec = AgendaSlotEngine::vecinosEnGrilla($versionPropuesta, $fecha, $hora, $intervaloNuevo);
            $conflictos[] = [
                'id_turno' => (int) $turno->id_turnos,
                'fecha' => $fecha,
                'hora_actual' => $hora,
                'opcion_antes' => $vec['antes'],
                'opcion_despues' => $vec['despues'],
            ];
        }

        $cambiosAnio = self::contarCambiosIntervaloEnAnio($idPes, (int) date('Y', strtotime($vigenteDesde)));
        $cambioIntervalo = $intervaloActual !== $intervaloNuevo;

        return [
            'vigente_desde' => $vigenteDesde,
            'intervalo_actual' => $intervaloActual,
            'intervalo_nuevo' => $intervaloNuevo,
            'slots_por_dia_antes' => $slotsAntes,
            'slots_por_dia_despues' => $slotsDespues,
            'turnos_futuros_total' => count($turnosFuturos),
            'turnos_alineados' => $alineados,
            'turnos_en_conflicto' => count($conflictos),
            'conflictos' => $conflictos,
            'cambios_intervalo_este_anio' => $cambiosAnio,
            'cambios_intervalo_max_por_anio' => AgendaIntervaloMinutos::MAX_CAMBIOS_INTERVALO_POR_ANIO,
            'requiere_confirmacion' => count($conflictos) > 0 || $cambioIntervalo,
            'mensaje' => self::buildMensajePreview($intervaloActual, $intervaloNuevo, $slotsAntes, $slotsDespues, count($conflictos)),
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array{message: string, agenda_ui_completed: string, id_agenda_version: int, preview?: array<string, mixed>}
     */
    public static function publicarVersion(int $idPes, int $idEfector, array $post): array
    {
        $preview = self::previewImpacto($idPes, $idEfector, $post);
        $vigenteDesde = (string) $preview['vigente_desde'];
        $intervaloNuevo = (int) $preview['intervalo_nuevo'];
        $confirmar = !empty($post['confirmar_cambios']) && (string) $post['confirmar_cambios'] !== '0';

        if (AgendaConfigImpactProfile::isModalityOnlySubmit($post)) {
            $confirmar = true;
        }

        $cambioIntervalo = (int) $preview['intervalo_actual'] !== $intervaloNuevo;
        if ($cambioIntervalo) {
            $cambiosAnio = (int) $preview['cambios_intervalo_este_anio'];
            if ($cambiosAnio >= AgendaIntervaloMinutos::MAX_CAMBIOS_INTERVALO_POR_ANIO) {
                throw new BadRequestHttpException(
                    'Ya alcanzaste el máximo de ' . AgendaIntervaloMinutos::MAX_CAMBIOS_INTERVALO_POR_ANIO
                    . ' cambios de intervalo por año para esta agenda.'
                );
            }
        }

        $needsConfirm = AgendaConfigImpactProfile::previewRequiresUserConfirmation($preview, $post);
        if ($needsConfirm && !$confirmar) {
            throw new BadRequestHttpException(
                'Confirme el cambio de agenda tras revisar el impacto en turnos futuros.'
            );
        }

        if ((int) $preview['turnos_en_conflicto'] > 0 && !$confirmar && $needsConfirm) {
            throw new BadRequestHttpException(
                'Hay turnos que no encajan en la nueva grilla. Revise el impacto y confirme el cambio.'
            );
        }

        $version = self::buildVersionFromPost($idPes, $idEfector, $post, $vigenteDesde, $intervaloNuevo);
        if (!$version->validate()) {
            throw new BadRequestHttpException(implode(' ', $version->getFirstErrors()));
        }
        if (!$version->save(false)) {
            throw new \RuntimeException('No se pudo guardar la versión de agenda.');
        }

        self::sincronizarAgendaEspejo($idPes, $idEfector, $version);
        TurnoResolucionService::crearDesdeCambioAgenda((int) $version->id, $preview['conflictos'] ?? []);

        return [
            'message' => 'Agenda guardada. Vigente desde ' . $vigenteDesde . '.',
            'agenda_ui_completed' => '1',
            'id_agenda_version' => (int) $version->id,
            'preview' => $preview,
        ];
    }

    private static function sincronizarAgendaEspejo(int $idPes, int $idEfector, ProfesionalEfectorServicioAgendaVersion $version): void
    {
        $agenda = ProfesionalEfectorServicioAgenda::findActivaPorProfesionalEfectorServicio($idPes);
        if ($agenda === null) {
            $agenda = new ProfesionalEfectorServicioAgenda();
            $agenda->id_profesional_efector_servicio = $idPes;
            $agenda->id_efector = $idEfector;
        }
        $agenda->formas_atencion = $version->formas_atencion;
        $agenda->intervalo_minutos = $version->getIntervaloMinutosEfectivo();
        $agenda->duracion_slot_minutos = null;
        $agenda->acepta_consultas_online = (bool) $version->acepta_consultas_online;
        foreach (['lunes_2', 'martes_2', 'miercoles_2', 'jueves_2', 'viernes_2', 'sabado_2', 'domingo_2'] as $col) {
            $agenda->{$col} = $version->{$col};
        }
        $agenda->save(false);
    }

    /**
     * @param array<string, mixed> $post
     */
    private static function buildVersionFromPost(
        int $idPes,
        int $idEfector,
        array $post,
        string $vigenteDesde,
        int $intervaloMinutos
    ): ProfesionalEfectorServicioAgendaVersion {
        $version = new ProfesionalEfectorServicioAgendaVersion();
        $version->id_profesional_efector_servicio = $idPes;
        $version->id_efector = $idEfector;
        $version->vigente_desde = $vigenteDesde;
        $version->intervalo_minutos = $intervaloMinutos;
        $version->load($post, '');
        $version->intervalo_minutos = $intervaloMinutos;
        $version->id_profesional_efector_servicio = $idPes;
        $version->id_efector = $idEfector;
        $version->vigente_desde = $vigenteDesde;

        return $version;
    }

    /**
     * @param array<string, mixed> $post
     */
    private static function parseVigenteDesde(array $post): string
    {
        $raw = trim((string) ($post['vigente_desde'] ?? ''));
        if ($raw === '') {
            $raw = date('Y-m-d', strtotime('+1 day'));
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            throw new BadRequestHttpException('vigente_desde inválido.');
        }
        $ymd = date('Y-m-d', $ts);
        $hoy = date('Y-m-d');
        if ($ymd < $hoy) {
            throw new BadRequestHttpException('vigente_desde no puede ser anterior a hoy.');
        }

        return $ymd;
    }

    /**
     * @return Turno[]
     */
    private static function turnosPendientesFuturos(int $idPes, string $desdeYmd): array
    {
        return Turno::find()
            ->where([
                'id_profesional_efector_servicio' => $idPes,
                'estado' => [Turno::ESTADO_PENDIENTE, Turno::ESTADO_EN_RESOLUCION],
            ])
            ->andWhere(['>=', 'fecha', $desdeYmd])
            ->orderBy(['fecha' => SORT_ASC, 'hora' => SORT_ASC])
            ->all();
    }

    private static function contarCambiosIntervaloEnAnio(int $idPes, int $anio): int
    {
        $desde = sprintf('%04d-01-01', $anio);
        $hasta = sprintf('%04d-12-31', $anio);
        $versions = ProfesionalEfectorServicioAgendaVersion::find()
            ->where(['id_profesional_efector_servicio' => $idPes])
            ->andWhere(['between', 'vigente_desde', $desde, $hasta])
            ->orderBy(['vigente_desde' => SORT_ASC])
            ->all();
        if (count($versions) < 2) {
            return 0;
        }
        $cambios = 0;
        $prev = null;
        foreach ($versions as $v) {
            $int = $v->getIntervaloMinutosEfectivo();
            if ($prev !== null && $prev !== $int) {
                $cambios++;
            }
            $prev = $int;
        }

        return $cambios;
    }

    private static function intervaloDesdeAgendaEspejo(int $idPes): int
    {
        $agenda = ProfesionalEfectorServicioAgenda::findActivaPorProfesionalEfectorServicio($idPes);
        if ($agenda === null) {
            return AgendaIntervaloMinutos::DEFAULT;
        }
        if (isset($agenda->intervalo_minutos) && (int) $agenda->intervalo_minutos > 0) {
            return AgendaIntervaloMinutos::normalize((int) $agenda->intervalo_minutos);
        }
        $dur = isset($agenda->duracion_slot_minutos) ? (int) $agenda->duracion_slot_minutos : 0;
        if (AgendaIntervaloMinutos::isAllowed($dur)) {
            return $dur;
        }

        return AgendaIntervaloMinutos::DEFAULT;
    }

    private static function buildMensajePreview(
        int $intervaloActual,
        int $intervaloNuevo,
        int $slotsAntes,
        int $slotsDespues,
        int $conflictos
    ): string {
        $msg = sprintf(
            'Intervalo: %d → %d min. Aprox. %d → %d turnos por día con horarios cargados.',
            $intervaloActual,
            $intervaloNuevo,
            $slotsAntes,
            $slotsDespues
        );
        if ($conflictos > 0) {
            $msg .= ' ' . $conflictos . ' turno(s) futuro(s) requerirán reprogramación o cancelación.';
        }

        return $msg;
    }

}
