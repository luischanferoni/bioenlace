<?php

namespace frontend\controllers;

use Yii;
use yii\web\Controller;
use common\components\Organization\Service\ProfesionalEfectorServicio\ProfesionalEnEfectorListadoUiService;

/**
 * Endpoint MVC mínimo para autocomplete PES en formularios legacy (encuesta mamaria).
 * Equivalente API: GET/POST /api/v1/profesional-efector-servicio/autocomplete.
 */
class AsignacionPesController extends Controller
{
    /**
     * Autocomplete Select2.
     *
     * @no_intent_catalog
     */
    public function actionProfesionalesAutocomplete($q = null)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $request = Yii::$app->request;
        $idEfector = $request->get('id_efector') ?: $request->post('id_efector');
        $idServicio = $request->get('id_servicio') ?: $request->post('id_servicio');
        if ($idEfector === null || $idEfector === '' || $idServicio === null || $idServicio === '') {
            return ['results' => []];
        }
        $q = $q ?? $request->get('q') ?? $request->post('q');
        if ($q === null || trim((string) $q) === '') {
            return ['results' => []];
        }
        $filters = [
            'id_efector' => $idEfector,
            'id_servicio' => $idServicio,
        ];

        return ['results' => array_values(ProfesionalEnEfectorListadoUiService::autocompletePorEfectorServicio((string) $q, $filters))];
    }
}
