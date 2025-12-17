<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use frontend\modules\api\v1\controllers\BaseController;

class CrudController extends BaseController
{
    public $modelClass = ''; // No usamos ActiveController para este endpoint
    public $enableCsrfValidation = false; // Deshabilitar CSRF para API

    /**
     * Configurar behaviors para permitir sesiones web además de Bearer token
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        
        // Permitir acceso sin autenticación Bearer si hay sesión activa
        // El método actionProcessQuery verificará manualmente la autenticación
        $behaviors['authenticator']['except'] = ['options', 'process-query'];
        
        // CORS ya está configurado en BaseController, no es necesario redefinirlo
        
        return $behaviors;
    }

    /**
     * Procesar consulta en lenguaje natural usando UniversalQueryAgent
     * 
     * Este endpoint procesa consultas en lenguaje natural y devuelve acciones relevantes
     * del sistema que el usuario tiene permitido realizar.
     * 
     * Ejemplos de consultas:
     * - "listame mis licencias"
     * - "29486884" (búsqueda por DNI)
     * - "cuántos consultas voy atendiendo este mes?"
     * - "qué puedo hacer?"
     * 
     * @return array Respuesta con acciones encontradas o error
     */
    public function actionProcessQuery()
    {
        // Verificar autenticación usando el método de BaseController
        $authError = $this->requerirAutenticacion();
        if ($authError !== null) {
            return $authError;
        }
        
        // Obtener el userId usando el método de BaseController
        $auth = $this->verificarAutenticacion();
        $userId = $auth['userId'];

        $query = Yii::$app->request->post('query');
        
        if (empty($query)) {
            return $this->error('La consulta no puede estar vacía', null, 400);
        }

        try {
            // Procesar consulta usando UniversalQueryAgent (implementación genérica y mejorada)
            $result = \common\components\UniversalQueryAgent::processQuery($query, $userId);
            
            // Asegurar que el resultado tenga el formato correcto
            if (isset($result['success'])) {
                return $result;
            }
            
            // Si no tiene formato estándar, envolverlo
            return $this->success($result);
        } catch (\Exception $e) {
            Yii::error("Error procesando consulta: " . $e->getMessage(), 'api-crud-controller');
            return $this->error('Error al procesar la consulta. Por favor, intente nuevamente.', null, 500);
        }
    }

    /**
     * Deshabilitar acciones por defecto de ActiveController
     */
    public function actions()
    {
        return [];
    }
}

