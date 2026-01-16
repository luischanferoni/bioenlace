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
            
            // Si la autenticación fue exitosa, verificar status y asignar roles
            $authHeader = $request->getHeaders()->get('Authorization');
            if ($authHeader && preg_match('/^Bearer\s+(.*?)$/', $authHeader, $matches)) {
                $token = $matches[1];
                try {
                    $decoded = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key(Yii::$app->params['jwtSecret'], 'HS256'));
                    $userId = $decoded->user_id;
                    $idPersona = $decoded->id_persona ?? null; // Obtener id_persona del token
                    
                    // Verificar que el usuario existe y está activo
                    $userModel = \webvimark\modules\UserManagement\models\User::findOne($userId);
                    if (!$userModel) {
                        $response->format = Response::FORMAT_JSON;
                        $response->statusCode = 401;
                        $response->data = [
                            'success' => false,
                            'message' => 'Usuario no encontrado',
                            'errors' => null,
                        ];
                        $response->send();
                        Yii::$app->end();
                    }
                    
                    // Verificar status del usuario
                    if ($userModel->status !== \webvimark\modules\UserManagement\models\User::STATUS_ACTIVE) {
                        $response->format = Response::FORMAT_JSON;
                        $response->statusCode = 401;
                        $response->data = [
                            'success' => false,
                            'message' => 'Usuario inactivo',
                            'errors' => null,
                        ];
                        $response->send();
                        Yii::$app->end();
                    }
                    
                    // Asignar rol "paciente" por defecto si no lo tiene
                    \common\models\SisseDbManager::asignarRolPacienteSiNoExiste($userId);
                    
                    // Asignar idPersona a la sesión desde el token (sin buscar en BD)
                    if ($idPersona) {
                        $session = Yii::$app->session;
                        if (!$session->isActive) {
                            $session->open();
                        }
                        $session->set('idPersona', $idPersona);
                    }
                    
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

