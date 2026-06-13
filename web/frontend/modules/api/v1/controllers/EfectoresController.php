<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use common\components\Organization\Service\Efectores\EfectoresListadosService;
use common\components\Organization\Service\ProfesionalEfectorServicio\ProfesionalEnEfectorListadoUiService;
use common\components\Ui\UiScreenService;
use yii\web\BadRequestHttpException;

/**
 * API Efectores: búsqueda de efectores.
 * Lógica migrada desde frontend\controllers\EfectoresController::actionSearch.
 */
class EfectoresController extends BaseController
{
    public static $authenticatorExcept = ['search'];

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
    private function buildEfectoresSearchFilters(bool $nearby = false): array
    {
        return EfectoresListadosService::extractFilters(Yii::$app->request, $nearby);
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function efectoresItemsForUi(?string $q, array $filters): array
    {
        return EfectoresListadosService::itemsForUi($q, $filters);
    }

    public function actions()
    {
        $actions = parent::actions();
        unset($actions['index'], $actions['view'], $actions['create'], $actions['update']);

        return $actions;
    }

    /**
     * GET/POST /api/v1/efectores/buscar
     * Parámetros: q (opcional), id_localidad, id_departamento, id_servicio, dependencia, tipologia, estado,
     * latitud, longitud, radio_km, limit, sort_by, sort_order, etc.
     */
    public function actionBuscar($q = null)
    {
        $out = ['results' => ['id' => '', 'text' => '']];
        if ($q === null) {
            $q = Yii::$app->request->get('q') ?: Yii::$app->request->post('q');
        }
        $filters = $this->buildEfectoresSearchFilters(false);
        if ($q === null && $filters === []) {
            return $out;
        }
        $data = \common\models\Efector::liveSearch($q, $filters);

        return ['results' => array_values($data)];
    }

    /**
     * Vista embebible: listar efectores filtrados por servicio (obligatorio).
     *
     * GET|POST /api/v1/efectores/listar-por-servicio
     *
     * @action_name Listar efectores por servicio
     * @entity Efectores
     * @tags views, ui, efector
     * @keywords efectores, servicio, centro de salud
     */
    public function actionListarPorServicio(): array
    {
        $req = Yii::$app->request;
        $idServicio = EfectoresListadosService::requireServicioId($req);
        if ($idServicio === null) {
            throw new BadRequestHttpException('Se requiere id_servicio o id_servicio_asignado.');
        }

        $ui = UiScreenService::handleScreen(
            'efectores',
            'listar-por-servicio',
            $req->get(),
            $req->post(),
            static function (array $post): array {
                return ['data' => ['ok' => true]];
            }
        );

        if (isset($ui['kind']) && $ui['kind'] === 'ui_definition' && isset($ui['ui_type']) && $ui['ui_type'] === 'ui_json') {
            $filters = EfectoresListadosService::extractFilters($req, false);
            $filters['id_servicio'] = $idServicio;
            $q = $this->reqParamRaw('q');
            $ui = UiScreenService::withListBlockItems($ui, $this->efectoresItemsForUi($q, $filters));
        }

        return $ui;
    }

    /**
     * Vista embebible: listar efectores por servicio ordenados por cercanía (lat/lng obligatorios).
     *
     * GET|POST /api/v1/efectores/listar-por-servicio-cercano
     *
     * @action_name Listar efectores por servicio cercanos
     * @entity Efectores
     * @tags views, ui, efector, geolocalización
     * @keywords efectores cercanos, cerca, servicio
     */
    public function actionListarPorServicioCercano(): array
    {
        $req = Yii::$app->request;
        $idServicio = EfectoresListadosService::requireServicioId($req);
        if ($idServicio === null) {
            throw new BadRequestHttpException('Se requiere id_servicio o id_servicio_asignado.');
        }
        [$lat, $lng] = EfectoresListadosService::requireLatLng($req);
        if ($lat === null || $lng === null) {
            throw new BadRequestHttpException('Se requiere latitud y longitud.');
        }

        $ui = UiScreenService::handleScreen(
            'efectores',
            'listar-por-servicio-cercano',
            $req->get(),
            $req->post(),
            static function (array $post): array {
                return ['data' => ['ok' => true]];
            }
        );

        if (isset($ui['kind']) && $ui['kind'] === 'ui_definition' && isset($ui['ui_type']) && $ui['ui_type'] === 'ui_json') {
            $filters = EfectoresListadosService::extractFilters($req, true);
            $filters['id_servicio'] = $idServicio;
            $q = $this->reqParamRaw('q');
            $ui = UiScreenService::withListBlockItems($ui, $this->efectoresItemsForUi($q, $filters));
        }

        return $ui;
    }

    /**
     * Solo datos: efectores por servicio (sin plantilla `views/json`, no se infiere ui_json).
     *
     * GET|POST /api/v1/efectores/listar-datos-por-servicio
     */
    public function actionListarDatosPorServicio(): array
    {
        $req = Yii::$app->request;
        $idServicio = EfectoresListadosService::requireServicioId($req);
        if ($idServicio === null) {
            throw new BadRequestHttpException('Se requiere id_servicio o id_servicio_asignado.');
        }
        $filters = EfectoresListadosService::extractFilters($req, false);
        $filters['id_servicio'] = $idServicio;
        $q = $this->reqParamRaw('q');
        $rows = \common\models\Efector::liveSearch($q, $filters);

        return ['results' => array_values($rows)];
    }

    /**
     * Vista embebible: servicios habilitados que ofrece el efector en sesión ({@see ServiciosEfector}).
     *
     * - Por defecto usa `id_efector` de sesión.
     * - Opcional: `id_efector` en query/body debe coincidir con el de sesión (evita fuga entre establecimientos).
     *
     * GET|POST /api/v1/efectores/listar-servicios-habilitados
     *
     * @action_name Listar servicios habilitados del efector
     * @entity Efectores
     * @tags views, ui, servicios, efector, asistente
     */
    public function actionListarServiciosHabilitados(): array
    {
        $req = Yii::$app->request;
        $ui = UiScreenService::handleScreen(
            'efectores',
            'listar-servicios-habilitados',
            $req->get(),
            $req->post(),
            static function (array $post): array {
                return ['data' => ['ok' => true]];
            }
        );

        if (isset($ui['kind']) && $ui['kind'] === 'ui_definition' && isset($ui['ui_type']) && $ui['ui_type'] === 'ui_json') {
            $idEfectorSesion = (int) Yii::$app->user->getIdEfector();
            if ($idEfectorSesion <= 0) {
                throw new BadRequestHttpException('No hay efector en sesión.');
            }
            $idEfectorParam = $req->get('id_efector');
            if ($idEfectorParam === null || $idEfectorParam === '') {
                $idEfectorParam = $req->post('id_efector');
            }
            if ($idEfectorParam !== null && $idEfectorParam !== '' && (int) $idEfectorParam !== $idEfectorSesion) {
                throw new BadRequestHttpException('id_efector no coincide con el efector en sesión.');
            }

            $q = $req->get('q') ?: $req->post('q');
            try {
                $uiItems = ProfesionalEnEfectorListadoUiService::uiJsonItemsServiciosHabilitadosEfector(
                    $idEfectorSesion,
                    is_string($q) ? $q : null
                );
            } catch (\InvalidArgumentException $e) {
                throw new BadRequestHttpException($e->getMessage());
            }
            $ui = UiScreenService::withListBlockItems($ui, $uiItems);
        }

        return $ui;
    }
}
