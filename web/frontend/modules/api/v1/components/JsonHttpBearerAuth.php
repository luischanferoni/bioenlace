<?php

namespace frontend\modules\api\v1\components;

use Yii;
use common\components\Platform\Assistant\UiActions\AllowedRoutesResolver;
use common\models\Person\Persona;
use common\models\ProfesionalEfectorServicio;
use frontend\components\WebApiJwtSessionService;
use yii\filters\auth\HttpBearerAuth;
use yii\web\Request;
use yii\web\Response;

/**
 * HttpBearerAuth personalizado que siempre devuelve JSON en lugar de HTML.
 * Cliente web (X-Client: web): si falta Bearer o es inválido, usa apiJwtToken de la sesión PHP.
 */
class JsonHttpBearerAuth extends HttpBearerAuth
{
    /**
     * @inheritdoc
     */
    public function handleFailure($response)
    {
        $response->format = Response::FORMAT_JSON;
        $response->statusCode = 401;

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

        $token = $this->extractBearerToken($request);
        $decoded = null;

        if ($token !== null) {
            $decoded = $this->tryDecodeToken($token);
        }

        if ($decoded === null && $this->isWebClientRequest($request)) {
            $sessionToken = WebApiJwtSessionService::resolveTokenFromWebSession();
            if ($sessionToken !== null) {
                $token = $sessionToken;
                $decoded = $this->tryDecodeToken($token);
            }
        }

        if ($decoded === null) {
            if ($token !== null) {
                Yii::warning(
                    'JWT inválido en JsonHttpBearerAuth (Bearer y sesión web sin token válido).',
                    'auth.jwt'
                );
            }
            return null;
        }

        return $this->authenticateDecodedToken($user, $response, $decoded);
    }

    /**
     * @return object|null payload JWT decodificado
     */
    private function tryDecodeToken(string $token): ?object
    {
        try {
            return \Firebase\JWT\JWT::decode(
                $token,
                new \Firebase\JWT\Key(Yii::$app->params['jwtSecret'], 'HS256')
            );
        } catch (\Throwable $e) {
            Yii::debug(
                'JWT no decodificable: ' . get_class($e) . ' - ' . $e->getMessage(),
                'auth.jwt'
            );

            return null;
        }
    }

    private function extractBearerToken(Request $request): ?string
    {
        $authHeader = $request->getHeaders()->get('Authorization');
        if ($authHeader === null || !preg_match('/^Bearer\s+(.*?)$/', $authHeader, $matches)) {
            return null;
        }

        $token = trim($matches[1]);
        if ($token === '' || strtolower($token) === 'null') {
            return null;
        }

        return $token;
    }

    private function isWebClientRequest(Request $request): bool
    {
        $client = strtolower((string) $request->headers->get('X-Client', ''));
        if ($client === 'web') {
            return true;
        }

        $appClient = strtolower((string) $request->headers->get('X-App-Client', ''));

        return $appClient === 'web-frontend';
    }

    /**
     * @param object $decoded payload JWT
     * @return \common\models\User|null
     */
    private function authenticateDecodedToken($user, $response, object $decoded)
    {
        $userId = $decoded->user_id;
        $idPersonaClaim = isset($decoded->id_persona) ? (int) $decoded->id_persona : 0;

        $userModel = \common\models\User::findOne($userId);
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

        if ($userModel->status !== \common\models\User::STATUS_ACTIVE) {
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
            $session->set('efectores', ProfesionalEfectorServicio::getEfectoresParaSesion((int) $persona->id_persona));
        }

        try {
            if (isset($decoded->id_efector)) {
                Yii::$app->user->setIdEfector((int) $decoded->id_efector);
            }
            if (isset($decoded->servicio_actual)) {
                Yii::$app->user->setServicioActual((int) $decoded->servicio_actual);
            }
            if (isset($decoded->id_profesional_efector_servicio)) {
                Yii::$app->user->setIdProfesionalEfectorServicio((int) $decoded->id_profesional_efector_servicio);
            }
            if (isset($decoded->encounter_class)) {
                Yii::$app->user->setEncounterClass((string) $decoded->encounter_class);
            }
        } catch (\Throwable $e) {
            Yii::debug('No se pudo aplicar contexto desde JWT: ' . $e->getMessage(), 'auth.jwt');
        }

        $user->setIdentity($userModel);
        \common\components\Platform\Core\Permission\BioenlaceAccessChecker::refreshForIdentity($userModel);
        AllowedRoutesResolver::markSessionRoutesOwner((int) $userModel->id);

        return $userModel;
    }
}
