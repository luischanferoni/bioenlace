<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use common\models\RrhhEfector;

class RrhhController extends BaseController
{
    public $modelClass = 'common\models\RrhhEfector';
    
    /**
     * Sobrescribir behaviors para permitir acceso sin autenticación a rrhh-autocomplete
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        
        // Permitir acceso sin autenticación a rrhh-autocomplete (búsqueda pública de recursos humanos)
        $behaviors['authenticator']['except'] = ['options', 'rrhh-autocomplete'];
        
        return $behaviors;
    }
    
    /**
     * Búsqueda de recursos humanos con filtros avanzados
     * GET /api/v1/rrhh/rrhh-autocomplete?q=Juan&limit=5&sort_by=apellido&sort_order=ASC
     */
    public function actionRrhhAutocomplete()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        $request = Yii::$app->request;
        
        // Obtener query de GET o POST
        $q = $request->get('q') ?: $request->post('q');
        
        // Recopilar todos los filtros de los parámetros GET/POST
        $filters = [];
        
        // Filtro por efector
        if ($request->get('id_efector') || $request->post('id_efector')) {
            $filters['id_efector'] = $request->get('id_efector') ?: $request->post('id_efector');
        }
        
        // Filtro por servicio
        if ($request->get('id_servicio') || $request->post('id_servicio')) {
            $filters['id_servicio'] = $request->get('id_servicio') ?: $request->post('id_servicio');
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
            return $this->success(['results' => [['id' => '', 'text' => '']]]);
        }
        
        $data = RrhhEfector::autocompleteRrhh($q, $filters);

        return $this->success(['results' => array_values($data)]);
    }
}
