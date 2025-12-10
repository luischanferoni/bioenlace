<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\BadRequestHttpException;
use yii\web\UnauthorizedHttpException;
use common\models\User;
use common\models\Persona;
use webvimark\modules\UserManagement\models\rbacDB\Role;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthController extends BaseController
{
    public $modelClass = '';

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        
        // No requerir autenticación para login y register
        $behaviors['authenticator']['except'] = ['login', 'register', 'refresh-token', 'generate-test-token', 'options'];
        
        return $behaviors;
    }

    /**
     * Login de usuario
     */
    public function actionLogin()
    {
        $request = Yii::$app->request;
        $email = $request->post('email');
        $password = $request->post('password');

        if (!$email || !$password) {
            return $this->error('Email y contraseña son requeridos', null, 400);
        }

        // Buscar usuario por email
        $user = User::findByEmail($email);
        if (!$user || !$user->validatePassword($password)) {
            return $this->error('Credenciales inválidas', null, 401);
        }

        // Verificar que el usuario esté activo
        if ($user->status !== User::STATUS_ACTIVE) {
            return $this->error('Usuario inactivo', null, 401);
        }

        // Generar token JWT
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
            'token' => $token,
        ], 'Login exitoso');
    }

    /**
     * Registro de usuario
     */
    public function actionRegister()
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

        // Generar token JWT
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
            'token' => $token,
        ], 'Usuario creado exitosamente', 201);
    }

    /**
     * Obtener usuario actual
     */
    public function actionMe()
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
    public function actionLogout()
    {
        // En JWT, el logout se maneja del lado del cliente
        // Aquí podríamos implementar una blacklist de tokens si es necesario
        return $this->success(null, 'Logout exitoso');
    }

    /**
     * Refrescar token
     */
    public function actionRefreshToken()
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
    private function generateJwtToken($user)
    {
        $role = $this->getUserRole($user);
        
        $payload = [
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $role,
            'iat' => time(),
            'exp' => time() + (24 * 60 * 60), // 24 horas
        ];

        return JWT::encode($payload, Yii::$app->params['jwtSecret'], 'HS256');
    }

    /**
     * Endpoint de prueba: Generar token para paciente por DNI
     * Solo para desarrollo/pruebas
     */
    public function actionGenerateTestToken()
    {
        $request = Yii::$app->request;
        $dni = $request->post('dni') ?? $request->get('dni');

        if (!$dni) {
            return $this->error('DNI requerido', null, 400);
        }

        // Buscar persona por DNI
        $persona = Persona::findOne(['documento' => $dni]);
        
        if (!$persona) {
            return $this->error('No se encontró paciente con DNI: ' . $dni, null, 404);
        }

        // Verificar si tiene usuario asociado
        if (!$persona->id_user) {
            return $this->error('El paciente con DNI ' . $dni . ' no tiene usuario asociado. id_persona: ' . $persona->id_persona, null, 404);
        }

        // Buscar el usuario
        $user = User::findIdentity($persona->id_user);
        
        if (!$user) {
            return $this->error('Usuario no encontrado para id_user: ' . $persona->id_user, null, 404);
        }

        // Verificar que el usuario esté activo
        if ($user->status !== User::STATUS_ACTIVE) {
            return $this->error('Usuario inactivo', null, 401);
        }

        // Generar token JWT
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
        ], 'Token generado exitosamente para paciente con DNI: ' . $dni);
    }
}
