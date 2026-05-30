<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\BadRequestHttpException;
use yii\web\UnauthorizedHttpException;
use common\components\Assistant\UiActions\AllowedRoutesResolver;
use common\models\BioenlaceDbManager;
use common\models\ProfesionalEfectorServicio;
use common\models\User;
use common\models\Persona;
use common\components\DiditClient;
use webvimark\modules\UserManagement\components\AuthHelper;
use webvimark\modules\UserManagement\models\rbacDB\Role;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthController extends BaseController
{
    /** Acciones sin autenticación (no mapea a frontend; solo API). */
    public static $authenticatorExcept = ['registrar', 'refrescar-token', 'generar-token-prueba', 'login-biometrico'];

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
     * Ruta: POST /api/v1/auth/login-biometrico
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
     * Rol principal para JWT / respuesta (evita devolver solo «paciente» si hay roles de staff).
     */
    private function getUserRole($user)
    {
        $roles = Role::getUserRoles($user->id);
        $names = [];
        foreach ($roles as $role) {
            if (!empty($role->name)) {
                $names[] = (string) $role->name;
            }
        }

        return $this->resolvePrimaryRoleName($names);
    }

    /**
     * @param list<string> $roleNames
     */
    private function resolvePrimaryRoleName(array $roleNames): string
    {
        if ($roleNames === []) {
            return 'usuario';
        }

        $staff = array_values(array_filter($roleNames, static function (string $name): bool {
            return $name !== 'paciente'
                && (str_contains($name, '_x_efector_') || str_contains($name, '_sin_efector_'));
        }));
        if ($staff !== []) {
            return $staff[0];
        }

        foreach ($roleNames as $name) {
            if ($name !== 'paciente') {
                return $name;
            }
        }

        return $roleNames[0];
    }

    /**
     * Obtener los permisos del usuario (requiere identidad en Yii::$app->user para BioenlaceDbManager).
     */
    private function getUserPermissions($user)
    {
        try {
            $permissions = Yii::$app->authManager->getPermissionsByUser($user->id);

            return array_keys($permissions);
        } catch (\Exception $e) {
            Yii::warning('Error obteniendo permisos del usuario: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Hidrata sesión + RBAC como tras login API (solo endpoint de prueba).
     *
     * @return array{roles: list<string>, primary_role: string, permissions: list<string>, jwt_claims: array<string, mixed>, pes_resuelto: array<string, mixed>|null}
     */
    private function bootstrapDevAuthContext(
        User $user,
        Persona $persona,
        ?int $idEfector = null,
        ?int $idPes = null,
        ?string $encounterClass = null,
        bool $autoPes = true
    ): array {
        $identity = \webvimark\modules\UserManagement\models\User::findOne((int) $user->id);
        if (!$identity) {
            throw new \RuntimeException('No se pudo cargar identidad webvimark para user_id ' . $user->id);
        }

        $session = Yii::$app->session;
        if (!$session->isActive) {
            $session->open();
        }

        $session->set('idPersona', (int) $persona->id_persona);
        $session->set('nombreUsuario', $persona->nombre);
        $session->set('apellidoUsuario', $persona->apellido);
        $session->set(
            'efectores',
            ProfesionalEfectorServicio::getEfectoresParaSesion((int) $persona->id_persona)
        );

        Yii::$app->user->setIdentity($identity);

        $jwtClaims = [];
        $pesMeta = null;
        $solicitaPes = $autoPes
            || ($idPes !== null && $idPes > 0)
            || ($idEfector !== null && $idEfector > 0);
        if ($solicitaPes) {
            [$pes, $pesMeta] = $this->resolvePesForDevToken($persona, $idPes, $idEfector);
            $pesExplicito = ($idPes !== null && $idPes > 0)
                || ($idEfector !== null && $idEfector > 0);
            if ($pes === null && $pesExplicito) {
                $disponibles = $this->listPesIdsForDevToken((int) $persona->id_persona);
                throw new \InvalidArgumentException(
                    'Sin asignación PES para id_persona ' . $persona->id_persona
                    . ($disponibles !== [] ? '. IDs PES en BD: ' . implode(', ', $disponibles) : '.')
                );
            }
            if ($pes !== null) {
                Yii::$app->user->setIdProfesionalEfectorServicio((int) $pes->id);
                Yii::$app->user->setIdEfector((int) $pes->id_efector);
                Yii::$app->user->setServicioActual((int) $pes->id_servicio);
                $jwtClaims['id_profesional_efector_servicio'] = (int) $pes->id;
                $jwtClaims['id_efector'] = (int) $pes->id_efector;
                $jwtClaims['servicio_actual'] = (int) $pes->id_servicio;
            }
        }

        if ($encounterClass !== null && $encounterClass !== '') {
            Yii::$app->user->setEncounterClass($encounterClass);
            $jwtClaims['encounter_class'] = $encounterClass;
        }

        BioenlaceDbManager::asignarRolPacienteSiNoExiste((int) $user->id);
        AuthHelper::updatePermissions($identity);
        AllowedRoutesResolver::markSessionRoutesOwner((int) $user->id);

        $authManager = Yii::$app->authManager;
        $rolesMap = $authManager->getRolesByUser((string) $user->id);
        $roleNames = array_keys($rolesMap);
        $permissions = array_keys($authManager->getPermissionsByUser($user->id));

        return [
            'roles' => $roleNames,
            'primary_role' => $this->resolvePrimaryRoleName($roleNames),
            'permissions' => $permissions,
            'jwt_claims' => $jwtClaims,
            'pes_resuelto' => $pesMeta,
        ];
    }

    /**
     * @return array{0: ProfesionalEfectorServicio|null, 1: array<string, mixed>|null}
     */
    private function resolvePesForDevToken(Persona $persona, ?int $idPes, ?int $idEfector): array
    {
        $idPersona = (int) $persona->id_persona;

        if ($idPes !== null && $idPes > 0) {
            $pes = ProfesionalEfectorServicio::findOne(['id' => $idPes, 'deleted_at' => null]);
            if ($pes !== null && (int) $pes->id_persona === $idPersona) {
                return [$pes, [
                    'id' => (int) $pes->id,
                    'id_efector' => (int) $pes->id_efector,
                    'id_servicio' => (int) $pes->id_servicio,
                    'solicitado' => $idPes,
                    'auto' => false,
                ]];
            }
        }

        $query = ProfesionalEfectorServicio::find()
            ->where(['id_persona' => $idPersona, 'deleted_at' => null]);
        if ($idEfector !== null && $idEfector > 0) {
            $query->andWhere(['id_efector' => $idEfector]);
        }
        /** @var ProfesionalEfectorServicio|null $pes */
        $pes = $query->orderBy(['id' => SORT_ASC])->one();
        if ($pes === null) {
            return [null, null];
        }

        return [$pes, [
            'id' => (int) $pes->id,
            'id_efector' => (int) $pes->id_efector,
            'id_servicio' => (int) $pes->id_servicio,
            'solicitado' => $idPes,
            'auto' => $idPes === null || $idPes !== (int) $pes->id,
        ]];
    }

    /**
     * @return list<int>
     */
    private function listPesIdsForDevToken(int $idPersona): array
    {
        return ProfesionalEfectorServicio::find()
            ->select('id')
            ->where(['id_persona' => $idPersona, 'deleted_at' => null])
            ->orderBy(['id' => SORT_ASC])
            ->column();
    }

    /**
     * Generar token JWT
     *
     * @param array<string, mixed> $extraClaims p. ej. role, id_efector, id_profesional_efector_servicio
     */
    private function generateJwtToken($user, ?int $idPersona = null, array $extraClaims = []): string
    {
        $role = isset($extraClaims['role'])
            ? (string) $extraClaims['role']
            : $this->getUserRole($user);
        unset($extraClaims['role']);

        if ($idPersona === null) {
            $persona = Persona::findOne(['id_user' => $user->id]);
            if ($persona) {
                $idPersona = (int) $persona->id_persona;
            }
        }

        $payload = array_merge([
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $role,
            'id_persona' => $idPersona,
            'iat' => time(),
            'exp' => time() + (24 * 60 * 60),
        ], $extraClaims);

        return JWT::encode($payload, Yii::$app->params['jwtSecret'], 'HS256');
    }

    /**
     * Endpoint de prueba: Generar token para paciente por DNI o por user_id.
     * Solo para desarrollo/pruebas. Identidad: user_id, id_persona o dni (uno alcanza).
     * PES/efector/servicio: auto_pes=1 intenta resolver el primer PES; si no hay asignación,
     * emite token solo con identidad (p. ej. paciente). Falla solo si se pidió id_pes/id_efector
     * concretos y no existen. Opcional: encounter_class, auto_pes=0.
     */
    public function actionGenerarTokenPrueba()
    {
        $request = Yii::$app->request;
        $dni = $request->post('dni') ?? $request->get('dni');
        $userId = $request->post('user_id') ?? $request->get('user_id');
        $idPersonaParam = $request->post('id_persona') ?? $request->get('id_persona');
        $idEfectorParam = $request->post('id_efector') ?? $request->get('id_efector');
        $idPesParam = $request->post('id_profesional_efector_servicio')
            ?? $request->get('id_profesional_efector_servicio')
            ?? $request->post('id_pes')
            ?? $request->get('id_pes');
        $idEfector = ($idEfectorParam !== null && $idEfectorParam !== '') ? (int) $idEfectorParam : null;
        $idPes = ($idPesParam !== null && $idPesParam !== '') ? (int) $idPesParam : null;
        $encounterClassParam = $request->post('encounter_class') ?? $request->get('encounter_class');
        $encounterClass = is_string($encounterClassParam) && $encounterClassParam !== ''
            ? $encounterClassParam
            : null;
        $autoPesParam = $request->post('auto_pes') ?? $request->get('auto_pes', '1');
        $autoPes = !in_array(strtolower((string) $autoPesParam), ['0', 'false', 'no'], true);
        if ($userId !== null && $userId !== '') {
            $userId = (int) $userId;
        } else {
            $userId = null;
        }
        if ($idPersonaParam !== null && $idPersonaParam !== '') {
            $idPersonaParam = (int) $idPersonaParam;
        } else {
            $idPersonaParam = null;
        }

        if ($userId !== null) {
            // Por user_id: buscar usuario y luego su persona
            $user = User::findIdentity($userId);
            if (!$user) {
                return $this->error('No se encontró usuario con id: ' . $userId, null, 404);
            }
            if ($idPersonaParam !== null) {
                $persona = Persona::findOne(['id_persona' => $idPersonaParam]);
                if (!$persona) {
                    return $this->error('No se encontró persona con id_persona: ' . $idPersonaParam, null, 404);
                }
                if ((int) $persona->id_user !== (int) $user->id) {
                    return $this->error(
                        'id_persona ' . $idPersonaParam . ' no está vinculada al user_id ' . $userId,
                        null,
                        400
                    );
                }
            } else {
                $persona = Persona::findOne(['id_user' => $user->id]);
                if (!$persona) {
                    return $this->error('El usuario ' . $userId . ' no tiene persona asociada', null, 404);
                }
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
        } elseif ($idPersonaParam !== null) {
            $persona = Persona::findOne(['id_persona' => $idPersonaParam]);
            if (!$persona) {
                return $this->error('No se encontró persona con id_persona: ' . $idPersonaParam, null, 404);
            }
            if (!$persona->id_user) {
                return $this->error(
                    'La persona ' . $idPersonaParam . ' no tiene usuario asociado',
                    null,
                    404
                );
            }
            $user = User::findIdentity((int) $persona->id_user);
            if (!$user) {
                return $this->error('Usuario no encontrado para id_user: ' . $persona->id_user, null, 404);
            }
        } else {
            return $this->error('Se requiere user_id, id_persona o dni', null, 400);
        }

        if ($user->status !== User::STATUS_ACTIVE) {
            return $this->error('Usuario inactivo', null, 401);
        }

        try {
            $authContext = $this->bootstrapDevAuthContext(
                $user,
                $persona,
                $idEfector,
                $idPes,
                $encounterClass,
                $autoPes
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            Yii::error($e->getMessage(), __METHOD__);

            return $this->error('No se pudo preparar contexto RBAC: ' . $e->getMessage(), null, 500);
        }

        $jwtClaims = array_merge(
            ['role' => $authContext['primary_role']],
            $authContext['jwt_claims']
        );
        $token = $this->generateJwtToken($user, (int) $persona->id_persona, $jwtClaims);

        return $this->success([
            'user' => [
                'id' => $user->id,
                'name' => $user->username,
                'email' => $user->email,
                'role' => $authContext['primary_role'],
                'roles' => $authContext['roles'],
                'permissions' => $authContext['permissions'],
            ],
            'persona' => [
                'id_persona' => $persona->id_persona,
                'nombre' => $persona->nombre,
                'apellido' => $persona->apellido,
                'documento' => $persona->documento,
            ],
            'sesion_operativa' => $authContext['jwt_claims'] !== [] ? $authContext['jwt_claims'] : null,
            'pes_resuelto' => $authContext['pes_resuelto'],
            'token' => $token,
        ], 'Token generado exitosamente');
    }
}
