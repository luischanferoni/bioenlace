<?php

namespace frontend\modules\api\v1\components;

use Yii;
use yii\filters\auth\HttpBearerAuth;
use yii\web\UnauthorizedHttpException;
use yii\web\Response;

/**
 * HttpBearerAuth personalizado que siempre devuelve JSON en lugar de HTML
 */
class JsonHttpBearerAuth extends HttpBearerAuth
{
    /**
     * @inheritdoc
     */
    public function handleFailure($response)
    {
        // Forzar formato JSON antes de lanzar la excepción
        $response->format = Response::FORMAT_JSON;
        $response->statusCode = 401;
        
        // Enviar respuesta JSON directamente
        $response->data = [
            'success' => false,
            'message' => 'Su solicitud fue hecha con credenciales inválidas. Verifique que el token de autenticación sea válido.',
            'errors' => null,
        ];
        
        $response->send();
        Yii::$app->end();
    }
    
    /**
     * @inheritdoc
     */
    public function authenticate($user, $request, $response)
    {
        // Asegurar formato JSON antes de autenticar
        $response->format = Response::FORMAT_JSON;
        
        try {
            return parent::authenticate($user, $request, $response);
        } catch (UnauthorizedHttpException $e) {
            // Si falla la autenticación, devolver JSON
            $response->format = Response::FORMAT_JSON;
            $response->statusCode = 401;
            $response->data = [
                'success' => false,
                'message' => $e->getMessage() ?: 'Usuario no autenticado',
                'errors' => null,
            ];
            $response->send();
            Yii::$app->end();
        }
    }
}

