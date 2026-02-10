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
        
        // id_efector e id_servicio son requeridos
        $idEfector = $request->get('id_efector') ?: $request->post('id_efector');
        $idServicio = $request->get('id_servicio') ?: $request->post('id_servicio');
        if (empty($idEfector) || empty($idServicio)) {
            return $this->error('id_efector e id_servicio son requeridos', null, 422);
        }
        
        // Query de búsqueda: nombre del profesional (GET o POST). Si no viene, se devuelven todos.
        $q = $request->get('q') ?: $request->post('q');
        
        $filters = [
            'id_efector' => $idEfector,
            'id_servicio' => $idServicio,
        ];
        
        if ($request->get('limit') || $request->post('limit')) {
            $filters['limit'] = $request->get('limit') ?: $request->post('limit');
        }
        if ($request->get('sort_by') || $request->post('sort_by')) {
            $filters['sort_by'] = $request->get('sort_by') ?: $request->post('sort_by');
        }
        if ($request->get('sort_order') || $request->post('sort_order')) {
            $filters['sort_order'] = $request->get('sort_order') ?: $request->post('sort_order');
        }
        
        $data = RrhhEfector::autocompleteRrhh($q, $filters);

        return $this->success(['results' => array_values($data)]);
    }
}
