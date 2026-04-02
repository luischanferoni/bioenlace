<?php

namespace frontend\modules\api\v1\components;

use Yii;
use common\components\Actions\AllowedRoutesResolver;
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
     * Valida el Bearer JWT, establece la identidad del usuario y idPersona en sesión.
     * @inheritdoc
     */
    public function authenticate($user, $request, $response)
    {
        $response->format = Response::FORMAT_JSON;

        $authHeader = $request->getHeaders()->get('Authorization');
        if ($authHeader === null || !preg_match('/^Bearer\s+(.*?)$/', $authHeader, $matches)) {
            return null;
        }

        $token = $matches[1];
        try {
            $decoded = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key(Yii::$app->params['jwtSecret'], 'HS256'));
        } catch (\Exception $e) {
            $this->challenge($response);
            $this->handleFailure($response);
        }

        $userId = $decoded->user_id;
        $idPersona = $decoded->id_persona ?? null;

        $userModel = \webvimark\modules\UserManagement\models\User::findOne($userId);
        if (!$userModel) {
            $response->statusCode = 401;
            $response->data = [
                'success' => false,
                'message' => 'Usuario no encontrado',
                'errors' => null,
            ];
            $response->send();
            Yii::$app->end();
        }

        if ($userModel->status !== \webvimark\modules\UserManagement\models\User::STATUS_ACTIVE) {
            $response->statusCode = 401;
            $response->data = [
                'success' => false,
                'message' => 'Usuario inactivo',
                'errors' => null,
            ];
            $response->send();
            Yii::$app->end();
        }

        \common\models\BioenlaceDbManager::asignarRolPacienteSiNoExiste($userId);

        if ($idPersona) {
            $session = Yii::$app->session;
            if (!$session->isActive) {
                $session->open();
            }
            $session->set('idPersona', $idPersona);
        }

        $user->setIdentity($userModel);
        \webvimark\modules\UserManagement\components\AuthHelper::updatePermissions($user);
        AllowedRoutesResolver::markSessionRoutesOwner((int) $userModel->id);

        return $userModel;
    }
}

