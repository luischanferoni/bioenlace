<?php

namespace common\components\Scheduling\Service;

use common\components\Organization\Service\Servicios\ServicioMencionLookupService;
use common\components\Organization\Service\Servicios\ServiciosEfectorAutogestionListadoService;
use common\models\ConsultaDerivaciones;
use common\models\Scheduling\Turno;
use common\models\Servicio;
use Yii;

/**
 * Resuelve rol/servicios sugeridos a partir del draft de triage y filtra listados de autogestión.
 *
 * Presencial: todos los servicios con agenda; triage prioriza sugeridos.
 * Teleconsulta / hub: clínico generalista; especialista remoto con derivación.
 */
final class ReservaTriageServicioSugeridoService
{
    private ReservaTriageServicioRolResolver $rolResolver;

    public function __construct(?ReservaTriageServicioRolResolver $rolResolver = null)
    {
        $this->rolResolver = $rolResolver ?? new ReservaTriageServicioRolResolver();
    }

    /**
     * @param array<string, mixed> $draft
     * @return array{
     *   rol: string,
     *   rol_label: string,
     *   id_servicios: list<int>,
     *   filtrado_aplicado: bool,
     *   autogestion_disponible: bool,
     *   triage_codigo_resolutor: string,
     *   mensaje_orientacion: string|null,
     *   mensaje_lista: string|null
     * }
     */
    public function resolverParaDraft(array $draft, bool $soloHubPaciente = false): array
    {
        if ($soloHubPaciente) {
            return $this->resolverSoloHub($draft);
        }

        if ($this->esSeguimientoConCarePlan($draft)) {
            return $this->resolverDesdeCarePlan($draft);
        }

        if (!$this->draftTieneTriageRelevante($draft)) {
            return $this->resolverSinTriage();
        }

        $res = $this->rolResolver->resolveDesdeDraft($draft);

        return [
            'rol' => $res->rol_ideal,
            'rol_label' => $res->rol_ideal_label,
            'id_servicios' => $res->id_servicios_reservables,
            'filtrado_aplicado' => true,
            'autogestion_disponible' => $res->autogestion_disponible,
            'triage_codigo_resolutor' => $res->triage_codigo_resolutor,
            'mensaje_orientacion' => $res->mensaje_orientacion,
            'mensaje_lista' => $res->mensaje_lista,
        ];
    }

    /**
     * @param list<array{id: string, name: string}> $items
     * @return list<array{id: string, name: string}>
     */
    public function filtrarItemsHubPaciente(array $items): array
    {
        return $this->filtrarItemsPorIds($items, (new ReservaTriageServicioRolResolver())->idsServiciosHub());
    }

    /**
     * @param list<array{id: string, name: string}> $items
     * @param array<string, mixed> $draft
     * @return list<array{id: string, name: string}>
     */
    public function filtrarItemsUiJson(array $items, array $draft, bool $soloHubPaciente = false): array
    {
        if ($items === []) {
            return $items;
        }
        if (!$soloHubPaciente && !$this->draftTieneTriageRelevante($draft)) {
            return $items;
        }

        $res = $this->resolverParaDraft($draft, $soloHubPaciente);
        if ($res['id_servicios'] === []) {
            return [];
        }

        return $this->filtrarItemsPorIds($items, $res['id_servicios']);
    }

