<?php

namespace frontend\modules\api\v1\components;

use Yii;
use common\components\Actions\AllowedRoutesResolver;
use common\models\Persona;
use common\models\RrhhEfector;
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
        $idPersonaClaim = isset($decoded->id_persona) ? (int) $decoded->id_persona : 0;

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

        $session = Yii::$app->session;
        if (!$session->isActive) {
            $session->open();
        }

        $persona = null;
        if ($idPersonaClaim > 0) {
            $persona = Persona::findOne($idPersonaClaim);
            if ($persona && (int) $persona->id_user !== (int) $userModel->id) {
                $response->statusCode = 401;
                $response->data = [
                    'success' => false,
                    'message' => 'El token no coincide con la identidad del usuario.',
                    'errors' => null,
                ];
                $response->send();
                Yii::$app->end();
            }
        }
        if (!$persona && (int) ($userModel->superadmin ?? 0) !== 1) {
            $persona = Persona::findOne(['id_user' => $userModel->id]);
        }
        if (!$persona && (int) ($userModel->superadmin ?? 0) !== 1) {
            $response->statusCode = 401;
            $response->data = [
                'success' => false,
                'message' => 'Cuenta sin persona asociada. Comuníquese con administración.',
                'errors' => null,
            ];
            $response->send();
            Yii::$app->end();
        }
        if ($persona !== null) {
            $session->set('idPersona', (int) $persona->id_persona);
            $session->set('apellidoUsuario', $persona->apellido);
            $session->set('nombreUsuario', $persona->nombre);
            $session->set('efectores', RrhhEfector::getEfectores($persona->id_persona));
        }

        $user->setIdentity($userModel);
        \webvimark\modules\UserManagement\components\AuthHelper::updatePermissions($user);
        AllowedRoutesResolver::markSessionRoutesOwner((int) $userModel->id);

        return $userModel;
    }
}

