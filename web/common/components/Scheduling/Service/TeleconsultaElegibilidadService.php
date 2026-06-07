<?php

namespace common\components\Scheduling\Service;

use common\components\Organization\Service\Servicios\ServiciosEfectorAutogestionListadoService;
use common\models\ConsultaDerivaciones;
use common\models\Scheduling\Turno;
use common\models\Servicio;
use common\models\ReservaTriageTeleconsultaElegibilidad;
use common\models\ServicioTeleconsultaCaso;

/**
 * Reglas simples de elegibilidad de teleconsulta al reservar turno (triage + servicio).
 *
 * La reserva no diagnostica: filtra lo obvio; el profesional decide en la consulta.
 */
final class TeleconsultaElegibilidadService
{
    public const POLITICA_NINGUNA = 'ninguna';
    public const POLITICA_TODAS = 'todas';
    public const POLITICA_ALGUNAS = 'algunas';

    public const ELEG_EXCLUIDO = 'excluido';
    public const ELEG_PRESENCIAL_PREFERIDO = 'presencial_preferido';
    public const ELEG_PERMITIDO = 'permitido';
    public const ELEG_SUGERIDO = 'sugerido';

    /**
     * @param array<string, mixed> $draft
     * @return array{
     *   ofrecible: bool,
     *   tipo_atencion_forzado: string|null,
     *   sugerencia: string|null,
     *   motivo: string,
     *   elegibilidad_clinica: string
     * }
     */
    public function resolverParaDraft(array $draft): array
    {
        $catalog = new ReservaTurnoTriageCatalogService();
        $compiled = $catalog->compileSelections($draft);
        $elegClinica = $this->resolverElegibilidadClinica($draft, $compiled);

        if ($elegClinica === self::ELEG_EXCLUIDO || $elegClinica === self::ELEG_PRESENCIAL_PREFERIDO) {
            return [
                'ofrecible' => false,
                'tipo_atencion_forzado' => Turno::TIPO_ATENCION_PRESENCIAL,
                'sugerencia' => null,
                'motivo' => 'caso_clinico',
                'elegibilidad_clinica' => $elegClinica,
            ];
        }

        $idServicio = (int) ($draft['id_servicio_asignado'] ?? 0);
        if ($idServicio <= 0) {
            $hubIds = (new ReservaTriageServicioRolResolver())->idsServiciosHubParaDraft($draft);
            if ($hubIds !== []) {
                $idServicio = (int) $hubIds[0];
            }
        }
        if ($idServicio <= 0) {
            return [
                'ofrecible' => false,
                'tipo_atencion_forzado' => Turno::TIPO_ATENCION_PRESENCIAL,
                'sugerencia' => $this->sugerenciaDesdeElegibilidad($elegClinica, $compiled),
                'motivo' => 'sin_servicio',
                'elegibilidad_clinica' => $elegClinica,
            ];
        }

        $accesoHub = $this->resolverAccesoHubEspecialista($idServicio, $draft, $elegClinica);
        if ($accesoHub !== null) {
            return $accesoHub;
        }

        if (!$this->servicioPermiteTeleconsulta($idServicio, $draft)) {
            return [
                'ofrecible' => false,
                'tipo_atencion_forzado' => Turno::TIPO_ATENCION_PRESENCIAL,
                'sugerencia' => null,
                'motivo' => 'servicio_no_permite',
                'elegibilidad_clinica' => $elegClinica,
            ];
        }

        return [
            'ofrecible' => true,
            'tipo_atencion_forzado' => null,
            'sugerencia' => $this->sugerenciaDesdeElegibilidad($elegClinica, $compiled),
            'motivo' => 'ok',
            'elegibilidad_clinica' => $elegClinica,
        ];
    }