    /**
     * Presencial: muestra todos los servicios; los sugeridos por triage van primero.
     *
     * @param list<array{id: string, name: string}> $items
     * @param array<string, mixed> $draft
     * @return list<array{id: string, name: string}>
     */
    public function priorizarItemsSegunTriage(array $items, array $draft): array
    {
        if ($items === [] || !$this->draftTieneTriageRelevante($draft)) {
            return $items;
        }

        $codigos = (new ReservaTriageServicioRolResolver())->codigosTriagePublicosDesdeDraft($draft);
        $prioridad = \common\models\ReservaTriageCodigoServicio::idsParaCodigos($codigos);
        if ($prioridad === []) {
            return $items;
        }
        $prioFlip = array_flip($prioridad);

        usort($items, static function (array $a, array $b) use ($prioFlip): int {
            $ia = (int) ($a['id'] ?? 0);
            $ib = (int) ($b['id'] ?? 0);
            $pa = isset($prioFlip[$ia]) ? 0 : 1;
            $pb = isset($prioFlip[$ib]) ? 0 : 1;
            if ($pa !== $pb) {
                return $pa <=> $pb;
            }

            return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        return $items;
    }

    /**
     * @param array<string, mixed> $draft
     */
    public function mensajeIntroPresencialParaDraft(array $draft): ?string
    {
        if (!$this->draftTieneTriageRelevante($draft)) {
            return 'Elegí el servicio con el que querés atenderte.';
        }
        $res = $this->resolverParaDraft($draft, false);
        $label = trim((string) ($res['rol_label'] ?? ''));
        if ($label === '') {
            return 'Elegí el servicio con el que querés atenderte.';
        }

        return 'Según lo que indicaste, podría corresponderte ' . $label
            . '. Podés elegir cualquier servicio con turnos disponibles.';
    }

    public static function esListadoPresencial(array $params): bool
    {
        return trim((string) ($params['tipo_atencion'] ?? '')) === Turno::TIPO_ATENCION_PRESENCIAL;
    }

    /**
     * @param array<string, mixed> $draft
     */
    public function mensajeListaVaciaParaDraft(array $draft, bool $soloHubPaciente = true): string
    {
        $res = $this->resolverParaDraft($draft, $soloHubPaciente);
        if ($res['mensaje_orientacion'] !== null && trim($res['mensaje_orientacion']) !== '') {
            return trim($res['mensaje_orientacion']);
        }

        $label = $res['rol_label'] !== '' ? $res['rol_label'] : 'Medicina clínica';

        return 'No hay turnos de ' . $label
            . ' habilitados en este momento. Consultá con tu centro de salud si necesitás ayuda.';
    }

    /**
     * @param array<string, mixed> $draft
     */
    public function mensajeIntroListaParaDraft(array $draft, bool $soloHubPaciente = true): ?string
    {
        $res = $this->resolverParaDraft($draft, $soloHubPaciente);
        if ($res['id_servicios'] === []) {
            return null;
        }
        $msg = trim((string) ($res['mensaje_lista'] ?? ''));

        return $msg !== '' ? $msg : null;
    }

    /**
     * @param array<string, mixed> $draft mutado in-place
     */
    public function aplicarFlagsEnDraft(array &$draft): void
    {
        if (!$this->draftTieneTriageRelevante($draft)) {
            return;
        }

        $res = $this->resolverParaDraft($draft, false);
        $draft['servicio_reserva_rol'] = $res['rol'];
        $draft['triage_servicio_rol_ideal'] = $res['rol'];
        $draft['triage_codigo_servicio_resolutor'] = $res['triage_codigo_resolutor'];
        $draft['reserva_servicio_autogestion'] = $res['autogestion_disponible'] ? '1' : '0';
        if ($res['mensaje_orientacion'] !== null && trim($res['mensaje_orientacion']) !== '') {
            $draft['reserva_servicio_mensaje'] = trim($res['mensaje_orientacion']);
        }
        $draft['reserva_modo_hub_paciente'] = '1';
    }

    /**
     * Paciente autogestionando: presencial → cualquier servicio con agenda; teleconsulta especialista → derivación.
     */
    public function assertPacientePuedeReservarServicio(Turno $model): void
    {
        $idServicio = (int) ($model->id_servicio_asignado ?? 0);
        $idPersona = (int) ($model->id_persona ?? 0);
        $idEfector = (int) ($model->id_efector ?? 0);
        if ($idServicio <= 0 || $idPersona <= 0) {
            return;
        }

        $servicio = Servicio::findOne($idServicio);
        if ($servicio === null) {
            return;
        }

        if ($servicio->permiteReservaAutogestionPaciente()) {
            return;
        }

        $tipo = trim((string) ($model->tipo_atencion ?? Turno::TIPO_ATENCION_PRESENCIAL));
        if ($tipo === Turno::TIPO_ATENCION_PRESENCIAL) {
            return;
        }

        if ($idEfector <= 0) {
            throw new \InvalidArgumentException(
                'Este servicio requiere derivación de Medicina clínica antes de reservar turno.'
            );
        }

        $pendientes = ConsultaDerivaciones::getDerivacionesPorPersona(
            $idPersona,
            $idEfector,
            $idServicio,
            ConsultaDerivaciones::ESTADO_EN_ESPERA
        );
        if ($pendientes === []) {
            throw new \InvalidArgumentException(
                'Los turnos con especialistas requieren derivación de un médico de Medicina clínica.'
                . ' Pedí turno con clínica primero o usá la derivación que te indicaron.'
            );
        }
    }

    /**
     * @param array<string, mixed> $draft
     */
    public function resolverRolDesdeDraft(array $draft, bool $soloHubPaciente = false): string
    {
        return $this->resolverParaDraft($draft, $soloHubPaciente)['rol'];
    }

    /**
     * @param array<string, mixed> $params query/body de API
     * @return array<string, mixed>
     */
    public static function draftDesdeParamsTriage(array $params): array
    {
        $keys = [
            'triage_raiz',
            'triage_urgente',
            'triage_alarmas',
            'triage_zona',
            'triage_evolucion',
        ];
        $draft = [];
        foreach ($keys as $key) {
            if (!array_key_exists($key, $params)) {
                continue;
            }
            $v = trim((string) $params[$key]);
            if ($v !== '') {
                $draft[$key] = $v;
            }
        }

        return $draft;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public static function draftCarePlanDesdeParams(array $params): array
    {
        $draft = [];
        if (array_key_exists('care_plan_id', $params)) {
            $v = trim((string) $params['care_plan_id']);
            if ($v !== '') {
                $draft['care_plan_id'] = $v;
            }
        }

        return $draft;
    }

    /**
     * @param array<string, mixed> $draft
     */
    public function esSeguimientoConCarePlan(array $draft): bool
    {
        return trim((string) ($draft['triage_raiz'] ?? '')) === 'seguimiento_cronico'
            && (int) ($draft['care_plan_id'] ?? 0) > 0;
    }

    /**
     * @param array<string, mixed> $draft
     */
    public function mensajeIntroCarePlanParaDraft(array $draft): ?string
    {
        $res = $this->resolverDesdeCarePlan($draft);
        if ($res['id_servicios'] === []) {
            return 'Según tu plan de tratamiento no hay un servicio con turnos directos. '
                . 'Elegí Medicina clínica o consultá con tu centro de salud.';
        }

        return trim((string) ($res['mensaje_lista'] ?? '')) !== ''
            ? trim((string) $res['mensaje_lista'])
            : 'Estos servicios están vinculados a tu plan de tratamiento.';
    }

    public static function esModoHubPaciente(array $params): bool
    {
        return trim((string) ($params['reserva_modo'] ?? '')) === 'hub_paciente';
    }

    public static function esModoTeleconsultaHub(array $params): bool
    {
        return trim((string) ($params['reserva_modo'] ?? '')) === 'teleconsulta_hub';
    }

    /**
     * @return array{
     *   rol: string,
     *   rol_label: string,
     *   id_servicios: list<int>,
     *   filtrado_aplicado: bool,
     *   autogestion_disponible: bool,
     *   triage_codigo_resolutor: string,
     *   mensaje_orientacion: string|null,
     *   mensaje_lista: string|null
     * }
     */
    private function resolverSoloHub(array $draft): array
    {
        $resolver = new ReservaTriageServicioRolResolver();
        $ids = $resolver->idsServiciosHub();
        $label = (new ServicioMencionLookupService())->labelParaIds($ids);

        return [
            'rol' => $ids !== [] ? (string) $ids[0] : '',
            'rol_label' => $label !== '' ? $label : 'Medicina clínica',
            'id_servicios' => $ids,
            'filtrado_aplicado' => $ids !== [],
            'autogestion_disponible' => $ids !== [],
            'triage_codigo_resolutor' => '',
            'mensaje_orientacion' => null,
            'mensaje_lista' => null,
        ];
    }

    /**
     * @return array{
     *   rol: string,
     *   rol_label: string,
     *   id_servicios: list<int>,
     *   filtrado_aplicado: bool,
     *   autogestion_disponible: bool,
     *   triage_codigo_resolutor: string,
     *   mensaje_orientacion: string|null,
     *   mensaje_lista: string|null
     * }
     */
    private function resolverSinTriage(): array
    {
        $resolver = new ReservaTriageServicioRolResolver();
        $ids = $resolver->idsServiciosHub();
        $label = (new ServicioMencionLookupService())->labelParaIds($ids);

        return [
            'rol' => $ids !== [] ? (string) $ids[0] : '',
            'rol_label' => $label !== '' ? $label : 'Medicina clínica',
            'id_servicios' => $ids,
            'filtrado_aplicado' => false,
            'autogestion_disponible' => $ids !== [],
            'triage_codigo_resolutor' => '',
            'mensaje_orientacion' => null,
            'mensaje_lista' => null,
        ];
    }

    /**
     * @param list<array{id: string, name: string}> $items
     * @param list<int> $ids
     * @return list<array{id: string, name: string}>
     */
    private function filtrarItemsPorIds(array $items, array $ids): array
    {
        $allow = array_flip($ids);
        $filtered = [];
        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0 && isset($allow[$id])) {
                $filtered[] = $row;
            }
        }

        return $filtered;
    }

    /**
     * @param array<string, mixed> $draft
     */
    private function draftTieneTriageRelevante(array $draft): bool
    {
        if ($this->esSeguimientoConCarePlan($draft)) {
            return true;
        }
        foreach (['triage_raiz', 'triage_zona'] as $key) {
            if (trim((string) ($draft[$key] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $draft
     * @return array{
     *   rol: string,
     *   rol_label: string,
     *   id_servicios: list<int>,
     *   filtrado_aplicado: bool,
     *   autogestion_disponible: bool,
     *   triage_codigo_resolutor: string,
     *   mensaje_orientacion: string|null,
     *   mensaje_lista: string|null
     * }
     */
    private function resolverDesdeCarePlan(array $draft): array
    {
        $idPersona = (int) (Yii::$app->user->getIdPersona() ?? 0);
        $carePlanId = (int) ($draft['care_plan_id'] ?? 0);
        $carePlanSvc = new ReservaTriageCarePlanServicioService();
        $plan = $carePlanSvc->findPlanForPersona($carePlanId, $idPersona);

        if ($plan === null) {
            return $this->resolverSinTriage();
        }

        $idsSugeridos = $carePlanSvc->idsServicioReservaDesdePlan($plan);
        $eligibleIds = ServiciosEfectorAutogestionListadoService::idsServiciosDistintosAceptaTurnos();
        $idsElegibles = $this->intersectarElegiblesCarePlan($idsSugeridos, $eligibleIds);
        $idsReservables = $this->filtrarAutogestionCarePlan($idsElegibles);

        if ($idsReservables === [] && $idsSugeridos !== []) {
            $label = (new ServicioMencionLookupService())->labelParaIds($idsSugeridos);

            return [
                'rol' => (string) ($idsSugeridos[0] ?? ''),
                'rol_label' => $label,
                'id_servicios' => [],
                'filtrado_aplicado' => true,
                'autogestion_disponible' => false,
                'triage_codigo_resolutor' => 'seguimiento_cronico',
                'mensaje_orientacion' => 'Tu plan sugiere ' . ($label !== '' ? $label : 'una especialidad')
                    . ', pero no podés reservar turno directo desde la app. Pedí turno con Medicina clínica primero.',
                'mensaje_lista' => null,
            ];
        }

        if ($idsReservables === []) {
            return $this->resolverSinTriage();
        }

        $label = (new ServicioMencionLookupService())->labelParaIds($idsReservables);

        return [
            'rol' => (string) $idsReservables[0],
            'rol_label' => $label,
            'id_servicios' => $idsReservables,
            'filtrado_aplicado' => true,
            'autogestion_disponible' => true,
            'triage_codigo_resolutor' => 'seguimiento_cronico',
            'mensaje_orientacion' => null,
            'mensaje_lista' => 'Servicios vinculados a tu plan: ' . ($label !== '' ? $label : 'elegí uno'),
        ];
    }

    /**
     * @param list<int> $ids
     * @param list<int> $eligibleIds
     * @return list<int>
     */
    private function intersectarElegiblesCarePlan(array $ids, array $eligibleIds): array
    {
        if ($ids === []) {
            return [];
        }
        $allow = array_flip($eligibleIds);
        $out = [];
        foreach ($ids as $id) {
            if (isset($allow[$id])) {
                $out[] = $id;
            }
        }

        return $out;
    }

    /**
     * @param list<int> $ids
     * @return list<int>
     */
    private function filtrarAutogestionCarePlan(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $out = [];
        foreach (Servicio::find()->where(['id_servicio' => $ids])->all() as $servicio) {
            if (!$servicio instanceof Servicio) {
                continue;
            }
            if ($servicio->permiteReservaAutogestionPaciente()) {
                $out[] = (int) $servicio->id_servicio;
            }
        }
        sort($out);

        return array_values(array_unique($out));
    }
}
