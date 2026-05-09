<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\BadRequestHttpException;
use common\components\Services\ProfesionalEfectorServicio\ProfesionalEnEfectorListadoUiService;
use common\components\Services\ProfesionalEfectorServicio\ProfesionalEfectorServicioAgendaUiService;
use common\components\UiScreenService;
use common\components\UiSelectOptionSourceResolver;
use common\models\Persona;
use common\models\ProfesionalEfectorServicio;
use common\models\RrhhEfector;
use common\models\ServiciosEfector;

/**
 * API recurso humano (profesional en efector): autocomplete por efector/servicio; servicios asignados al RRHH del usuario en un efector.
 * Autocomplete: migrado desde frontend\controllers\RrhhController::actionRrhhAutocomplete.
 * Servicios por RRHH: migrado desde frontend\controllers\RrhhEfectorController::actionServiciosPorRrhh.
 */
class RecursoHumanoController extends BaseController
{
    public static $authenticatorExcept = ['autocomplete'];

    private function reqParamRaw(string $name): ?string
    {
        $req = Yii::$app->request;
        $v = $req->get($name);
        if ($v === null || $v === '') {
            $v = $req->post($name);
        }
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRrhhAutocompleteFilters(string $idEfector, string $idServicio): array
    {
        $req = Yii::$app->request;
        $filters = [
            'id_efector' => $idEfector,
            'id_servicio' => $idServicio,
            // Esta vista se usa para elegir profesional en flujos de turnos: solo servicios que aceptan turnos.
            'acepta_turnos' => 'SI',
            // Para listados UI JSON: devolver suficiente sin paginar de más.
            'limit' => 200,
        ];
        if ($req->get('sort_by') || $req->post('sort_by')) {
            $filters['sort_by'] = $req->get('sort_by') ?: $req->post('sort_by');
        }
        if ($req->get('sort_order') || $req->post('sort_order')) {
            $filters['sort_order'] = $req->get('sort_order') ?: $req->post('sort_order');
        }

        return $filters;
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function rrhhItemsForUi(?string $q, array $filters): array
    {
        $rows = ProfesionalEnEfectorListadoUiService::autocompletePorEfectorServicio($q ?? '', $filters);
        $out = [];
        foreach ($rows as $r) {
            if (!is_array($r)) {
                continue;
            }
            $id = isset($r['id']) ? trim((string) $r['id']) : '';
            $text = isset($r['text']) ? trim((string) $r['text']) : $id;
            if ($id === '') {
                continue;
            }
            $item = [
                'id' => $id,
                'name' => $text !== '' ? $text : $id,
            ];
            if (isset($r['id_rr_hh']) && (int) $r['id_rr_hh'] > 0) {
                $item['meta'] = ['id_rr_hh' => (int) $r['id_rr_hh']];
            }
            $out[] = $item;
        }

        return $out;
    }

    public function actions()
    {
        $actions = parent::actions();
        unset($actions['index'], $actions['view'], $actions['create'], $actions['update']);
        return $actions;
    }

    /**
     * GET/POST /api/v1/recurso-humano/autocomplete
     * Parámetros: id_efector, id_servicio (requeridos); q, limit, sort_by, sort_order, efector_nombre, servicio_nombre (opcionales).
     */
    public function actionAutocomplete($q = null)
    {
        $request = Yii::$app->request;
        $idEfector = $request->get('id_efector') ?: $request->post('id_efector');
        $idServicio = $request->get('id_servicio') ?: $request->post('id_servicio');
        if (empty($idEfector) || empty($idServicio)) {
            throw new BadRequestHttpException('id_efector e id_servicio son requeridos');
        }
        $out = ['results' => ['id' => '', 'text' => '']];
        // Para flujos UI JSON con auto_load, es válido consultar sin `q` y listar
        // profesionales disponibles para un efector+servicio.
        $q = $q ?? $request->get('q') ?? $request->post('q');
        if ($q === null) {
            $q = '';
        }
        $filters = [
            'id_efector' => $idEfector,
            'id_servicio' => $idServicio,
        ];
        if ($request->get('limit') || $request->post('limit')) {
            $filters['limit'] = $request->get('limit') ?: $request->post('limit');
        }
        if ($request->get('efector_nombre') || $request->post('efector_nombre')) {
            $filters['efector_nombre'] = $request->get('efector_nombre') ?: $request->post('efector_nombre');
        }
        if ($request->get('servicio_nombre') || $request->post('servicio_nombre')) {
            $filters['servicio_nombre'] = $request->get('servicio_nombre') ?: $request->post('servicio_nombre');
        }
        if ($request->get('sort_by') || $request->post('sort_by')) {
            $filters['sort_by'] = $request->get('sort_by') ?: $request->post('sort_by');
        }
        if ($request->get('sort_order') || $request->post('sort_order')) {
            $filters['sort_order'] = $request->get('sort_order') ?: $request->post('sort_order');
        }
        $out['results'] = array_values(ProfesionalEnEfectorListadoUiService::autocompletePorEfectorServicio((string) $q, $filters));
        return $out;
    }

    /**
     * GET|POST /api/v1/recurso-humano/listar-mis-servicios-en-efector
     *
     * Lista servicios asignados al RRHH de la persona autenticada, filtrados como en el flujo web
     * (servicio activo en el efector o servicio con item_name AdminEfector).
     *
     * Resolución del efector:
     * - Si viene `id_efector` o `idEfector`: se usa ese efector con la persona autenticada (asignaciones PES).
     * - Si no: se intenta por `id_rr_hh` en sesión → `rrhh_efector`, o en último término `getIdEfector()` de sesión.
     *
     * @return array{servicios: list<array{id_servicio: int, nombre: string}>}
     */
    public function actionListarMisServiciosEnEfector()
    {
        $request = Yii::$app->request;
        $idPersona = (int) Yii::$app->user->getIdPersona();

        $idEfectorRaw = $request->post('id_efector') ?: $request->post('idEfector')
            ?: $request->get('id_efector') ?: $request->get('idEfector');
        $tieneEfectorEnPedido = $idEfectorRaw !== null && $idEfectorRaw !== '';
        $idEfectorPedido = $tieneEfectorEnPedido ? (int) $idEfectorRaw : 0;

        $idEfector = 0;
        if ($idEfectorPedido > 0) {
            $idEfector = $idEfectorPedido;
        } else {
            $idRrHhSesion = (int) Yii::$app->user->getIdRecursoHumano();
            if ($idRrHhSesion > 0) {
                $rrhhEfector = RrhhEfector::findActive()
                    ->where([
                        'id_rr_hh' => $idRrHhSesion,
                        'id_persona' => $idPersona,
                    ])
                    ->one();
                if ($rrhhEfector !== null) {
                    $idEfector = (int) $rrhhEfector->id_efector;
                }
            }
            if ($idEfector <= 0) {
                $idEfector = (int) Yii::$app->user->getIdEfector();
            }
        }

        if ($idEfector <= 0) {
            throw new BadRequestHttpException(
                'Indique id_efector o fije contexto operativo en sesión (efector / recurso humano) para listar servicios.'
            );
        }

        $pesRows = ProfesionalEfectorServicio::find()
            ->where([
                'id_persona' => $idPersona,
                'id_efector' => $idEfector,
                'deleted_at' => null,
            ])
            ->with('servicio')
            ->orderBy(['id_servicio' => SORT_ASC])
            ->all();

        $servicios = [];
        $vistos = [];
        foreach ($pesRows as $pes) {
            $idServicio = (int) $pes->id_servicio;
            if (isset($vistos[$idServicio])) {
                continue;
            }
            $servicioEfector = ServiciosEfector::findActive()
                ->where([
                    'id_efector' => $idEfector,
                    'id_servicio' => $idServicio,
                ])
                ->one();

            $nombreServicio = $pes->servicio !== null
                ? (string) $pes->servicio->nombre
                : '';
            $esAdminEfector = $pes->servicio !== null
                && (string) $pes->servicio->item_name === 'AdminEfector';

            if (
                ($servicioEfector !== null && $servicioEfector->deleted_at === null)
                || $esAdminEfector
            ) {
                $vistos[$idServicio] = true;
                $servicios[] = [
                    'id_servicio' => $idServicio,
                    'nombre' => $nombreServicio,
                ];
            }
        }

        return ['servicios' => $servicios];
    }

    /**
     * Vista embebible: listar RRHH (profesionales) de un efector como `ui_json`.
     *
     * GET|POST /api/v1/recurso-humano/listar-por-efector
     *
     * Parámetros: id_efector (opcional, default sesión), q (opcional), limit (opcional).
     *
     * @action_name Listar profesionales por efector
     * @entity Rrhh
     * @tags views, ui, rrhh, profesional
     * @keywords elegir profesional, listar médicos, listar especialistas, efector
     */
    public function actionListarPorEfector(): array
    {
        $req = Yii::$app->request;
        $ui = UiScreenService::handleScreen(
            'recurso-humano',
            'listar-por-efector',
            $req->get(),
            $req->post(),
            static function (array $post): array {
                return ['data' => ['ok' => true]];
            }
        );

        if (isset($ui['kind']) && $ui['kind'] === 'ui_definition' && isset($ui['ui_type']) && $ui['ui_type'] === 'ui_json') {
            $q = $req->get('q') ?: $req->post('q');
            $idEfector = $req->get('id_efector') ?: $req->post('id_efector');
            if ($idEfector === null || $idEfector === '') {
                $idEfector = Yii::$app->user->getIdEfector();
            }
            $idEfector = (int) $idEfector;
            if ($idEfector <= 0) {
                throw new BadRequestHttpException('id_efector es requerido');
            }

            $limit = (int) ($req->get('limit') ?: $req->post('limit') ?: 200);
            if ($limit < 1) {
                $limit = 200;
            }
            if ($limit > 200) {
                $limit = 200;
            }

            try {
                $ui = UiScreenService::withListBlockItems(
                    $ui,
                    ProfesionalEnEfectorListadoUiService::listarPorEfector($idEfector, is_string($q) ? $q : null, $limit)
                );
            } catch (\InvalidArgumentException $e) {
                throw new BadRequestHttpException($e->getMessage());
            }
        }

        return $ui;
    }

    /**
     * Vista embebible: listar RRHH (profesionales) de un efector como `ui_json`,
     * filtrando a RRHH que tengan servicios con `servicios.acepta_turnos = SI`.
     *
     * GET|POST /api/v1/recurso-humano/listar-por-efector-acepta-turnos
     *
     * Parámetros: id_efector (opcional, default sesión), q (opcional), limit (opcional).
     *
     * @action_name Listar profesionales (acepta turnos) por efector
     * @entity Rrhh
     * @tags views, ui, rrhh, profesional
     * @keywords elegir profesional, listar médicos, listar especialistas, efector, acepta turnos
     */
    public function actionListarPorEfectorAceptaTurnos(): array
    {
        $req = Yii::$app->request;
        $ui = UiScreenService::handleScreen(
            'recurso-humano',
            'listar-por-efector-acepta-turnos',
            $req->get(),
            $req->post(),
            static function (array $post): array {
                return ['data' => ['ok' => true]];
            }
        );

        if (isset($ui['kind']) && $ui['kind'] === 'ui_definition' && isset($ui['ui_type']) && $ui['ui_type'] === 'ui_json') {
            $q = $req->get('q') ?: $req->post('q');
            $idEfector = $req->get('id_efector') ?: $req->post('id_efector');
            if ($idEfector === null || $idEfector === '') {
                $idEfector = Yii::$app->user->getIdEfector();
            }
            $idEfector = (int) $idEfector;
            if ($idEfector <= 0) {
                throw new BadRequestHttpException('id_efector es requerido');
            }

            $limit = (int) ($req->get('limit') ?: $req->post('limit') ?: 200);
            if ($limit < 1) {
                $limit = 200;
            }
            if ($limit > 200) {
                $limit = 200;
            }

            try {
                $ui = UiScreenService::withListBlockItems(
                    $ui,
                    ProfesionalEnEfectorListadoUiService::listarPorEfectorAceptaTurnos($idEfector, is_string($q) ? $q : null, $limit)
                );
            } catch (\InvalidArgumentException $e) {
                throw new BadRequestHttpException($e->getMessage());
            }
        }

        return $ui;
    }

    /**
     * Vista embebible: listar servicios asignados a un RRHH como `ui_json`.
     *
     * GET|POST /api/v1/recurso-humano/listar-servicios-en-efector
     *
     * Parámetros: id_rr_hh o id_profesional_efector_servicio (uno obligatorio) para anclar el profesional en el efector de sesión.
     *
     * @action_name Listar servicios de un profesional (en efector)
     * @entity Rrhh
     * @tags views, ui, rrhh, servicios
     * @keywords elegir servicio, servicios asignados, agenda por servicio, efector
     */
    public function actionListarServiciosEnEfector(): array
    {
        $req = Yii::$app->request;
        $ui = UiScreenService::handleScreen(
            'recurso-humano',
            'listar-servicios-en-efector',
            $req->get(),
            $req->post(),
            static function (array $post): array {
                return ['data' => ['ok' => true]];
            }
        );

        if (isset($ui['kind']) && $ui['kind'] === 'ui_definition' && isset($ui['ui_type']) && $ui['ui_type'] === 'ui_json') {
            $idEfector = $this->requireIdEfectorFromSession();
            [$idPersonaProf] = $this->resolvePersonaYEfectorParaServiciosProfesional($idEfector);

            $items = $this->serviciosAsignadosItemsForPersonaEfector($idPersonaProf, $idEfector);
            $uiItems = [];
            foreach ($items as $it) {
                $uiItems[] = [
                    'id' => (string) (int) $it['id'],
                    'name' => (string) $it['name'],
                    'meta' => isset($it['meta']) && is_array($it['meta']) ? $it['meta'] : [],
                ];
            }
            $ui = UiScreenService::withListBlockItems($ui, $uiItems);
        }

        return $ui;
    }

    /**
     * Vista embebible: servicios habilitados en el efector de sesión ({@see ServiciosEfector}), sin exigir RRHH previo.
     * Sin `q` no se listan ítems (solo plantilla con buscador).
     *
     * GET|POST /api/v1/recurso-humano/listar-servicios-habilitados-efector
     *
     * @action_name Listar servicios habilitados en el efector
     * @entity Rrhh
     * @tags views, ui, rrhh, servicios, asistente
     */
    public function actionListarServiciosHabilitadosEfector(): array
    {
        $req = Yii::$app->request;
        $ui = UiScreenService::handleScreen(
            'recurso-humano',
            'listar-servicios-habilitados-efector',
            $req->get(),
            $req->post(),
            static function (array $post): array {
                return ['data' => ['ok' => true]];
            }
        );

        if (isset($ui['kind']) && $ui['kind'] === 'ui_definition' && isset($ui['ui_type']) && $ui['ui_type'] === 'ui_json') {
            $idEfector = (int) Yii::$app->user->getIdEfector();
            if ($idEfector <= 0) {
                throw new BadRequestHttpException('No hay efector en sesión.');
            }
            $q = $req->get('q') ?: $req->post('q');
            try {
                $raw = ProfesionalEnEfectorListadoUiService::listarServiciosHabilitadosPorEfector($idEfector, is_string($q) ? $q : null);
            } catch (\InvalidArgumentException $e) {
                throw new BadRequestHttpException($e->getMessage());
            }
            $uiItems = [];
            foreach ($raw as $it) {
                $uiItems[] = [
                    'id' => (string) $it['id'],
                    'name' => (string) $it['name'],
                    'meta' => isset($it['meta']) && is_array($it['meta']) ? $it['meta'] : [],
                ];
            }
            $ui = UiScreenService::withListBlockItems($ui, $uiItems);
        }

        return $ui;
    }

    /**
     * Ancla el profesional vía `id_profesional_efector_servicio` o `id_rr_hh` + efector de sesión.
     *
     * @return array{0: int, 1: int} id_persona del profesional, id_efector
     */
    private function resolvePersonaYEfectorParaServiciosProfesional(int $idEfectorSesion): array
    {
        $request = Yii::$app->request;
        $idPesRaw = $request->get('id_profesional_efector_servicio') ?: $request->post('id_profesional_efector_servicio');
        if ($idPesRaw !== null && $idPesRaw !== '') {
            $pes = ProfesionalEfectorServicio::findOne(['id' => (int) $idPesRaw, 'deleted_at' => null]);
            if ($pes === null) {
                throw new BadRequestHttpException('id_profesional_efector_servicio inválido.');
            }
            if ((int) $pes->id_efector !== (int) $idEfectorSesion) {
                throw new BadRequestHttpException('La PES no corresponde al efector de sesión.');
            }

            return [(int) $pes->id_persona, (int) $pes->id_efector];
        }

        $idRrHh = $request->get('id_rr_hh') ?: $request->post('id_rr_hh');
        if ($idRrHh === null || $idRrHh === '') {
            throw new BadRequestHttpException('Indique id_rr_hh o id_profesional_efector_servicio.');
        }
        $re = RrhhEfector::findActive()
            ->where([
                'id_rr_hh' => (int) $idRrHh,
                'id_efector' => $idEfectorSesion,
                'deleted_at' => null,
            ])
            ->one();
        if ($re === null) {
            throw new BadRequestHttpException('RRHH no válido para este efector.');
        }

        return [(int) $re->id_persona, (int) $re->id_efector];
    }

    private function requireIdEfectorFromSession(): int
    {
        $idEfector = (int) Yii::$app->user->getIdEfector();
        if ($idEfector <= 0) {
            throw new BadRequestHttpException('No hay efector en sesión.');
        }

        return $idEfector;
    }

    /**
     * @return list<array{id:int,name:string,meta:array{id_rrhh_servicio:int, id_profesional_efector_servicio:int, acepta_turnos:string}>>
     *         meta.id_rrhh_servicio: mismo entero que meta.id_profesional_efector_servicio (alias de payload); preferir el nombre canónico.
     */
    private function serviciosAsignadosItemsForPersonaEfector(int $idPersona, int $idEfector): array
    {
        $pesRows = ProfesionalEfectorServicio::find()
            ->where([
                'id_persona' => $idPersona,
                'id_efector' => $idEfector,
                'deleted_at' => null,
            ])
            ->with('servicio')
            ->orderBy(['id_servicio' => SORT_ASC])
            ->all();

        $items = [];
        foreach ($pesRows as $pes) {
            if ((int) $pes->id_servicio === 62) {
                continue;
            }
            $nombre = $pes->servicio !== null ? (string) $pes->servicio->nombre : ('Servicio #' . $pes->id_servicio);
            $acepta = $pes->servicio !== null && strtoupper(trim((string) $pes->servicio->acepta_turnos)) === 'SI' ? 'SI' : 'NO';
            $items[] = [
                'id' => (int) $pes->id_servicio,
                'name' => $nombre,
                'meta' => [
                    'id_rrhh_servicio' => (int) $pes->id,
                    'id_profesional_efector_servicio' => (int) $pes->id,
                    'acepta_turnos' => $acepta,
                ],
            ];
        }

        return $items;
    }

    /**
     * GET /api/v1/recurso-humano/condiciones-laborales-catalogo
     *
     * @return array{results: list<array{id: int, text: string}>}
     */
    public function actionCondicionesLaboralesCatalogo()
    {
        $opts = UiSelectOptionSourceResolver::resolve('catalog', ['catalog' => 'condiciones_laborales'], []);
        if (!is_array($opts)) {
            $opts = [];
        }
        $results = [];
        foreach ($opts as $o) {
            $results[] = [
                'id' => (int) ($o['value'] ?? 0),
                'text' => (string) ($o['label'] ?? ''),
            ];
        }

        return ['results' => $results];
    }

    /**
     * UI JSON: crear/editar condición laboral (vigencia) de un RRHH.
     *
     * GET|POST /api/v1/recurso-humano/editar-condicion-laboral
     *
     * @action_name Editar condición laboral (RRHH)
     * @entity Rrhh
     * @tags rrhh, condicion-laboral, staff
     * @keywords condición laboral, vigencia, rrhh
     */
    public function actionEditarCondicionLaboral(): array
    {
        $req = Yii::$app->request;
        $idEfector = (int) Yii::$app->user->getIdEfector();

        $fromClient = array_merge($req->get(), $req->isPost ? $req->post() : []);
        $defaults = ProfesionalEfectorServicioAgendaUiService::buildCondicionLaboralValuesForGet($idEfector, $fromClient);
        $paramsForRender = array_merge($defaults, $fromClient);

        return UiScreenService::handleScreen(
            'recurso-humano',
            'editar-condicion-laboral',
            $paramsForRender,
            $req->post(),
            static function (array $post) use ($idEfector): array {
                return ProfesionalEfectorServicioAgendaUiService::submitCondicionLaboral($idEfector, $post);
            }
        );
    }

    /**
     * UI JSON: crear condición laboral (vigencia) de un RRHH.
     * Nota: el submit es un upsert; este endpoint existe por claridad de intención.
     *
     * GET|POST /api/v1/recurso-humano/crear-condicion-laboral
     *
     * @action_name Crear condición laboral (RRHH)
     * @entity Rrhh
     * @tags rrhh, condicion-laboral, staff
     * @keywords crear condición laboral, alta condición laboral, vigencia rrhh
     */
    public function actionCrearCondicionLaboral(): array
    {
        $req = Yii::$app->request;
        $idEfector = (int) Yii::$app->user->getIdEfector();

        $fromClient = array_merge($req->get(), $req->isPost ? $req->post() : []);
        // Precarga si existe (upsert). Para "crear", esto también ayuda a no duplicar.
        $defaults = ProfesionalEfectorServicioAgendaUiService::buildCondicionLaboralValuesForGet($idEfector, $fromClient);
        $paramsForRender = array_merge($defaults, $fromClient);

        return UiScreenService::handleScreen(
            'recurso-humano',
            'crear-condicion-laboral',
            $paramsForRender,
            $req->post(),
            static function (array $post) use ($idEfector): array {
                return ProfesionalEfectorServicioAgendaUiService::submitCondicionLaboral($idEfector, $post);
            }
        );
    }

    /**
     * Vista embebible: listar profesionales (RRHH) por efector y servicio (obligatorios),
     * filtrando a servicios que aceptan turnos (`servicios.acepta_turnos = SI`).
     *
     * GET|POST /api/v1/recurso-humano/listar-por-efector-servicio-acepta-turnos
     *
     * @action_name Listar profesionales (acepta turnos) por efector y servicio
     * @entity Rrhh
     * @tags views, ui, rrhh, profesional
     * @keywords listar profesional, elegir médico, elegir especialista, efector, servicio, acepta turnos
     */
    public function actionListarPorEfectorServicioAceptaTurnos(): array
    {
        $req = Yii::$app->request;
        $idEfector = $this->reqParamRaw('id_efector');
        $idServicio = $this->reqParamRaw('id_servicio');
        if ($idEfector === null || $idServicio === null) {
            throw new BadRequestHttpException('id_efector e id_servicio son requeridos');
        }

        $ui = UiScreenService::handleScreen(
            'recurso-humano',
            'listar-por-efector-servicio-acepta-turnos',
            $req->get(),
            $req->post(),
            static function (array $post): array {
                return ['data' => ['ok' => true]];
            }
        );

        if (isset($ui['kind']) && $ui['kind'] === 'ui_definition' && isset($ui['ui_type']) && $ui['ui_type'] === 'ui_json') {
            $filters = $this->buildRrhhAutocompleteFilters($idEfector, $idServicio);
            $q = $this->reqParamRaw('q');
            $ui = UiScreenService::withListBlockItems($ui, $this->rrhhItemsForUi($q, $filters));
        }

        return $ui;
    }

    /**
     * Vista embebible: elegir profesional (RRHH) para un efector/servicio.
     *
     * GET|POST /api/v1/recurso-humano/elegir
     *
     * @deprecated Preferir listar-por-efector-servicio-acepta-turnos en nuevos flujos.
     */
    public function actionElegir(): array
    {
        $req = Yii::$app->request;
        // Mantener compatibilidad: redirigir al listado explícito si llegan los parámetros requeridos.
        $idEfector = $this->reqParamRaw('id_efector');
        $idServicio = $this->reqParamRaw('id_servicio');
        if ($idEfector !== null && $idServicio !== null) {
            return $this->actionListarPorEfectorServicioAceptaTurnos();
        }

        return UiScreenService::handleScreen(
            'recurso-humano',
            'elegir',
            $req->get(),
            $req->post(),
            static function (array $post): array {
                return ['data' => ['ok' => true]];
            }
        );
    }
}
