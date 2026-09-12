<?php

namespace frontend\components\Clinical;

use common\models\Clinical\Encounter;
use common\models\Person\Persona;
use yii\helpers\Url;

/**
 * View model de la pantalla HC / timeline paciente ({@see PacienteController::actionHistoria}).
 */
final class PacienteHistoriaTimelinePageBuilder
{
    private const GENERO_LABELS = [
        1 => 'Femenino',
        2 => 'Masculino',
        3 => 'Otro',
        4 => 'Indefinido',
    ];

    /**
     * @param array{parent_type?: string, episodio_id?: int, items?: list<array<string, mixed>>}|null $timelineEpisodioFeed
     * @return array{
     *   pageTitle: string,
     *   personaId: int,
     *   edad: int|string|null,
     *   mostrarCurvasCrecimiento: bool,
     *   esContextoInternacion: bool,
     *   esContextoGuardia: bool,
     *   esContextoEpisodio: bool,
     *   mostrarMotivosAmbulatorios: bool,
     *   modoCaptura: string,
     *   parentUpper: string|null,
     *   parentId: int|null,
     *   episodioTipoData: string,
     *   episodioBannerAccent: string,
     *   timelineEpisodioGroups: list<array<string, mixed>>,
     *   timelineEpisodioItemCount: int,
     *   registerGuardiaAssets: bool,
     *   jsConfig: array<string, mixed>
     * }
     */
    public static function build(
        Persona $persona,
        ?array $timelineEpisodioFeed,
        ?string $parent,
        int $parentId,
        string $vistaQuery = ''
    ): array {
        $personaId = (int) $persona->id_persona;
        $parentUpper = strtoupper(trim((string) $parent));
        $esContextoInternacion = $parentUpper === Encounter::PARENT_INTERNACION && $parentId > 0;
        $esContextoGuardia = $parentUpper === Encounter::PARENT_GUARDIA && $parentId > 0;
        $esContextoEpisodio = $esContextoInternacion || $esContextoGuardia;
        $mostrarMotivosAmbulatorios = !$esContextoEpisodio
            && !in_array($parentUpper, [Encounter::PARENT_CIRUGIA, Encounter::PARENT_GENERICO_EMER], true);
        $modoCaptura = $esContextoInternacion ? 'imp' : ($esContextoGuardia ? 'emer' : 'amb');

        $edad = $persona->edad;
        $edadTexto = ($edad !== null && $edad !== '') ? ((int) $edad) . ' años' : 'edad s/d';
        $generoTexto = self::GENERO_LABELS[(int) ($persona->genero ?? 0)] ?? 'Sin datos';
        $barrioTexto = self::barrioTexto($persona);
        $vistaConsultaCargada = strtolower(trim($vistaQuery)) === 'consulta';

        $pageTitle = $vistaConsultaCargada
            ? ('Consulta cargada · ' . $persona->apellido . ', ' . $persona->nombre . ' | ' . $edadTexto)
            : ($persona->nombre . ' ' . $persona->otro_nombre . ', ' . $persona->apellido
                . ' | ' . $edadTexto . ' · ' . $generoTexto . ' · Barrio: ' . $barrioTexto);

        $timelineEpisodioGroups = [];
        $timelineEpisodioItemCount = 0;
        if ($esContextoEpisodio) {
            $timelineEpisodioGroups = EpisodioTimelineViewBuilder::groupsFromFeed($timelineEpisodioFeed);
            $timelineEpisodioItemCount = EpisodioTimelineViewBuilder::itemCount($timelineEpisodioFeed);
        }

        $paths = self::resolvePaths($personaId, $parentUpper, $parentId);
        $mostrarCurvas = $edad !== null && $edad !== '' && (int) $edad < 14;

        $jsConfig = [
            'pacienteId' => $personaId,
            'vistaConsultaCargada' => $vistaConsultaCargada,
            'modoCaptura' => $modoCaptura,
            'parent' => $esContextoEpisodio ? $parentUpper : null,
            'parentId' => $esContextoEpisodio ? $parentId : null,
            'siteIndexUrl' => Url::to(['/site/index']),
            'endpoints' => [
                'curvasCrecimiento' => $mostrarCurvas
                    ? Url::to(['personas/curvas-crecimiento', 'id' => $personaId])
                    : null,
                'formularioConsulta' => Url::to(['paciente/formulario-consulta', 'id' => $personaId]),
                'historiaClinica' => $vistaConsultaCargada ? null : $paths['historiaClinicaPath'],
                'verConsultaComoStaff' => $paths['verConsultaStaffPath'],
                'episodioTimelineHtml' => $paths['episodioTimelineHtmlPath'],
            ],
        ];

        return [
            'pageTitle' => $pageTitle,
            'personaId' => $personaId,
            'edad' => $edad,
            'mostrarCurvasCrecimiento' => $mostrarCurvas,
            'esContextoInternacion' => $esContextoInternacion,
            'esContextoGuardia' => $esContextoGuardia,
            'esContextoEpisodio' => $esContextoEpisodio,
            'mostrarMotivosAmbulatorios' => $mostrarMotivosAmbulatorios,
            'modoCaptura' => $modoCaptura,
            'parentUpper' => $esContextoEpisodio ? $parentUpper : null,
            'parentId' => $esContextoEpisodio ? $parentId : null,
            'episodioTipoData' => $esContextoGuardia ? 'GUARDIA' : 'INTERNACION',
            'episodioBannerAccent' => $esContextoGuardia
                ? 'border-danger bg-danger-subtle'
                : 'border-primary bg-primary-subtle',
            'timelineEpisodioGroups' => $timelineEpisodioGroups,
            'timelineEpisodioItemCount' => $timelineEpisodioItemCount,
            'registerGuardiaAssets' => $esContextoGuardia,
            'jsConfig' => $jsConfig,
        ];
    }

