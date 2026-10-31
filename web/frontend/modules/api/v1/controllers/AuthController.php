<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\BadRequestHttpException;
use yii\web\UnauthorizedHttpException;
use common\components\Platform\Assistant\UiActions\AllowedRoutesResolver;
use common\models\Platform\BioenlaceDbManager;
use common\models\Organization\ProfesionalEfectorServicio;
use common\models\Platform\User;
use common\models\Person\Persona;
use common\components\Domain\Person\FrontDesk\Infrastructure\External\Identity\Connector\DiditClient;
use common\components\Platform\Core\Permission\BioenlaceAccessChecker;
use common\components\Platform\Core\Permission\RbacRoleQueryService;
use common\components\Platform\Core\Auth\PlayReviewLoginService;
use common\components\Platform\Core\Auth\StaffMobileAccessService;
use common\components\Platform\Core\Auth\ApiAuthSessionService;
use common\components\Domain\Person\Identity\Application\Service\IdentityVerificationException;
use common\components\Domain\Person\Identity\Application\Service\DiditIdentityService;
use frontend\components\QrLoginService;
use frontend\components\WebApiJwtSessionService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthController extends BaseController
{
    protected bool $contractResponses = true;

    /** Acciones sin autenticación Bearer (no mapea a frontend; solo API). */
    public static $authenticatorExcept = [
        'registrar',
        'refrescar-token',
        'generar-token-prueba',
        'login-biometrico',
        'login-revision',
        'login-credenciales',
        'activar-cuenta',
    ];

    /**
     * Ruta retirada: el registro con correo y contraseña no forma parte del alta actual.
     */
    public function actionRegistrar()
    {
        return $this->error(
            'El registro con correo y contraseña fue retirado. Usá el registro correspondiente a tu tipo de cuenta.',
            null,
            410
        );
    }

    /**
     * Obtener usuario actual
     */
    public function actionYo()
    {
        $user = Yii::$app->user->identity;
        
        if (!$user) {
            return $this->fail('unauthenticated');
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
     * Aprueba un desafío QR de login web desde Personal de Salud.
     *
     * POST /api/v1/auth/qr-login-aprobar
     * Body: { "challenge_id": "..." }
     */
    public function actionAprobarQrLogin()
    {
        $challengeId = (string) (Yii::$app->request->post('challenge_id') ?? '');
        if ($challengeId === '') {
            return $this->error('El campo "challenge_id" es requerido', null, 400);
        }

        $user = Yii::$app->user->identity;
        if (!$user instanceof User) {
            return $this->fail('unauthenticated');
        }

        try {
            QrLoginService::approve($challengeId, $user);
        } catch (\yii\web\BadRequestHttpException $e) {
            return $this->fail('qr_login_invalid', ['detail' => $e->getMessage()]);
        }

        return $this->success(null, 'Acceso web aprobado');
    }

    /**
     * Logout
     */
    public function actionCerrarSesion()
    {
        $token = $this->requestBearerToken();
        if ($token === null && $this->isWebClientRequest()) {
            $token = WebApiJwtSessionService::getSessionToken();
        }
        if ($token !== null) {
            try {
                $decoded = JWT::decode($token, new Key(Yii::$app->params['jwtSecret'], 'HS256'));
            } catch (\Throwable $e) {
                return $this->fail('unauthenticated');
            }

            ApiAuthSessionService::revoke(
                (string) ($decoded->jti ?? ''),
                isset($decoded->user_id) ? (int) $decoded->user_id : null
            );
            if ($this->isWebClientRequest()) {
                WebApiJwtSessionService::revokeCurrentToken();
            }
        }

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
            $userId = (int) ($decoded->user_id ?? 0);
            $jti = (string) ($decoded->jti ?? '');
            if (!ApiAuthSessionService::isActive($jti, $userId)) {
                return $this->fail('unauthenticated');
            }

            $user = User::findIdentity($userId);
            
            if (!$user) {
                return $this->fail('unauthenticated');
            }

            // Generar nuevo token
            $newToken = $this->generateJwtToken($user);
            ApiAuthSessionService::revoke($jti, $userId);

            return $this->success([
                'token' => $newToken,
            ], 'Token refrescado exitosamente');

        } catch (\Exception $e) {
            return $this->fail('unauthenticated');
        }
    }

    /**
     * Login biométrico usando Didit (selfie + liveness + face match).
     *
     * Ruta: POST /api/v1/auth/login-biometrico
     *
     * Payload esperado:
     * { "biometric_verification_id": "didit-biometric-verification-id" }
     */
    public function actionLoginBiometrico()
    {
        $request = Yii::$app->request;
        $verificationIdInput = $request->post('biometric_verification_id');
        $verificationId = is_string($verificationIdInput) ? trim($verificationIdInput) : '';

        if (!$verificationId) {
            return $this->error('El campo "biometric_verification_id" es requerido', null, 400);
        }

        /** @var DiditClient $didit */
        $didit = Yii::$container->has(DiditClient::class)
            ? Yii::$container->get(DiditClient::class)
            : new DiditClient();

        $diditResult = $didit->getBiometricAuth($verificationId);

        try {
            DiditIdentityService::assertApprovedVerification($diditResult);
        } catch (IdentityVerificationException $e) {
            return $this->fail('authentication_failed_detail', [
                'detail' => 'No pudimos confirmar tu identidad. Verificá tus datos e intentá nuevamente.',
            ]);
        }

        // El documento verificado es la identidad principal; vendor_data puede no ser único.
        $persona = null;
        $linkedDocument = preg_replace('/\D+/', '', (string) ($diditResult['linked_document'] ?? '')) ?? '';
        if ($linkedDocument !== '') {
            $persona = Persona::findOne(['documento' => $linkedDocument]);
        } elseif (!empty($diditResult['didit_reference_id'])) {
            $persona = Persona::findOne(['didit_reference_id' => $diditResult['didit_reference_id']]);
        }

        if ($persona === null) {
            return $this->error('No se encontró persona asociada a la verificación biométrica', null, 404);
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
            return $this->fail('authentication_failed_detail', [
                'detail' => 'Usuario inactivo',
            ]);
        }

        if (!StaffMobileAccessService::isUserAllowedToLogin($user, $persona)) {
            return $this->fail('forbidden_detail', [
                'detail' => 'Esta cuenta no tiene acceso desde Personal de Salud. Ingresá desde la web.',
            ]);
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
            'token' => $token,
        ], 'Login biométrico exitoso');
    }

    /**
     * Login usuario/contraseña para revisión de tiendas (cuentas allowlisted en params).
     *
     * POST /api/v1/auth/login-revision
     * Body: { "username": "...", "password": "..." }
     *
     * Requiere play_review_login_habilitado y play_review_accounts en params-local.
     *
     * @see mobile/PLAY_APP_ACCESS.md
     */
    public function actionLoginRevision()
    {
        if (!PlayReviewLoginService::isEnabled()) {
            return $this->error('Acceso de revisión no habilitado', null, 404);
        }

        $username = (string) (Yii::$app->request->post('username') ?? '');
        $password = (string) (Yii::$app->request->post('password') ?? '');

        try {
            $auth = PlayReviewLoginService::authenticate($username, $password);
        } catch (\DomainException $e) {
            return $this->fail('authentication_failed_detail', [
                'detail' => $e->getMessage(),
            ]);
        }

        $user = $auth['user'];
        $persona = $auth['persona'];
        $token = $this->generateJwtToken($user, (int) $persona->id_persona);
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
        ], 'Login de revisión exitoso');
    }

    /**
     * Ruta retirada: Personal de Salud ingresa verificando su identidad.
     *
     * POST /api/v1/auth/login-credenciales
     * Devuelve 410 para clientes legacy.
     */
    public function actionLoginCredenciales()
    {
        return $this->error(
            'El ingreso móvil con usuario y contraseña fue retirado. Verificá tu identidad desde Personal de Salud.',
            null,
            410
        );
    }

    /**
     * Ruta retirada: el alta y la incorporación no usan activación por código.
     *
     * POST /api/v1/auth/activar-cuenta
     * Devuelve 410 para clientes legacy.
     */
    public function actionActivarCuenta()
    {
        return $this->error(
            'La activación con código y contraseña fue retirada. Pedí a la administración que te sume al centro y verificá tu identidad en la app.',
            null,
            410
        );
    }

    /**
     * Rol principal para JWT / respuesta (evita devolver solo «paciente» si hay roles de staff).
     */
    private function getUserRole($user)
    {
        $roles = RbacRoleQueryService::getUserRoles((int) $user->id);
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
        $identity = \common\models\Platform\User::findOne((int) $user->id);
        if (!$identity) {
            throw new \RuntimeException('No se pudo cargar identidad para user_id ' . $user->id);
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
        BioenlaceAccessChecker::refreshForIdentity($identity);
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

        $expiresAt = time() + (24 * 60 * 60);
        $payload = array_merge([
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $role,
            'id_persona' => $idPersona,
            'iat' => time(),
            'exp' => $expiresAt,
            'jti' => ApiAuthSessionService::issue(
                (int) $user->id,
                $expiresAt,
                (string) Yii::$app->request->headers->get('X-App-Client', 'unknown')
            ),
        ], $extraClaims);

        return JWT::encode($payload, Yii::$app->params['jwtSecret'], 'HS256');
    }

    private function requestBearerToken(): ?string
    {
        $authorization = (string) Yii::$app->request->headers->get('Authorization', '');
        if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches) !== 1) {
            return null;
        }

        return trim($matches[1]) ?: null;
    }

    private function isWebClientRequest(): bool
    {
        $client = strtolower((string) Yii::$app->request->headers->get('X-Client', ''));
        $appClient = strtolower((string) Yii::$app->request->headers->get('X-App-Client', ''));

        return $client === 'web' || $appClient === 'web-frontend';
    }

    /**
     * Endpoint de prueba: Generar token para paciente por DNI o por user_id.
     * Identidad: user_id, id_persona o dni (uno alcanza).
     * PES/efector/servicio: auto_pes=1 intenta resolver el primer PES; si no hay asignación,
     * emite token solo con identidad (p. ej. paciente). Falla solo si se pidió id_pes/id_efector
     * concretos y no existen. Opcional: encounter_class, auto_pes=0.
     *
     * Uso previsto: desarrollo / atajo debug de la app. No exponer en clientes de tienda.
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
            return $this->fail('authentication_failed_detail', [
                'detail' => 'Usuario inactivo',
            ]);
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
