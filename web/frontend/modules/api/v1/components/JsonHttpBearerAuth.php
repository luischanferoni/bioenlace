<?php

namespace frontend\modules\api\v1\components;

use Yii;
use common\components\Assistant\UiActions\AllowedRoutesResolver;
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
            // Diagnóstico: si el cliente envía un token inválido/expirado/firmado con otro secret,
            // terminaremos aquí con 401. Loguear el tipo de error ayuda a depurar sin exponer el token.
            Yii::warning(
                'JWT inválido en JsonHttpBearerAuth: ' . get_class($e) . ' - ' . $e->getMessage(),
                'auth.jwt'
            );
            $this->challenge($response);

            $response->statusCode = 401;
            $response->data = [
                'success' => false,
                'message' => 'Token inválido o expirado',
                'errors' => null,
            ];
            $response->send();
            Yii::$app->end();
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

        // Contexto operativo stateless: si el token trae claims de sesión operativa, aplicarlos.
        // Estos claims SOLO deben ser emitidos por el backend (SesionOperativaService) tras validar coherencia.
        try {
            if (isset($decoded->id_efector)) {
                Yii::$app->user->setIdEfector((int) $decoded->id_efector);
            }
            if (isset($decoded->id_rr_hh)) {
                Yii::$app->user->setIdRecursoHumano((int) $decoded->id_rr_hh);
            }
            if (isset($decoded->servicio_actual)) {
                Yii::$app->user->setServicioActual((int) $decoded->servicio_actual);
            }
            if (isset($decoded->id_rrhh_servicio)) {
                Yii::$app->user->setIdRrhhServicio((int) $decoded->id_rrhh_servicio);
            }
            if (isset($decoded->encounter_class)) {
                Yii::$app->user->setEncounterClass((string) $decoded->encounter_class);
            }
        } catch (\Throwable $e) {
            Yii::debug('No se pudo aplicar contexto desde JWT: ' . $e->getMessage(), 'auth.jwt');
        }

        $user->setIdentity($userModel);
        // Igual que en frontend\components\UserConfig::afterLogin: updatePermissions trabaja sobre la identidad.
        // Si se pasa el componente Yii::$app->user, en algunos contextos no se hidratan __userRoutes/__userRoles
        // como espera webvimark, y puede resultar en 403 aun con permisos asignados.
        \webvimark\modules\UserManagement\components\AuthHelper::updatePermissions($userModel);
        AllowedRoutesResolver::markSessionRoutesOwner((int) $userModel->id);

        return $userModel;
    }
}