    private static function barrioTexto(Persona $persona): string
    {
        $domicilio = $persona->domicilioActivo;
        if (!is_object($domicilio)) {
            return 'Sin datos';
        }
        if (is_object($domicilio->modelBarrio) && !empty($domicilio->modelBarrio->nombre)) {
            return (string) $domicilio->modelBarrio->nombre;
        }
        if (!empty($domicilio->barrio)) {
            return (string) $domicilio->barrio;
        }

        return 'Sin datos';
    }

    /**
     * @return array{historiaClinicaPath: string, verConsultaStaffPath: string|null, episodioTimelineHtmlPath: string|null}
     */
    private static function resolvePaths(int $personaId, string $parentUpper, int $parentId): array
    {
        $historiaClinicaQs = [];
        $verConsultaStaffPath = null;
        $episodioTimelineHtmlPath = null;

        if ($parentUpper === Encounter::PARENT_TURNO && $parentId > 0) {
            $historiaClinicaQs['turno_id'] = $parentId;
            $verConsultaStaffPath = '/api/v1/clinical/encounter/ver-consulta-como-staff?'
                . http_build_query(['turno_id' => $parentId]);
        } elseif ($parentUpper === Encounter::PARENT_INTERNACION && $parentId > 0) {
            $historiaClinicaQs['parent'] = Encounter::PARENT_INTERNACION;
            $historiaClinicaQs['parent_id'] = $parentId;
            $episodioTimelineHtmlPath = Url::to([
                '/paciente/episodio-timeline-html',
                'id' => $personaId,
                'parent' => Encounter::PARENT_INTERNACION,
                'parent_id' => $parentId,
            ]);
        } elseif ($parentUpper === Encounter::PARENT_GUARDIA && $parentId > 0) {
            $historiaClinicaQs['parent'] = Encounter::PARENT_GUARDIA;
            $historiaClinicaQs['parent_id'] = $parentId;
            $episodioTimelineHtmlPath = Url::to([
                '/paciente/episodio-timeline-html',
                'id' => $personaId,
                'parent' => Encounter::PARENT_GUARDIA,
                'parent_id' => $parentId,
            ]);
        }

        $historiaClinicaPath = '/api/v1/personas/' . $personaId . '/historia-clinica';
        if ($historiaClinicaQs !== []) {
            $historiaClinicaPath .= '?' . http_build_query($historiaClinicaQs);
        }

        return [
            'historiaClinicaPath' => $historiaClinicaPath,
            'verConsultaStaffPath' => $verConsultaStaffPath,
            'episodioTimelineHtmlPath' => $episodioTimelineHtmlPath,
        ];
    }
}
