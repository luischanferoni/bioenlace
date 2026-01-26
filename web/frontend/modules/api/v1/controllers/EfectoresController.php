<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use common\models\Efector;

class EfectoresController extends BaseController
{
    public $modelClass = 'common\models\Efector';
    
    /**
     * Sobrescribir behaviors para permitir acceso sin autenticación a search
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        
        // Permitir acceso sin autenticación a search (búsqueda pública de efectores)
        $behaviors['authenticator']['except'] = ['options', 'search'];
        
        return $behaviors;
    }
    
    /**
     * Búsqueda de efectores con filtros avanzados
     * GET /api/v1/efectores/search?q=hospital&limit=5&sort_by=nombre&sort_order=ASC
     */
    public function actionSearch()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        $request = Yii::$app->request;
        
        // Obtener query de GET o POST
        $q = $request->get('q') ?: $request->post('q');
        
        // Recopilar todos los filtros de los parámetros GET/POST
        $filters = [];
        
        // Filtros de localización
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
        
        // Filtro por servicio
        if ($request->get('id_servicio') || $request->post('id_servicio')) {
            $filters['id_servicio'] = $request->get('id_servicio') ?: $request->post('id_servicio');
        }
        
        // Filtros de características del efector
        if ($request->get('dependencia') || $request->post('dependencia')) {
            $filters['dependencia'] = $request->get('dependencia') ?: $request->post('dependencia');
        }
        if ($request->get('tipologia') || $request->post('tipologia')) {
            $filters['tipologia'] = $request->get('tipologia') ?: $request->post('tipologia');
        }
        if ($request->get('estado') || $request->post('estado')) {
            $filters['estado'] = $request->get('estado') ?: $request->post('estado');
        }
        
        // Filtro por geolocalización
        $lat = $request->get('latitud') ?: $request->post('latitud');
        $lng = $request->get('longitud') ?: $request->post('longitud');
        if ($lat && $lng) {
            $filters['latitud'] = $lat;
            $filters['longitud'] = $lng;
            $filters['radio_km'] = $request->get('radio_km') ?: $request->post('radio_km') ?: 10; // Por defecto 10 km
        }
        
        // Límite de resultados
        if ($request->get('limit') || $request->post('limit')) {
            $filters['limit'] = $request->get('limit') ?: $request->post('limit');
        }
        
        // Parámetros de ordenamiento
        if ($request->get('sort_by') || $request->post('sort_by')) {
            $filters['sort_by'] = $request->get('sort_by') ?: $request->post('sort_by');
        }
        if ($request->get('sort_order') || $request->post('sort_order')) {
            $filters['sort_order'] = $request->get('sort_order') ?: $request->post('sort_order');
        }
        
        // Permitir búsqueda sin query si hay filtros o limit
        // Si no hay query ni filtros, retornar vacío
        if (is_null($q) && empty($filters)) {
            return $this->success(['results' => []]);
        }

        $data = Efector::liveSearch($q, $filters);

        return $this->success(['results' => array_values($data)]);
    }
}
