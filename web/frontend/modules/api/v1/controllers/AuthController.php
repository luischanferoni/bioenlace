<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\BadRequestHttpException;
use yii\web\UnauthorizedHttpException;
use common\models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthController extends BaseController
{
    public $modelClass = '';

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        
        // No requerir autenticación para login y register
        $behaviors['authenticator']['except'] = ['login', 'register', 'refresh-token', 'options'];
        
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

        return $this->success([
            'user' => [
                'id' => $user->id,
                'name' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'permissions' => $user->getPermissions(),
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
        $user->role = 'paciente'; // Rol por defecto

        if (!$user->save()) {
            return $this->error('Error creando usuario', $user->getErrors(), 422);
        }

        // Generar token JWT
        $token = $this->generateJwtToken($user);

        return $this->success([
            'user' => [
                'id' => $user->id,
                'name' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'permissions' => $user->getPermissions(),
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

        return $this->success([
            'id' => $user->id,
            'name' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
            'permissions' => $user->getPermissions(),
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
     * Generar token JWT
     */
    private function generateJwtToken($user)
    {
        $payload = [
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'iat' => time(),
            'exp' => time() + (24 * 60 * 60), // 24 horas
        ];

        return JWT::encode($payload, Yii::$app->params['jwtSecret'], 'HS256');
    }
}
