<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\BadRequestHttpException;
use common\components\Platform\Core\Permission\Domain\ApiDomainOperationBridge;
use common\components\Platform\Core\Permission\Domain\DomainOperationForbiddenException;
use common\components\Domain\Organization\Service\Authorization\ProfesionalEfectorServicioDomainAuthorizationService;
use yii\web\ForbiddenHttpException;
use yii\web\MethodNotAllowedHttpException;
use common\components\Domain\Organization\Service\ProfesionalEfectorServicio\FhirServiceCodeCatalogUiService;
use common\components\Domain\Organization\Service\ProfesionalEfectorServicio\LicenciaUiFlowService;
use common\components\Domain\Organization\Service\ProfesionalEfectorServicio\ProfesionalEfectorServicioCuilUiService;
use common\components\Domain\Organization\Service\ProfesionalEfectorServicio\ProfesionalEnEfectorListadoUiService;
use common\components\Domain\Organization\Service\ProfesionalEfectorServicio\ProfesionalEfectorServicioAgendaUiService;
use common\components\Platform\Ui\UiScreenService;
use common\components\Platform\Ui\UiSelectOptionSourceResolver;
use common\models\Person\Persona;
use common\models\ProfesionalEfectorServicio;
use common\models\ServiciosEfector;

/**
 * API profesional en efector: autocomplete por efector/servicio; servicios asignados en sesión.
 * Autocomplete: migrado desde listados web legacy.
 * Servicios por PES: ver admin ProfesionalEfectorServicio.
 */
