<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\BadRequestHttpException;
use common\components\Services\Rrhh\RrhhService;
use common\components\UiScreenService;
use common\models\Condiciones_laborales;
use common\models\Persona;
use common\models\RrhhEfector;
use common\models\RrhhServicio;
use common\models\ServiciosEfector;

/**
 * API Rrhh: autocomplete de RRHH por efector/servicio; servicios asignados al RRHH del usuario en un efector.
 * Autocomplete: migrado desde frontend\controllers\RrhhController::actionRrhhAutocomplete.
 * Servicios por RRHH: migrado desde frontend\controllers\RrhhEfectorController::actionServiciosPorRrhh.
 */
class RrhhController extends BaseController
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
        $rows = RrhhEfector::autocompleteRrhh($q ?? '', $filters);
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
            $out[] = [
                'id' => $id,
                'name' => $text !== '' ? $text : $id,
            ];
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
     * GET/POST /api/v1/rrhh/autocomplete
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
        $out['results'] = array_values(RrhhEfector::autocompleteRrhh($q, $filters));
        return $out;
    }

    /**
     * GET|POST /api/v1/rrhh/listar-servicios-en-efector
     *
     * Lista servicios asignados al RRHH de la persona autenticada, filtrados como en el flujo web
     * (servicio activo en el efector o servicio con item_name AdminEfector).
     *
     * Resolución del vínculo RRHH–efector:
     * - Si viene `id_efector` o `idEfector` (query/body): se usa la fila `rrhh_efector` de esa persona en ese efector
     *   (p. ej. wizard post-login antes de tener sesión operativa completa).
     * - Si no viene efector: se usa `id_rr_hh` de la sesión operativa (`getIdRecursoHumano`) y la fila correspondiente
     *   debe pertenecer a la misma persona.
     *
     * @return array{servicios: list<array{id_servicio: int, nombre: string}>}
     */
    public function actionListarServiciosEnEfector()
    {
        $request = Yii::$app->request;
        $idPersona = (int) Yii::$app->user->getIdPersona();

        $idEfectorRaw = $request->post('id_efector') ?: $request->post('idEfector')
            ?: $request->get('id_efector') ?: $request->get('idEfector');
        $tieneEfectorEnPedido = $idEfectorRaw !== null && $idEfectorRaw !== '';
        $idEfectorPedido = $tieneEfectorEnPedido ? (int) $idEfectorRaw : 0;

        $rrhhEfector = null;
        if ($idEfectorPedido > 0) {
            $rrhhEfector = RrhhEfector::findActive()
                ->where([
                    'id_efector' => $idEfectorPedido,
                    'id_persona' => $idPersona,
                ])
                ->one();
        } else {
            $idRrHhSesion = (int) Yii::$app->user->getIdRecursoHumano();
            if ($idRrHhSesion > 0) {
                $rrhhEfector = RrhhEfector::findActive()
                    ->where([
                        'id_rr_hh' => $idRrHhSesion,
                        'id_persona' => $idPersona,
                    ])
                    ->one();
            }
        }

        if ($rrhhEfector === null) {
            throw new BadRequestHttpException(
                'Indique id_efector o fije contexto operativo en sesión (recurso humano / efector) para listar servicios.'
            );
        }
        /** @var RrhhEfector $rrhhEfector */

        $idEfector = (int) $rrhhEfector->id_efector;
        $servicios = [];
        /** @var \yii\db\ActiveQuery $q */
        $q = $rrhhEfector->getRrhhServicio();
        $rrhhServicios = $q->with('servicio')->all();
        foreach ($rrhhServicios as $rrhhServicio) {
            $servicioEfector = ServiciosEfector::findActive()
                ->where([
                    'id_efector' => $idEfector,
                    'id_servicio' => $rrhhServicio->id_servicio,
                ])
                ->one();

            $nombreServicio = $rrhhServicio->servicio !== null
                ? (string) $rrhhServicio->servicio->nombre
                : '';
            $esAdminEfector = $rrhhServicio->servicio !== null
                && (string) $rrhhServicio->servicio->item_name === 'AdminEfector';

            if (
                ($servicioEfector !== null && $servicioEfector->deleted_at === null)
                || $esAdminEfector
            ) {
                $servicios[] = [
                    'id_servicio' => (int) $rrhhServicio->id_servicio,
                    'nombre' => $nombreServicio,
                ];
            }
        }

        return ['servicios' => $servicios];
    }

    /**
     * Vista embebible: listar RRHH (profesionales) de un efector como `ui_json`.
     *
     * GET|POST /api/v1/rrhh/listar-por-efector
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
            'rrhh',
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
                $ui['items'] = RrhhService::listarPorEfector($idEfector, is_string($q) ? $q : null, $limit);
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
     * GET|POST /api/v1/rrhh/listar-por-efector-acepta-turnos
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
            'rrhh',
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
                $ui['items'] = RrhhService::listarPorEfectorAceptaTurnos($idEfector, is_string($q) ? $q : null, $limit);
            } catch (\InvalidArgumentException $e) {
                throw new BadRequestHttpException($e->getMessage());
            }
        }

        return $ui;
    }

    /**
     * Vista embebible: listar servicios asignados a un RRHH como `ui_json`.
     *
     * GET|POST /api/v1/rrhh/listar-servicios-asignados
     *
     * Parámetros: id_rr_hh (obligatorio).
     *
     * @action_name Listar servicios asignados (UI)
     * @entity Rrhh
     * @tags views, ui, rrhh, servicios
     * @keywords elegir servicio, servicios asignados, agenda por servicio
     */
    public function actionListarServiciosAsignados(): array
    {
        $req = Yii::$app->request;
        $ui = UiScreenService::handleScreen(
            'rrhh',
            'listar-servicios-asignados',
            $req->get(),
            $req->post(),
            static function (array $post): array {
                return ['data' => ['ok' => true]];
            }
        );

        if (isset($ui['kind']) && $ui['kind'] === 'ui_definition' && isset($ui['ui_type']) && $ui['ui_type'] === 'ui_json') {
            $idRrHh = $this->requireIdRrHh();
            $idEfector = $this->requireIdEfectorFromSession();

            $items = $this->serviciosAsignadosItems($idRrHh, $idEfector);
            $uiItems = [];
            foreach ($items as $it) {
                $uiItems[] = [
                    'id' => (string) (int) $it['id'],
                    'name' => (string) $it['name'],
                    'meta' => isset($it['meta']) && is_array($it['meta']) ? $it['meta'] : [],
                ];
            }
            $ui['items'] = $uiItems;
        }

        return $ui;
    }

    private function requireIdRrHh(): int
    {
        $request = Yii::$app->request;
        $idRrHh = $request->get('id_rr_hh') ?: $request->post('id_rr_hh');
        if ($idRrHh === null || $idRrHh === '') {
            throw new BadRequestHttpException('id_rr_hh es requerido');
        }

        return (int) $idRrHh;
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
     * @return list<array{id:int,name:string,meta:array{id_rrhh_servicio:int}}>
     */
    private function serviciosAsignadosItems(int $idRrHh, int $idEfector): array
    {
        /** @var RrhhEfector|null $re */
        $re = RrhhEfector::findActive()
            ->where(['id_rr_hh' => $idRrHh, 'id_efector' => $idEfector, 'deleted_at' => null])
            ->one();
        if ($re === null) {
            throw new BadRequestHttpException('RRHH no válido para este efector.');
        }

        /** @var \yii\db\ActiveQuery $serviciosQ */
        $serviciosQ = RrhhServicio::find();
        $serviciosQ->where(['id_rr_hh' => $idRrHh, 'deleted_at' => null])
            ->with('servicio')
            ->orderBy(['id_servicio' => SORT_ASC]);
        $servicios = $serviciosQ->all();

        $items = [];
        foreach ($servicios as $rs) {
            if ((int) $rs->id_servicio === 62) {
                continue;
            }
            $nombre = $rs->servicio !== null ? (string) $rs->servicio->nombre : ('Servicio #' . $rs->id_servicio);
            $items[] = [
                'id' => (int) $rs->id_servicio,
                'name' => $nombre,
                'meta' => [
                    'id_rrhh_servicio' => (int) $rs->id,
                ],
            ];
        }

        return $items;
    }

    /**
     * GET /api/v1/rrhh/condiciones-laborales-catalogo
     *
     * @return array{results: list<array{id: int, text: string}>}
     */
    public function actionCondicionesLaboralesCatalogo()
    {
        $rows = Condiciones_laborales::find()
            ->orderBy(['nombre' => SORT_ASC])
            ->all();

        $results = [];
        foreach ($rows as $row) {
            $results[] = [
                'id' => (int) $row->id_condicion_laboral,
                'text' => (string) $row->nombre,
            ];
        }

        return ['results' => $results];
    }

    /**
     * Vista embebible: listar profesionales (RRHH) por efector y servicio (obligatorios),
     * filtrando a servicios que aceptan turnos (`servicios.acepta_turnos = SI`).
     *
     * GET|POST /api/v1/rrhh/listar-por-efector-servicio-acepta-turnos
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
            'rrhh',
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
            $ui['items'] = $this->rrhhItemsForUi($q, $filters);
        }

        return $ui;
    }

    /**
     * Vista embebible: elegir profesional (RRHH) para un efector/servicio.
     *
     * GET|POST /api/v1/rrhh/elegir
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
            'rrhh',
            'elegir',
            $req->get(),
            $req->post(),
            static function (array $post): array {
                return ['data' => ['ok' => true]];
            }
        );
    }
}