    /**
     * Opciones de modalidad para UI (step=modalidad).
     *
     * Hub paciente: presencial + videollamada siempre que el triage no sea excluido (urgencia).
     * La elegibilidad fina (política servicio, agenda PES) se valida al ofrecer slots y al persistir.
     *
     * @param array<string, mixed> $draft
     * @return list<array{code: string, label: string}>
     */
    public function opcionesModalidadParaDraft(array $draft): array
    {
        $res = $this->resolverParaDraft($draft);
        if ($res['motivo'] === 'derivacion_especialista' && $res['ofrecible']) {
            return [[
                'code' => Turno::TIPO_ATENCION_TELECONSULTA,
                'label' => 'Remoto (videollamada — derivación del clínico)',
            ]];
        }
        $presencial = [
            'code' => Turno::TIPO_ATENCION_PRESENCIAL,
            'label' => 'Presencial (voy al centro de salud)',
        ];
        if (!$this->modalidadTeleconsultaVisibleParaDraft($draft)) {
            return [$presencial];
        }
        $tele = [
            'code' => Turno::TIPO_ATENCION_TELECONSULTA,
            'label' => 'Remoto (videollamada / virtual desde casa)',
        ];

        return [$presencial, $tele];
    }

    /**
     * ¿Mostrar videollamada en el paso modalidad? false solo con triage excluido (urgencia/halt).
     *
     * @param array<string, mixed> $draft
     */
    public function modalidadTeleconsultaVisibleParaDraft(array $draft): bool
    {
        $catalog = new ReservaTurnoTriageCatalogService();
        $compiled = $catalog->compileSelections($draft);
        if (!empty($compiled['reserva_triage_halt'])) {
            return false;
        }
        $elegClinica = $this->resolverElegibilidadClinica($draft, $compiled);

        return $elegClinica !== self::ELEG_EXCLUIDO;
    }

    /**
     * Aplica flags de draft usados por el motor de flows (`teleconsulta_ofrecible`).
     *
     * No escribe `tipo_atencion`: el paso `select_tipo_atencion` es obligatorio tras triage
     * (salvo urgencia); la UI modalidad filtra opciones vía {@see opcionesModalidadParaDraft}.
     *
     * @param array<string, mixed> $draft mutado in-place
     */
    public function aplicarFlagsEnDraft(array &$draft): void
    {
        $draft['teleconsulta_ofrecible'] = $this->modalidadTeleconsultaVisibleParaDraft($draft) ? '1' : '0';
        $res = $this->resolverParaDraft($draft);
        if ($res['sugerencia'] !== null && trim((string) ($draft['tipo_atencion'] ?? '')) === '') {
            $draft['tipo_atencion_sugerido'] = $res['sugerencia'];
        }
    }

    /**
     * @param array<string, mixed> $draft
     * @param array<string, mixed> $compiled
     */
    private function resolverElegibilidadClinica(array $draft, array $compiled): string
    {
        if (!empty($compiled['reserva_triage_halt'])) {
            return self::ELEG_EXCLUIDO;
        }
        $band = trim((string) ($compiled['urgency_band'] ?? 'D'));
        if ($band === 'A') {
            return self::ELEG_EXCLUIDO;
        }
        if ($band === 'B') {
            return self::ELEG_PRESENCIAL_PREFERIDO;
        }

        $eleg = ReservaTriageTeleconsultaElegibilidad::elegibilidadParaCodigos(
            $this->codigosCasoDesdeDraft($draft)
        );
        if ($eleg !== null && in_array($eleg, [
            self::ELEG_EXCLUIDO,
            self::ELEG_PRESENCIAL_PREFERIDO,
            self::ELEG_PERMITIDO,
            self::ELEG_SUGERIDO,
        ], true)) {
            return $eleg;
        }

        return self::ELEG_PERMITIDO;
    }

