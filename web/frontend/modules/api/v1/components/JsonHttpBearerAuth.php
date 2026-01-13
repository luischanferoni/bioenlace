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
            $identity = parent::authenticate($user, $request, $response);
            
            // Si la autenticación fue exitosa, obtener id_user del token JWT
            // y buscar id_persona para asignarlo a la sesión
            $authHeader = $request->getHeaders()->get('Authorization');
            if ($authHeader && preg_match('/^Bearer\s+(.*?)$/', $authHeader, $matches)) {
                $token = $matches[1];
                try {
                    $decoded = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key(Yii::$app->params['jwtSecret'], 'HS256'));
                    $userId = $decoded->user_id;
                    
                    // Buscar persona asociada al usuario
                    $persona = \common\models\Persona::findOne(['id_user' => $userId]);
                    if (!$persona) {
                        // Si no se encuentra la persona, lanzar error
                        $response->format = Response::FORMAT_JSON;
                        $response->statusCode = 401;
                        $response->data = [
                            'success' => false,
                            'message' => 'No se encontró la persona asociada al usuario. Verifique la configuración del usuario.',
                            'errors' => null,
                        ];
                        $response->send();
                        Yii::$app->end();
                    }
                    
                    // Asignar idPersona a la sesión
                    $session = Yii::$app->session;
                    if (!$session->isActive) {
                        $session->open();
                    }
                    $session->set('idPersona', $persona->id_persona);
                } catch (\Exception $e) {
                    // Si hay error decodificando el token, continuar (ya está autenticado por parent)
                    // Pero registrar el error
                    Yii::warning("Error al procesar token JWT después de autenticación: " . $e->getMessage(), 'jwt-auth');
                }
            }
            
            return $identity;
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

