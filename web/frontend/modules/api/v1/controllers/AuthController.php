<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\BadRequestHttpException;
use yii\web\UnauthorizedHttpException;
use common\models\User;
use common\models\Persona;
use common\components\DiditClient;
use webvimark\modules\UserManagement\models\rbacDB\Role;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthController extends BaseController
{
    /** Acciones sin autenticación (no mapea a frontend; solo API). */
    public static $authenticatorExcept = ['register', 'refresh-token', 'generate-token-prueba', 'login-biometrico'];

    /**
     * Registro de usuario
     */
    public function actionRegistrar()
    {
        $request = Yii::$app->request;
        $email = $request->post('email');
        $password = $request->post('password');
        $name = $request->post('name');

        if (!$email || !$password || !$name) {
            return $this->error('Email, contraseña y nombre son requeridos', null, 400);
        }

        // Verificar si el usuario ya existe
        if (User::findByEmail($email)) {
            return $this->error('El usuario ya existe', null, 409);
        }

        // Crear nuevo usuario
        $user = new User();
        $user->username = $name;
        $user->email = $email;
        $user->setPassword($password);
        $user->generateAuthKey();
        $user->status = User::STATUS_ACTIVE;

        if (!$user->save()) {
            return $this->error('Error creando usuario', $user->getErrors(), 422);
        }

        // Asignar rol 'paciente' si existe en el sistema RBAC
        // Nota: Esto requiere que el rol 'paciente' exista en la tabla auth_item
        try {
            $pacienteRole = Role::findOne(['name' => 'paciente']);
            if ($pacienteRole) {
                Yii::$app->authManager->assign($pacienteRole, $user->id);
            }
        } catch (\Exception $e) {
            // Si no se puede asignar el rol, continuar sin él
            Yii::warning('No se pudo asignar rol paciente al usuario: ' . $e->getMessage());
        }

        // Generar token JWT reutilizando id_persona ya resuelto
        $token = $this->generateJwtToken($user, $persona->id_persona);
        $role = $this->getUserRole($user);
        $permissions = $this->getUserPermissions($user);

        return $this->success([
            'user' => [
                'id' => $user->id,
                'name' => $user->username,
                'email' => $user->email,
                'role' => $role,
                'permissions' => $permissions,
            ],
            'token' => $token,
        ], 'Usuario creado exitosamente', 201);
    }

    /**
     * Obtener usuario actual
     */
    public function actionYo()
    {
        $user = Yii::$app->user->identity;
        
        if (!$user) {
            return $this->error('Usuario no autenticado', null, 401);
        }

        $role = $this->getUserRole($user);
        $permissions = $this->getUserPermissions($user);
        
        return $this->success([
            'id' => $user->id,
            'name' => $user->username,
            'email' => $user->email,
            'role' => $role,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Logout
     */
    public function actionCerrarSesion()
    {
        // En JWT, el logout se maneja del lado del cliente
        // Aquí podríamos implementar una blacklist de tokens si es necesario
        return $this->success(null, 'Logout exitoso');
    }

    /**
     * Refrescar token
     */
    public function actionRefrescarToken()
    {
        $request = Yii::$app->request;
        $token = $request->post('token');

        if (!$token) {
            return $this->error('Token requerido', null, 400);
        }

        try {
            $decoded = JWT::decode($token, new Key(Yii::$app->params['jwtSecret'], 'HS256'));
            $user = User::findIdentity($decoded->user_id);
            
            if (!$user) {
                return $this->error('Usuario no encontrado', null, 401);
            }

            // Generar nuevo token
            $newToken = $this->generateJwtToken($user);

            return $this->success([
                'token' => $newToken,
            ], 'Token refrescado exitosamente');

        } catch (\Exception $e) {
            return $this->error('Token inválido', null, 401);
        }
    }

    /**
     * Login biométrico usando Didit (selfie + liveness + face match).
     *
     * Ruta: POST /api/v1/auth/biometric-login
     *
     * Payload esperado:
     * {
     *   "biometric_verification_id": "didit-biometric-verification-id",
     *   "device_id": "uuid-del-dispositivo",
     *   "platform": "android" | "ios" | "otro"
     * }
     */
    public function actionLoginBiometrico()
    {
        $request = Yii::$app->request;
        $verificationId = $request->post('biometric_verification_id');
        $deviceId = $request->post('device_id');
        $platform = $request->post('platform');

        if (!$verificationId) {
            return $this->error('El campo "biometric_verification_id" es requerido', null, 400);
        }

        /** @var DiditClient $didit */
        $didit = Yii::$container->has(DiditClient::class)
            ? Yii::$container->get(DiditClient::class)
            : new DiditClient();

        $diditResult = $didit->getBiometricAuth($verificationId);

        if ($diditResult['success'] !== true || $diditResult['status'] === 'rejected') {
            return $this->error('Verificación biométrica rechazada por Didit', $diditResult, 401);
        }

        // Resolver persona por referencia Didit o por documento
        $persona = null;
        if (!empty($diditResult['didit_reference_id'])) {
            $persona = Persona::findOne(['didit_reference_id' => $diditResult['didit_reference_id']]);
        }
        if ($persona === null && !empty($diditResult['linked_document'])) {
            $persona = Persona::findOne(['documento' => $diditResult['linked_document']]);
        }

        if ($persona === null) {
            return $this->error('No se encontró persona asociada a la verificación biométrica', $diditResult, 404);
        }

        if (!$persona->id_user) {
            return $this->error(
                'La persona (id_persona ' . $persona->id_persona . ') no tiene usuario asociado para login.',
                null,
                404
            );
        }

        $user = User::findIdentity($persona->id_user);
        if (!$user) {
            return $this->error('Usuario no encontrado para id_user: ' . $persona->id_user, null, 404);
        }

        if ($user->status !== User::STATUS_ACTIVE) {
            return $this->error('Usuario inactivo', null, 401);
        }

        // Generar token JWT
        $token = $this->generateJwtToken($user);
        $role = $this->getUserRole($user);
        $permissions = $this->getUserPermissions($user);

        // Nota: por ahora no persistimos device_id/platform en user_device; se puede extender luego.

        return $this->success([
            'user' => [
                'id' => $user->id,
                'name' => $user->username,
                'email' => $user->email,
                'role' => $role,
                'permissions' => $permissions,
            ],
            'persona' => [
                'id_persona' => $persona->id_persona,
                'nombre' => $persona->nombre,
                'apellido' => $persona->apellido,
                'documento' => $persona->documento,
            ],
            'didit' => $diditResult,
            'token' => $token,
        ], 'Login biométrico exitoso');
    }

    /**
     * Obtener el rol del usuario (primer rol asignado o 'usuario' por defecto)
     */
    private function getUserRole($user)
    {
        $roles = Role::getUserRoles($user->id);
        if (!empty($roles)) {
            // Obtener el primer rol
            $firstRole = reset($roles);
            return $firstRole->name ?? 'usuario';
        }
        return 'usuario';
    }

    /**
     * Obtener los permisos del usuario
     */
    private function getUserPermissions($user)
    {
        try {
            $permissions = Yii::$app->authManager->getPermissionsByUser($user->id);
            // Convertir a array de nombres de permisos
            return array_keys($permissions);
        } catch (\Exception $e) {
            // Si hay error, retornar array vacío
            Yii::warning('Error obteniendo permisos del usuario: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Generar token JWT
     */
    private function generateJwtToken($user, ?int $idPersona = null)
    {
        $role = $this->getUserRole($user);

        // Si no viene id_persona (casos como refresh-token o generate-test-token),
        // buscarlo una sola vez aquí.
        if ($idPersona === null) {
            $persona = \common\models\Persona::findOne(['id_user' => $user->id]);
            if ($persona) {
                $idPersona = $persona->id_persona;
            }
        }
        
        $payload = [
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $role,
            'id_persona' => $idPersona, // Agregar id_persona al token
            'iat' => time(),
            'exp' => time() + (24 * 60 * 60), // 24 horas
        ];

        return JWT::encode($payload, Yii::$app->params['jwtSecret'], 'HS256');
    }

    /**
     * Endpoint de prueba: Generar token para paciente por DNI o por user_id.
     * Solo para desarrollo/pruebas. Parámetros: dni O user_id.
     */
    public function actionGenerarTokenPrueba()
    {
        $request = Yii::$app->request;
        $dni = $request->post('dni') ?? $request->get('dni');
        $userId = $request->post('user_id') ?? $request->get('user_id');
        if ($userId !== null && $userId !== '') {
            $userId = (int) $userId;
        } else {
            $userId = null;
        }

        if ($userId !== null) {
            // Por user_id: buscar usuario y luego su persona
            $user = User::findIdentity($userId);
            if (!$user) {
                return $this->error('No se encontró usuario con id: ' . $userId, null, 404);
            }
            $persona = Persona::findOne(['id_user' => $user->id]);
            if (!$persona) {
                return $this->error('El usuario ' . $userId . ' no tiene persona asociada', null, 404);
            }
        } elseif ($dni) {
            // Por DNI: buscar persona y luego su usuario
            $persona = Persona::findOne(['documento' => $dni]);
            if (!$persona) {
                return $this->error('No se encontró paciente con DNI: ' . $dni, null, 404);
            }
            if (!$persona->id_user) {
                return $this->error('El paciente con DNI ' . $dni . ' no tiene usuario asociado. id_persona: ' . $persona->id_persona, null, 404);
            }
            $user = User::findIdentity($persona->id_user);
            if (!$user) {
                return $this->error('Usuario no encontrado para id_user: ' . $persona->id_user, null, 404);
            }
        } else {
            return $this->error('Se requiere dni o user_id', null, 400);
        }

        if ($user->status !== User::STATUS_ACTIVE) {
            return $this->error('Usuario inactivo', null, 401);
        }

        $token = $this->generateJwtToken($user);
        $role = $this->getUserRole($user);
        $permissions = $this->getUserPermissions($user);

        return $this->success([
            'user' => [
                'id' => $user->id,
                'name' => $user->username,
                'email' => $user->email,
                'role' => $role,
                'permissions' => $permissions,
            ],
            'persona' => [
                'id_persona' => $persona->id_persona,
                'nombre' => $persona->nombre,
                'apellido' => $persona->apellido,
                'documento' => $persona->documento,
            ],
            'token' => $token,
        ], 'Token generado exitosamente');
    }
}