    /**
     * @param array<string, mixed> $draft
     * @return list<string>
     */
    private function codigosCasoDesdeDraft(array $draft): array
    {
        $ordered = ['triage_zona', 'triage_detalle', 'triage_evolucion', 'triage_alarmas', 'triage_raiz'];
        $out = [];
        foreach ($ordered as $key) {
            $v = trim((string) ($draft[$key] ?? ''));
            if ($v !== '') {
                $out[] = $v;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param array<string, mixed> $draft
     */
    private function servicioPermiteTeleconsulta(int $idServicio, array $draft): bool
    {
        $servicio = Servicio::findOne($idServicio);
        if ($servicio === null) {
            return false;
        }
        $politica = self::normalizarPolitica(
            $servicio->hasAttribute('teleconsulta_politica')
                ? (string) $servicio->teleconsulta_politica
                : self::POLITICA_NINGUNA
        );

        if ($politica === self::POLITICA_NINGUNA) {
            return false;
        }
        if ($politica === self::POLITICA_TODAS) {
            return true;
        }

        $allow = ServicioTeleconsultaCaso::listCodigosPorServicio($idServicio);
        if ($allow === []) {
            return false;
        }
        foreach ($this->codigosCasoDesdeDraft($draft) as $code) {
            if (in_array($code, $allow, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Hub paciente vs especialista con derivación ({@see Servicio::permiteReservaAutogestionPaciente()}).
     *
     * @param array<string, mixed> $draft
     * @return array{
     *   ofrecible: bool,
     *   tipo_atencion_forzado: string|null,
     *   sugerencia: string|null,
     *   motivo: string,
     *   elegibilidad_clinica: string
     * }|null null = seguir reglas estándar (servicio hub / política servicio)
     */
    private function resolverAccesoHubEspecialista(int $idServicio, array $draft, string $elegClinica): ?array
    {
        if (!ReservaTriageAccesoConfig::especialistaSoloTeleconsultaConDerivacion()) {
            return null;
        }

        $servicio = Servicio::findOne($idServicio);
        if ($servicio === null) {
            return null;
        }

        if ($servicio->permiteReservaAutogestionPaciente()) {
            return null;
        }

        if (!$this->draftIndicaDerivacionVigente($draft, $idServicio)) {
            return [
                'ofrecible' => false,
                'tipo_atencion_forzado' => Turno::TIPO_ATENCION_PRESENCIAL,
                'sugerencia' => null,
                'motivo' => 'especialista_requiere_derivacion',
                'elegibilidad_clinica' => $elegClinica,
            ];
        }

        if (!$this->servicioPermiteTeleconsulta($idServicio, $draft)) {
            return [
                'ofrecible' => false,
                'tipo_atencion_forzado' => Turno::TIPO_ATENCION_PRESENCIAL,
                'sugerencia' => null,
                'motivo' => 'servicio_no_permite',
                'elegibilidad_clinica' => $elegClinica,
            ];
        }

        return [
            'ofrecible' => true,
            'tipo_atencion_forzado' => Turno::TIPO_ATENCION_TELECONSULTA,
            'sugerencia' => Turno::TIPO_ATENCION_TELECONSULTA,
            'motivo' => 'derivacion_especialista',
            'elegibilidad_clinica' => $elegClinica,
        ];
    }

    /**
     * @param array<string, mixed> $draft
     */
    private function draftIndicaDerivacionVigente(array $draft, int $idServicio): bool
    {
        if ((int) ($draft['id_derivacion_clinica'] ?? 0) > 0) {
            return true;
        }

        $idPersona = (int) ($draft['id_persona'] ?? 0);
        $idEfector = (int) ($draft['id_efector'] ?? 0);
        if ($idPersona <= 0 || $idEfector <= 0 || $idServicio <= 0) {
            return false;
        }

        $pendientes = ConsultaDerivaciones::getDerivacionesPorPersona(
            $idPersona,
            $idEfector,
            $idServicio,
            ConsultaDerivaciones::ESTADO_EN_ESPERA
        );

        return $pendientes !== [];
    }

    private static function normalizarPolitica(string $raw): string
    {
        $p = strtolower(trim($raw));
        if (in_array($p, [self::POLITICA_NINGUNA, self::POLITICA_TODAS, self::POLITICA_ALGUNAS], true)) {
            return $p;
        }

        return self::POLITICA_NINGUNA;
    }

    /**
     * @param array<string, mixed> $compiled
     */
    private function sugerenciaDesdeElegibilidad(string $elegClinica, array $compiled): ?string
    {
        if ($elegClinica === self::ELEG_SUGERIDO) {
            return Turno::TIPO_ATENCION_TELECONSULTA;
        }
        $suggest = isset($compiled['suggests_tipo_atencion'])
            ? trim((string) $compiled['suggests_tipo_atencion'])
            : '';
        if ($suggest === Turno::TIPO_ATENCION_TELECONSULTA) {
            return Turno::TIPO_ATENCION_TELECONSULTA;
        }

        return null;
    }
}
