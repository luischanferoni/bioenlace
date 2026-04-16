<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use common\components\UiScreenService;
use common\models\Efector;

/**
 * API Efectores: búsqueda de efectores.
 * Lógica migrada desde frontend\controllers\EfectoresController::actionSearch.
 */
class EfectoresController extends BaseController
{
    public static $authenticatorExcept = ['search'];

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
        $request = Yii::$app->request;
        $filters = [];
        if ($request->get('id_localidad') || $request->post('id_localidad')) {
            $filters['id_localidad'] = $request->get('id_localidad') ?: $request->post('id_localidad');
        }
        if ($request->get('id_departamento') || $request->post('id_departamento')) {
            $filters['id_departamento'] = $request->get('id_departamento') ?: $request->post('id_departamento');
        }
        if ($request->get('localidad_nombre') || $request->post('localidad_nombre')) {
            $filters['localidad_nombre'] = $request->get('localidad_nombre') ?: $request->post('localidad_nombre');
        }
        if ($request->get('departamento_nombre') || $request->post('departamento_nombre')) {
            $filters['departamento_nombre'] = $request->get('departamento_nombre') ?: $request->post('departamento_nombre');
        }
        if ($request->get('id_servicio') || $request->post('id_servicio')) {
            $filters['id_servicio'] = $request->get('id_servicio') ?: $request->post('id_servicio');
        }
        if ($request->get('dependencia') || $request->post('dependencia')) {
            $filters['dependencia'] = $request->get('dependencia') ?: $request->post('dependencia');
        }
        if ($request->get('tipologia') || $request->post('tipologia')) {
            $filters['tipologia'] = $request->get('tipologia') ?: $request->post('tipologia');
        }
        if ($request->get('estado') || $request->post('estado')) {
            $filters['estado'] = $request->get('estado') ?: $request->post('estado');
        }
        $lat = $request->get('latitud') ?: $request->post('latitud');
        $lng = $request->get('longitud') ?: $request->post('longitud');
        if ($lat && $lng) {
            $filters['latitud'] = $lat;
            $filters['longitud'] = $lng;
            $filters['radio_km'] = $request->get('radio_km') ?: $request->post('radio_km') ?: 10;
        }
        if ($request->get('limit') || $request->post('limit')) {
            $filters['limit'] = $request->get('limit') ?: $request->post('limit');
        }
        if ($request->get('sort_by') || $request->post('sort_by')) {
            $filters['sort_by'] = $request->get('sort_by') ?: $request->post('sort_by');
        }
        if ($request->get('sort_order') || $request->post('sort_order')) {
            $filters['sort_order'] = $request->get('sort_order') ?: $request->post('sort_order');
        }
        if (is_null($q) && empty($filters)) {
            return $out;
        }
        $data = Efector::liveSearch($q, $filters);
        return ['results' => array_values($data)];
    }

    /**
     * Vista embebible: elegir efector.
     *
     * GET|POST /api/v1/views/efectores/elegir
     *
     * @action_name Elegir efector
     * @entity Efectores
     * @tags views, ui, efector
     * @keywords elegir efector, hospital, centro de salud, efector
     */
    public function actionElegir(): array
    {
        $req = Yii::$app->request;
        return UiScreenService::handleScreen(
            'efectores',
            'elegir',
            $req->get(),
            $req->post(),
            static function (array $post): array {
                return ['data' => ['ok' => true]];
            }
        );
    }

    /**
     * Vista embebible: elegir efector cercano (usa latitud/longitud del cliente).
     *
     * GET|POST /api/v1/views/efectores/elegir-nearby
     *
     * @action_name Elegir efector cercano
     * @entity Efectores
     * @tags views, ui, efector, geolocalización
     * @keywords efectores cercanos, cerca, cerca de casa, hospital cercano, centro de salud cercano
     */
    public function actionElegirNearby(): array
    {
        $req = Yii::$app->request;
        return UiScreenService::handleScreen(
            'efectores',
            'elegir-nearby',
            $req->get(),
            $req->post(),
            static function (array $post): array {
                return ['data' => ['ok' => true]];
            }
        );
    }
}
