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

    private function reqParam(string $name): ?string
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
        $filters = [];

        foreach ([
            'id_localidad',
            'id_departamento',
            'localidad_nombre',
            'departamento_nombre',
            'id_servicio',
            'dependencia',
            'tipologia',
            'estado',
        ] as $k) {
            $v = $this->reqParam($k);
            if ($v !== null) {
                $filters[$k] = $v;
            }
        }

        // Alias común de UI JSON: id_servicio_asignado => id_servicio (para turnos flow).
        $idServAsignado = $this->reqParam('id_servicio_asignado');
        if (!isset($filters['id_servicio']) && $idServAsignado !== null) {
            $filters['id_servicio'] = $idServAsignado;
        }

        if ($nearby) {
            $lat = $this->reqParam('latitud');
            $lng = $this->reqParam('longitud');
            if ($lat !== null && $lng !== null) {
                $filters['latitud'] = $lat;
                $filters['longitud'] = $lng;
                $filters['radio_km'] = $this->reqParam('radio_km') ?? '10';
                $filters['sort_by'] = 'distancia';
                $filters['sort_order'] = 'ASC';
            }
        }

        // Para listados UI: aumentar límite por defecto.
        $filters['limit'] = $this->reqParam('limit') ?? '200';

        return $filters;
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function efectoresItemsForUi(?string $q, array $filters): array
    {
        $rows = Efector::liveSearch($q, $filters);
        $out = [];
        foreach ($rows as $r) {
            if (!is_array($r)) {
                continue;
            }
            $id = isset($r['id']) ? trim((string) $r['id']) : '';
            $name = isset($r['text']) ? trim((string) $r['text']) : '';
            if ($id === '') {
                continue;
            }
            $out[] = [
                'id' => $id,
                'name' => $name !== '' ? $name : $id,
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
        // buildEfectoresSearchFilters() siempre aporta al menos limit, así que este guard deja de aplicar.
        if ($q === null && $filters === []) {
            return $out;
        }
        $data = Efector::liveSearch($q, $filters);
        return ['results' => array_values($data)];
    }

    /**
     * Vista embebible: elegir efector.
     *
     * GET|POST /api/v1/efectores/elegir
     *
     * @action_name Elegir efector
     * @entity Efectores
     * @tags views, ui, efector
     * @keywords elegir efector, hospital, centro de salud, efector
     */
    public function actionElegir(): array
    {
        $req = Yii::$app->request;
        $ui = UiScreenService::handleScreen(
            'efectores',
            'elegir',
            $req->get(),
            $req->post(),
            static function (array $post): array {
                return ['data' => ['ok' => true]];
            }
        );

        if (isset($ui['kind']) && $ui['kind'] === 'ui_definition' && isset($ui['ui_type']) && $ui['ui_type'] === 'ui_json') {
            $filters = $this->buildEfectoresSearchFilters(false);
            $q = $this->reqParam('q');
            $ui['items'] = $this->efectoresItemsForUi($q, $filters);
        }

        return $ui;
    }

    /**
     * Vista embebible: elegir efector cercano (usa latitud/longitud del cliente).
     *
     * GET|POST /api/v1/efectores/elegir-nearby
     *
     * @action_name Elegir efector cercano
     * @entity Efectores
     * @tags views, ui, efector, geolocalización
     * @keywords efectores cercanos, cerca, cerca de casa, hospital cercano, centro de salud cercano
     */
    public function actionElegirNearby(): array
    {
        $req = Yii::$app->request;
        $ui = UiScreenService::handleScreen(
            'efectores',
            'elegir-nearby',
            $req->get(),
            $req->post(),
            static function (array $post): array {
                return ['data' => ['ok' => true]];
            }
        );

        if (isset($ui['kind']) && $ui['kind'] === 'ui_definition' && isset($ui['ui_type']) && $ui['ui_type'] === 'ui_json') {
            $filters = $this->buildEfectoresSearchFilters(true);
            $q = $this->reqParam('q');
            $ui['items'] = $this->efectoresItemsForUi($q, $filters);
        }

        return $ui;
    }
}