class ProfesionalEfectorServicioController extends BaseController
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
    private function buildProfesionalAutocompleteFilters(string $idEfector, string $idServicio): array
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
        $tipoAtencion = $req->get('tipo_atencion') ?: $req->post('tipo_atencion');
        if ($tipoAtencion !== null && trim((string) $tipoAtencion) !== '') {
            $filters['tipo_atencion'] = trim((string) $tipoAtencion);
        }

        return $filters;
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function profesionalItemsForUi(?string $q, array $filters): array
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
            $pesId = (int) ($r['id_profesional_efector_servicio'] ?? $r['id'] ?? 0);
            if ($pesId > 0) {
                $item['meta'] = ['id_profesional_efector_servicio' => $pesId];
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
     * GET/POST /api/v1/profesional-efector-servicio/autocomplete
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
     * Vista embebible: servicios propios del profesional autenticado en el efector (`ui_json`).
     *
     * GET|POST /api/v1/profesional-efector-servicio/listar-mis-servicios-en-efector
     *
     * Resolución del efector:
     * - Si viene `id_efector` o `idEfector`: se usa ese efector con la persona autenticada (asignaciones PES).
     * - Si no: contexto profesional en sesión (PES) o `getIdEfector()` de sesión.
     *
     * @action_name Listar mis servicios en el efector
     * @entity Profesional
     * @tags views, ui, servicios, agenda, profesional
     * @keywords mis servicios, elegir servicio propio, editar mi agenda
     */
    public function actionListarMisServiciosEnEfector(): array
    {
        $req = Yii::$app->request;
        $ui = UiScreenService::handleScreen(
            'profesional-efector-servicio',
            'listar-mis-servicios-en-efector',
            $req->get(),
            $req->post(),
            static function (array $post): array {
                return ['data' => ['ok' => true]];
            }
        );

        if (isset($ui['kind']) && $ui['kind'] === 'ui_definition' && isset($ui['ui_type']) && $ui['ui_type'] === 'ui_json') {
            $idPersona = (int) Yii::$app->user->getIdPersona();
            $idEfector = $this->resolveIdEfectorParaMisServicios();
            $incluirSinAgenda = filter_var(
                $req->get('incluir_sin_agenda') ?: $req->post('incluir_sin_agenda') ?: '0',
                FILTER_VALIDATE_BOOLEAN
            );
            $items = $this->serviciosAsignadosItemsForPersonaEfector($idPersona, $idEfector, $incluirSinAgenda);
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

    private function resolveIdEfectorParaMisServicios(): int
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
            $idPesSesion = (int) Yii::$app->user->getIdProfesionalEfectorServicio();
            if ($idPesSesion > 0) {
                $idPersonaSesion = ProfesionalEfectorServicio::resolveIdPersonaFromStaffContextId($idPesSesion);
                if ($idPersonaSesion !== null && $idPersonaSesion === $idPersona) {
                    $pesCtx = ProfesionalEfectorServicio::find()
                        ->where(['id_persona' => $idPersona, 'deleted_at' => null])
                        ->orderBy(['id_efector' => SORT_ASC, 'id' => SORT_ASC])
                        ->one();
                    if ($pesCtx !== null) {
                        $idEfector = (int) $pesCtx->id_efector;
                    }
                }
            }
            if ($idEfector <= 0) {
                $idEfector = (int) Yii::$app->user->getIdEfector();
            }
        }

        if ($idEfector <= 0) {
            throw new BadRequestHttpException(
                'Indique id_efector o fije contexto operativo en sesión (efector / profesional) para listar servicios.'
            );
        }

        return $idEfector;
    }

    /**
     * Vista embebible: listar profesionales de un efector como `ui_json`.
     *
     * GET|POST /api/v1/profesional-efector-servicio/listar-por-efector
     *
     * Parámetros: id_efector (opcional, default sesión), q (opcional), limit (opcional),
     * excluir_id_persona_sesion (opcional, 1 = no listar la persona del usuario en sesión).
     *
     * @action_name Listar profesionales por efector
     * @entity Profesional
     * @tags views, ui, profesional
     * @keywords elegir profesional, listar médicos, listar especialistas, efector
     */
    public function actionListarPorEfector(): array
    {
        $req = Yii::$app->request;
        $ui = UiScreenService::handleScreen(
            'profesional-efector-servicio',
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

            $excluirSesion = filter_var(
                $req->get('excluir_id_persona_sesion') ?: $req->post('excluir_id_persona_sesion') ?: '0',
                FILTER_VALIDATE_BOOLEAN
            );
            $excluirIdPersona = null;
            if ($excluirSesion) {
                $idPersonaSesion = (int) Yii::$app->user->getIdPersona();
                if ($idPersonaSesion > 0) {
                    $excluirIdPersona = $idPersonaSesion;
                }
            }

            try {
                $ui = UiScreenService::withListBlockItems(
                    $ui,
                    ProfesionalEnEfectorListadoUiService::listarPorEfector(
                        $idEfector,
                        is_string($q) ? $q : null,
                        $limit,
                        $excluirIdPersona
                    )
                );
            } catch (\InvalidArgumentException $e) {
                throw new BadRequestHttpException($e->getMessage());
            }
        }

        return $ui;
    }

    /**
     * Vista embebible: listar profesionales de un efector como `ui_json`,
     * filtrando a quienes tengan servicios con `servicios.acepta_turnos = SI`.
     *
     * GET|POST /api/v1/profesional-efector-servicio/listar-por-efector-acepta-turnos
     *
     * Parámetros: id_efector (opcional, default sesión), q (opcional), limit (opcional).
     *
     * @action_name Listar profesionales (acepta turnos) por efector
     * @entity Profesional
     * @tags views, ui, profesional
     * @keywords elegir profesional, listar médicos, listar especialistas, efector, acepta turnos
     */
    public function actionListarPorEfectorAceptaTurnos(): array
    {
        $req = Yii::$app->request;
        $ui = UiScreenService::handleScreen(
            'profesional-efector-servicio',
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
     * Vista embebible: listar servicios asignados al profesional como `ui_json`.
     *
     * GET|POST /api/v1/profesional-efector-servicio/listar-servicios-en-efector
     *
     * Parámetros: `id_profesional_efector_servicio` (obligatorio) para anclar el profesional en el efector de sesión.
     *
     * @action_name Listar servicios de un profesional (en efector)
     * @entity Profesional
     * @tags views, ui, servicios
     * @keywords elegir servicio, servicios asignados, agenda por servicio, efector
     */
    public function actionListarServiciosEnEfector(): array
    {
        $req = Yii::$app->request;
        $ui = UiScreenService::handleScreen(
            'profesional-efector-servicio',
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
     * Ancla el profesional vía `id_profesional_efector_servicio` en el efector de sesión.
     *
     * @return array{0: int, 1: int} id_persona del profesional, id_efector
     */
    private function resolvePersonaYEfectorParaServiciosProfesional(int $idEfectorSesion): array
    {
        $request = Yii::$app->request;
        $idPesRaw = $request->get('id_profesional_efector_servicio') ?: $request->post('id_profesional_efector_servicio');
        if ($idPesRaw === null || $idPesRaw === '') {
            throw new BadRequestHttpException('Indique id_profesional_efector_servicio.');
        }
        $pes = ProfesionalEfectorServicio::findOne(['id' => (int) $idPesRaw, 'deleted_at' => null]);
        if ($pes === null) {
            throw new BadRequestHttpException('id_profesional_efector_servicio inválido.');
        }
        if ((int) $pes->id_efector !== (int) $idEfectorSesion) {
            throw new BadRequestHttpException('La asignación no corresponde al efector de sesión.');
        }

        return [(int) $pes->id_persona, (int) $pes->id_efector];
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
     * @return list<array{id:int,name:string,meta:array{id_profesional_efector_servicio:int, acepta_turnos:string}}>
     */
    private function serviciosAsignadosItemsForPersonaEfector(int $idPersona, int $idEfector, bool $incluirSinAgenda = false): array
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
            if (!$incluirSinAgenda && (int) $pes->id_servicio === 62) {
                continue;
            }
            $nombre = $pes->servicio !== null ? (string) $pes->servicio->nombre : ('Servicio #' . $pes->id_servicio);
            $acepta = $pes->servicio !== null && strtoupper(trim((string) $pes->servicio->acepta_turnos)) === 'SI' ? 'SI' : 'NO';
            $items[] = [
                'id' => (int) $pes->id_servicio,
                'name' => $nombre,
                'meta' => [
                    'id_profesional_efector_servicio' => (int) $pes->id,
                    'acepta_turnos' => $acepta,
                ],
            ];
        }

        return $items;
    }

    /**
     * GET /api/v1/profesional-efector-servicio/condiciones-laborales-catalogo
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
     * UI JSON: cargar licencia / permiso sobre asignación propia (PES en sesión).
     *
     * GET|POST /api/v1/profesional-efector-servicio/cargar-licencia-como-profesional
     *
     * @action_name Cargar licencia (profesional)
     * @entity Profesional
     * @tags licencia, permiso, vacaciones, profesional
     * @keywords cargar licencia, solicitar licencia, pedir permiso, ausencia, vacaciones
     */
    public function actionCargarLicenciaComoProfesional(): array
    {
        $req = Yii::$app->request;
        $idEfector = (int) Yii::$app->user->getIdEfector();
        $fromClient = array_merge($req->get(), $req->isPost ? $req->post() : []);

        if ($req->isPost) {
            return LicenciaUiFlowService::handlePost(
                $idEfector,
                $fromClient,
                'profesional-efector-servicio',
                'cargar-licencia-como-profesional',
                'licencia.cargar-como-profesional-flow',
                true
            );
        }

        return LicenciaUiFlowService::renderGet(
            $idEfector,
            $fromClient,
            'profesional-efector-servicio',
            'cargar-licencia-como-profesional',
            'licencia.cargar-como-profesional-flow',
            true
        );
    }

    /**
     * UI JSON: cargar licencia / permiso de un profesional del efector (staff).
     *
     * GET|POST /api/v1/profesional-efector-servicio/cargar-licencia-para-profesional
     *
     * @action_name Cargar licencia (staff)
     * @entity Profesional
     * @tags licencia, permiso, vacaciones, staff
     * @keywords cargar licencia profesional, registrar licencia, permiso staff
     */
    public function actionCargarLicenciaParaProfesional(): array
    {
        $req = Yii::$app->request;
        $idEfector = (int) Yii::$app->user->getIdEfector();
        $fromClient = array_merge($req->get(), $req->isPost ? $req->post() : []);

        if ($req->isPost) {
            return LicenciaUiFlowService::handlePost(
                $idEfector,
                $fromClient,
                'profesional-efector-servicio',
                'cargar-licencia-para-profesional',
                'licencia.cargar-para-profesional-flow',
                false
            );
        }

        return LicenciaUiFlowService::renderGet(
            $idEfector,
            $fromClient,
            'profesional-efector-servicio',
            'cargar-licencia-para-profesional',
            'licencia.cargar-para-profesional-flow',
            false
        );
    }

    /**
     * POST /api/v1/profesional-efector-servicio/preview-impacto-licencia
     *
     * Simula turnos afectados por licencia sin persistir.
     */
    public function actionPreviewImpactoLicencia(): array
    {
        $req = Yii::$app->request;
        if (!$req->isPost) {
            throw new MethodNotAllowedHttpException(['POST'], 'Solo POST.');
        }
        $idEfector = (int) Yii::$app->user->getIdEfector();
        $post = array_merge($req->get(), $req->post());
        $intentId = trim((string) ($post['intent_id'] ?? ''));
        $allowOwn = $intentId === '' || !str_contains($intentId, 'para-profesional');
        $defaultIntent = $intentId !== ''
            ? $intentId
            : ($allowOwn ? 'licencia.cargar-como-profesional-flow' : 'licencia.cargar-para-profesional-flow');

        return [
            'success' => true,
            'kind' => 'licencia_impact_preview',
            'data' => LicenciaUiFlowService::previewImpacto($idEfector, $post, $defaultIntent, $allowOwn),
        ];
    }

    /**
     * Cierre declarativo del flujo asistente «alta profesional en efector» (solo POST; sin descriptor UI).
     * Permiso RBAC: `/api/profesional-efector-servicio/crear-flow` (alineado al YAML `profesional-efector-servicio.crear-flow`).
     *
     * POST /api/v1/profesional-efector-servicio/crear-flow
     *
     * @action_name Cerrar flujo alta profesional en efector (asistente)
     * @entity ProfesionalEfectorServicio
     * @tags profesional, asistente, flow
     */
    public function actionCrearFlow(): array
    {
        $req = Yii::$app->request;
        if (!$req->isPost) {
            throw new MethodNotAllowedHttpException(['POST'], 'Este endpoint solo acepta POST (cierre del flujo del asistente).');
        }
        $post = $req->post();
        ApiDomainOperationBridge::assertOrForbidden(
            'ProfesionalEfectorServicio.create',
            $post,
            array_merge($req->get(), $post)
        );
        $idEfector = (int) Yii::$app->user->getIdEfector();

        $idPes = (int) ($post['id_profesional_efector_servicio'] ?? 0);
        if ($idPes > 0) {
            $pes = ProfesionalEfectorServicio::findOne(['id' => $idPes, 'deleted_at' => null]);
            if ($pes === null || (int) $pes->id_efector !== $idEfector) {
                throw new ForbiddenHttpException('Asignación inválida para este efector.');
            }
        }

        return [
            'success' => true,
            'kind' => 'ui_submit_result',
            'action_id' => 'profesional-efector-servicio.crear-flow',
            'data' => [
                'success' => true,
                'message' => 'Flujo de alta completado.',
            ],
            'errors' => null,
        ];
    }

    /**
     * Cierre declarativo del flujo asistente «cargar licencia como profesional» (solo POST).
     * Permiso RBAC: `/api/profesional-efector-servicio/cargar-licencia-como-profesional-flow`
     *
     * POST /api/v1/profesional-efector-servicio/cargar-licencia-como-profesional-flow
     *
     * @action_name Cerrar flujo cargar licencia como profesional (asistente)
     * @entity Profesional
     * @tags licencia, asistente, flow, profesional
     */
    public function actionCargarLicenciaComoProfesionalFlow(): array
    {
        $req = Yii::$app->request;
        if (!$req->isPost) {
            throw new BadRequestHttpException('Este endpoint solo acepta POST (cierre del flujo del asistente).');
        }
        $idEfector = (int) Yii::$app->user->getIdEfector();
        if ($idEfector <= 0) {
            throw new BadRequestHttpException('Se requiere efector en sesión.');
        }

        return LicenciaUiFlowService::handleFlowSubmit(
            $idEfector,
            $req->post(),
            'licencia.cargar-como-profesional-flow',
            'profesional-efector-servicio.cargar-licencia-como-profesional-flow',
            true
        );
    }

    /**
     * Cierre declarativo del flujo asistente «cargar licencia para profesional» (staff; solo POST).
     * Permiso RBAC: `/api/profesional-efector-servicio/cargar-licencia-para-profesional-flow`
     *
     * POST /api/v1/profesional-efector-servicio/cargar-licencia-para-profesional-flow
     *
     * @action_name Cerrar flujo cargar licencia para profesional (asistente)
     * @entity Profesional
     * @tags licencia, asistente, flow, staff
     */
    public function actionCargarLicenciaParaProfesionalFlow(): array
    {
        $req = Yii::$app->request;
        if (!$req->isPost) {
            throw new BadRequestHttpException('Este endpoint solo acepta POST (cierre del flujo del asistente).');
        }
        $idEfector = (int) Yii::$app->user->getIdEfector();
        if ($idEfector <= 0) {
            throw new BadRequestHttpException('Se requiere efector en sesión.');
        }

        return LicenciaUiFlowService::handleFlowSubmit(
            $idEfector,
            $req->post(),
            'licencia.cargar-para-profesional-flow',
            'profesional-efector-servicio.cargar-licencia-para-profesional-flow',
            false
        );
    }

    /**
     * UI JSON: crear/editar condición laboral (vigencia) propia.
     *
     * GET|POST /api/v1/profesional-efector-servicio/editar-condicion-laboral
     *
     * @action_name Editar condición laboral (profesional)
     * @entity Profesional
     * @tags condicion-laboral, staff
     * @keywords condición laboral, vigencia, profesional
     */
    public function actionEditarCondicionLaboral(): array
    {
        $req = Yii::$app->request;
        $idEfector = (int) Yii::$app->user->getIdEfector();

        $fromClient = array_merge($req->get(), $req->isPost ? $req->post() : []);
        $defaults = ProfesionalEfectorServicioAgendaUiService::buildCondicionLaboralValuesForGet($idEfector, $fromClient);
        $paramsForRender = array_merge($defaults, $fromClient);

        return UiScreenService::handleScreen(
            'profesional-efector-servicio',
            'editar-condicion-laboral',
            $paramsForRender,
            $req->post(),
            static function (array $post) use ($idEfector): array {
                return ProfesionalEfectorServicioAgendaUiService::submitCondicionLaboral(
                    $idEfector,
                    $post,
                    'condicion-laboral.editar-propio'
                );
            }
        );
    }

    /**
     * UI JSON: crear condición laboral (vigencia) de un profesional (staff).
     * Nota: el submit es un upsert; este endpoint existe por claridad de intención.
     *
     * GET|POST /api/v1/profesional-efector-servicio/crear-condicion-laboral
     *
     * @action_name Crear condición laboral (profesional)
     * @entity Profesional
     * @tags condicion-laboral, staff
     * @keywords crear condición laboral, alta condición laboral, vigencia profesional
     */
    public function actionCrearCondicionLaboral(): array
    {
        $req = Yii::$app->request;
        $idEfector = (int) Yii::$app->user->getIdEfector();

        $fromClient = array_merge($req->get(), $req->isPost ? $req->post() : []);
        // Precarga si existe (upsert). Staff: no inferir PES del usuario en sesión.
        $defaults = ProfesionalEfectorServicioAgendaUiService::buildCondicionLaboralValuesForGet(
            $idEfector,
            $fromClient,
            false
        );
        $paramsForRender = array_merge($defaults, $fromClient);

        return UiScreenService::handleScreen(
            'profesional-efector-servicio',
            'crear-condicion-laboral',
            $paramsForRender,
            $req->post(),
            static function (array $post) use ($idEfector): array {
                return ProfesionalEfectorServicioAgendaUiService::submitCondicionLaboral(
                    $idEfector,
                    $post,
                    'condicion-laboral.editar-staff'
                );
            }
        );
    }

    /**
     * Carga CUIL de la persona antes del alta PES (flujo asistente).
     *
     * GET|POST /api/v1/profesional-efector-servicio/cargar-cuil-profesional
     */
    public function actionCargarCuilProfesional(): array
    {
        $req = Yii::$app->request;
        $idEfector = (int) Yii::$app->user->getIdEfector();
        $fromClient = array_merge($req->get(), $req->isPost ? $req->post() : []);
        $defaults = ProfesionalEfectorServicioCuilUiService::buildValuesForGet($idEfector, $fromClient);

        return UiScreenService::handleScreen(
            'profesional-efector-servicio',
            'cargar-cuil-profesional',
            array_merge($defaults, $fromClient),
            $req->post(),
            static function (array $post) use ($idEfector): array {
                ApiDomainOperationBridge::assertOrForbidden(
                    'ProfesionalEfectorServicio.create',
                    $post,
                    $post
                );

                return ProfesionalEfectorServicioCuilUiService::submit($idEfector, $post);
            }
        );
    }

    /**
     * Catálogo staff: códigos HealthcareService FHIR → servicio Bioenlace.
     *
     * GET|POST /api/v1/profesional-efector-servicio/listar-codigos-servicio-fhir
     */
    public function actionListarCodigosServicioFhir(): array
    {
        $req = Yii::$app->request;
        $idEfector = (int) Yii::$app->user->getIdEfector();
        $fromClient = array_merge($req->get(), $req->isPost ? $req->post() : []);

        return UiScreenService::handleScreen(
            'profesional-efector-servicio',
            'listar-codigos-servicio-fhir',
            FhirServiceCodeCatalogUiService::buildListValues($idEfector, $fromClient),
            $req->post(),
            static function (array $post): array {
                return ['data' => ['ok' => true]];
            }
        );
    }

    /**
     * Alta/actualización de código FHIR en catálogo de servicios.
     *
     * GET|POST /api/v1/profesional-efector-servicio/guardar-codigo-servicio-fhir
     */
    public function actionGuardarCodigoServicioFhir(): array
    {
        $req = Yii::$app->request;
        $idEfector = (int) Yii::$app->user->getIdEfector();
        $fromClient = array_merge($req->get(), $req->isPost ? $req->post() : []);

        return UiScreenService::handleScreen(
            'profesional-efector-servicio',
            'guardar-codigo-servicio-fhir',
            array_merge(
                FhirServiceCodeCatalogUiService::buildListValues($idEfector, $fromClient),
                $fromClient
            ),
            $req->post(),
            static function (array $post) use ($idEfector): array {
                ApiDomainOperationBridge::assertOrForbidden(
                    'ProfesionalEfectorServicio.create',
                    $post,
                    $post
                );

                return FhirServiceCodeCatalogUiService::submit($idEfector, $post);
            }
        );
    }

    /**
     * Vista embebible: listar profesionales por efector y servicio (obligatorios),
     * filtrando a servicios que aceptan turnos (`servicios.acepta_turnos = SI`).
     *
     * GET|POST /api/v1/profesional-efector-servicio/listar-por-efector-servicio-acepta-turnos
     *
     * @action_name Listar profesionales (acepta turnos) por efector y servicio
     * @entity Profesional
     * @tags views, ui, profesional
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
            'profesional-efector-servicio',
            'listar-por-efector-servicio-acepta-turnos',
            $req->get(),
            $req->post(),
            static function (array $post): array {
                return ['data' => ['ok' => true]];
            }
        );

        if (isset($ui['kind']) && $ui['kind'] === 'ui_definition' && isset($ui['ui_type']) && $ui['ui_type'] === 'ui_json') {
            $filters = $this->buildProfesionalAutocompleteFilters($idEfector, $idServicio);
            $q = $this->reqParamRaw('q');
            $ui = UiScreenService::withListBlockItems($ui, $this->profesionalItemsForUi($q, $filters));
        }

        return $ui;
    }
}
