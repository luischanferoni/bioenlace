<?php

namespace common\models;

use common\components\Platform\Core\Permission\BioenlaceAccessChecker;
use common\components\Platform\Core\Permission\BioenlaceRbacRevision;
use common\components\Platform\Core\Permission\BioenlaceSessionPermissions;
use Yii;
use yii\base\Security;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;

/**
 * Usuario de aplicación (tabla `user`). Identidad Yii + RBAC Bioenlace; sin webvimark AuthHelper.
 *
 * @property int $id
 * @property string $username
 * @property string|null $email
 * @property int $email_confirmed
 * @property string $auth_key
 * @property string $password_hash
 * @property string|null $confirmation_token
 * @property string|null $bind_to_ip
 * @property string|null $registration_ip
 * @property int $status
 * @property int $superadmin
 * @property int $created_at
 * @property int $updated_at
 * @property string|null $password
 * @property int|null $password_set_at
 * @property string|null $activation_code_hash
 * @property int|null $activation_code_expires_at
 * @property string|null $repeat_password
 * @property string|null $gridRoleSearch
 */
class User extends ActiveRecord implements IdentityInterface
{
    public const STATUS_ACTIVE = 1;

    public const STATUS_INACTIVE = 0;

    public const STATUS_BANNED = -1;

    /** Alta por invitación (staff): sin contraseña en el formulario. */
    public const SCENARIO_INVITE = 'inviteUser';

    /** @var string|null */
    public $password;

    /** @var string|null */
    public $repeat_password;

    /** @var string|null */
    public $gridRoleSearch;

    public static function tableName(): string
    {
        return 'user';
    }

    public function behaviors(): array
    {
        return [
            TimestampBehavior::class,
        ];
    }

    public function rules(): array
    {
        return [
            [['username'], 'required'],
            [['username'], 'unique'],
            [['username'], 'trim'],
            [['status', 'email_confirmed', 'password_set_at', 'activation_code_expires_at'], 'integer'],
            [['email'], 'required', 'on' => self::SCENARIO_INVITE],
            [['email'], 'email'],
            [['email'], 'validateEmailConfirmedUnique'],
            [['bind_to_ip'], 'validateBindToIp'],
            [['bind_to_ip'], 'trim'],
            [['bind_to_ip'], 'string', 'max' => 255],
            [['password'], 'required', 'on' => ['newUser', 'changePassword']],
            [['password'], 'string', 'min' => 6, 'max' => 255, 'on' => ['newUser', 'changePassword']],
            [['password'], 'trim', 'on' => ['newUser', 'changePassword']],
            [['repeat_password'], 'required', 'on' => ['newUser', 'changePassword']],
            [['repeat_password'], 'compare', 'compareAttribute' => 'password', 'on' => ['newUser', 'changePassword']],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'username' => 'Usuario',
            'superadmin' => 'Superadmin',
            'confirmation_token' => 'Token de confirmación',
            'registration_ip' => 'IP de registro',
            'bind_to_ip' => 'Vincular a IP',
            'status' => 'Estado',
            'gridRoleSearch' => 'Roles',
            'created_at' => 'Creado',
            'updated_at' => 'Actualizado',
            'password' => 'Contraseña',
            'repeat_password' => 'Repetir contraseña',
            'email_confirmed' => 'E-mail confirmado',
            'email' => 'E-mail',
        ];
    }

    public static function findIdentity($id)
    {
        return static::findOne($id);
    }

    public static function findIdentityByAccessToken($token, $type = null)
    {
        return static::findOne(['auth_key' => $token, 'status' => self::STATUS_ACTIVE]);
    }

    public static function findByUsername($username)
    {
        return static::findOne(['username' => $username, 'status' => self::STATUS_ACTIVE]);
    }

    public static function findByConfirmationToken($token, ?int $expireSeconds = null)
    {
        $expire = $expireSeconds ?? self::resolveConfirmationTokenExpire();

        $parts = explode('_', (string) $token);
        $timestamp = (int) end($parts);
        if ($timestamp + $expire < time()) {
            return null;
        }

        return static::findOne([
            'confirmation_token' => $token,
            'status' => self::STATUS_ACTIVE,
        ]);
    }

    private static function resolveConfirmationTokenExpire(): int
    {
        $expire = (int) (Yii::$app->params['user.passwordResetTokenExpire'] ?? 3600);
        if (Yii::$app->has('user-management')) {
            $module = Yii::$app->getModule('user-management');
            if ($module !== null && isset($module->confirmationTokenExpire)) {
                $expire = (int) $module->confirmationTokenExpire;
            }
        }

        return $expire;
    }

    public static function findInactiveByConfirmationToken($token)
    {
        $expire = self::resolveConfirmationTokenExpire();

        $parts = explode('_', (string) $token);
        $timestamp = (int) end($parts);
        if ($timestamp + $expire < time()) {
            return null;
        }

        return static::findOne([
            'confirmation_token' => $token,
            'status' => self::STATUS_INACTIVE,
        ]);
    }

    public function getId()
    {
        return $this->getPrimaryKey();
    }

    public function getAuthKey()
    {
        return $this->auth_key;
    }

    public function validateAuthKey($authKey)
    {
        return $this->getAuthKey() === $authKey;
    }

    public function validatePassword($password)
    {
        return Yii::$app->security->validatePassword($password, $this->password_hash);
    }

    public function setPassword($password)
    {
        if (PHP_SAPI === 'cli') {
            $security = new Security();
            $this->password_hash = $security->generatePasswordHash($password);
        } else {
            $this->password_hash = Yii::$app->security->generatePasswordHash($password);
        }
    }

    public function generateAuthKey()
    {
        if (PHP_SAPI === 'cli') {
            $security = new Security();
            $this->auth_key = $security->generateRandomString();
        } else {
            $this->auth_key = Yii::$app->security->generateRandomString();
        }
    }

    public function generateConfirmationToken()
    {
        $this->confirmation_token = Yii::$app->security->generateRandomString() . '_' . time();
    }

    public function removeConfirmationToken()
    {
        $this->confirmation_token = null;
    }

    /**
     * @param string|array<int|string, string> $route
     */
    public static function canRoute($route, $superAdminAllowed = true): bool
    {
        if (Yii::$app->user->isGuest) {
            return false;
        }

        $userId = (int) Yii::$app->user->id;
        if ($userId <= 0) {
            return false;
        }

        if ($superAdminAllowed && BioenlaceAccessChecker::isSuperadminUserId($userId)) {
            return true;
        }

        BioenlaceSessionPermissions::ensureUpToDate();

        foreach (self::expandRouteCandidates($route) as $candidate) {
            if (BioenlaceAccessChecker::userHasRoute($userId, $candidate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string|array<int|string, string> $roles
     */
    public static function hasRole($roles, $superAdminAllowed = true): bool
    {
        if (Yii::$app->user->isGuest) {
            return false;
        }

        $userId = (int) Yii::$app->user->id;
        if ($userId <= 0) {
            return false;
        }

        if ($superAdminAllowed && BioenlaceAccessChecker::isSuperadminUserId($userId)) {
            return true;
        }

        $roleList = self::normalizeRoleList($roles);
        if ($roleList === []) {
            return false;
        }

        BioenlaceSessionPermissions::ensureUpToDate();
        if (Yii::$app->has('session')) {
            $sessionRoles = Yii::$app->session->get(BioenlaceSessionPermissions::SESSION_PREFIX_ROLES, []);
            if (is_array($sessionRoles)) {
                foreach ($roleList as $role) {
                    if (in_array($role, $sessionRoles, true)) {
                        return true;
                    }
                }
            }
        }

        if (Yii::$app->has('authManager')) {
            foreach ($roleList as $role) {
                if (Yii::$app->authManager->checkAccess($userId, $role)) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function hasPermission($permission, $superAdminAllowed = true): bool
    {
        if (Yii::$app->user->isGuest) {
            return false;
        }

        $userId = (int) Yii::$app->user->id;
        if ($userId <= 0) {
            return false;
        }

        if ($superAdminAllowed && BioenlaceAccessChecker::isSuperadminUserId($userId)) {
            return true;
        }

        return BioenlaceAccessChecker::userCanPermissionKey($userId, (string) $permission);
    }

    public static function assignRole($userId, $roleName): bool
    {
        try {
            Yii::$app->db->createCommand()->insert('auth_assignment', [
                'user_id' => $userId,
                'item_name' => $roleName,
                'created_at' => time(),
            ])->execute();
            BioenlaceRbacRevision::bump();
            self::refreshSessionPermissionsForUserId((int) $userId);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function revokeRole($userId, $roleName): bool
    {
        $result = Yii::$app->db->createCommand()
            ->delete('auth_assignment', ['user_id' => $userId, 'item_name' => $roleName])
            ->execute() > 0;
        if ($result) {
            BioenlaceRbacRevision::bump();
            self::refreshSessionPermissionsForUserId((int) $userId);
        }

        return $result;
    }

    /**
     * @return array<int, string>
     */
    public static function getStatusList(): array
    {
        return [
            self::STATUS_ACTIVE => 'Activo',
            self::STATUS_INACTIVE => 'Inactivo',
            self::STATUS_BANNED => 'Baneado',
        ];
    }

    public static function getStatusValue($val): string
    {
        $list = self::getStatusList();

        return $list[$val] ?? (string) $val;
    }

    public function validateEmailConfirmedUnique(): void
    {
        if (!$this->email) {
            return;
        }

        $exists = static::findOne([
            'email' => $this->email,
            'email_confirmed' => 1,
        ]);
        if ($exists !== null && (int) $exists->id !== (int) $this->id) {
            $this->addError('email', 'Este e-mail ya existe');
        }
    }

    public function validateBindToIp(): void
    {
        if (!$this->bind_to_ip) {
            return;
        }

        foreach (explode(',', $this->bind_to_ip) as $ip) {
            if (!filter_var(trim($ip), FILTER_VALIDATE_IP)) {
                $this->addError('bind_to_ip', 'Formato incorrecto. Ingrese IPs válidas separadas por coma');
            }
        }
    }

    /**
     * Roles RBAC asignados (admin legacy webvimark).
     *
     * @return \yii\db\ActiveQuery
     */
    public function getRoles()
    {
        return $this->hasMany(
            \common\models\rbac\AuthRole::class,
            ['name' => 'item_name']
        )->viaTable('auth_assignment', ['user_id' => 'id']);
    }

    public function beforeSave($insert)
    {
        if ($insert) {
            if (PHP_SAPI !== 'cli' && Yii::$app->request instanceof \yii\web\Request) {
                $this->registration_ip = Yii::$app->request->userIP;
            }
            $this->generateAuthKey();
        } elseif (PHP_SAPI !== 'cli' && !Yii::$app->user->isGuest) {
            if ((int) Yii::$app->user->id === (int) $this->id) {
                $this->status = self::STATUS_ACTIVE;
                if (Yii::$app->user->isSuperadmin && (int) $this->superadmin !== 1) {
                    $this->superadmin = 1;
                }
            }
            if (isset($this->oldAttributes['superadmin'])
                && !Yii::$app->user->isSuperadmin
                && (int) $this->oldAttributes['superadmin'] === 1
            ) {
                return false;
            }
        }

        if ($this->password && $this->scenario !== self::SCENARIO_INVITE) {
            $this->setPassword($this->password);
        }

        return parent::beforeSave($insert);
    }

    public function beforeDelete()
    {
        if (PHP_SAPI !== 'cli' && !Yii::$app->user->isGuest) {
            if ((int) Yii::$app->user->id === (int) $this->id) {
                return false;
            }
            if (!Yii::$app->user->isSuperadmin && (int) $this->superadmin === 1) {
                return false;
            }
        }

        return parent::beforeDelete();
    }

    public static function getPorRolPorEfector($rol, $id_efector)
    {
        $query = new yii\db\Query();
        $query->select(['`user`.*'])
            ->from('user')
            ->where('auth_item.name', $rol)
            ->andWhere('auth_item.type', 1)
            ->andWhere('user_efector.id_efector', $id_efector)
            ->leftJoin('user_efector', '`user_efector`.`id_user` = `user`.`id`')
            ->leftJoin('auth_item', '`auth_item`.`name` = `auth_assignment`.`item_name`')
            ->leftJoin('auth_assignment', '`user`.`id` = `auth_assignment`.`user_id`');

        return $query->createCommand()->queryAll();
    }

    public function getNombrepersona($id)
    {
        $consulta_persona = Persona::findOne(['id_persona' => $id]);
        $apellido_persona = $consulta_persona->apellido;
        $nombre_persona = $consulta_persona->nombre . ' ' . $consulta_persona->otro_nombre;

        return $apellido_persona . ', ' . $nombre_persona;
    }

    public function actualizarIduserpersona($idpersona, $iduser)
    {
        $actualizar_persona = Persona::findOne(['id_persona' => $idpersona]);
        if ($actualizar_persona === null) {
            return;
        }
        $actualizar_persona->id_user = $iduser;
        $actualizar_persona->scenario = 'scenarioregistrar';
        $actualizar_persona->save();
    }

    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);

        if (!$insert) {
            return;
        }

        $request = Yii::$app->getRequest();
        if ($request instanceof \yii\web\Request) {
            $idpersona = $request->getQueryParam('id');
            if ($idpersona !== null && $idpersona !== '') {
                self::actualizarIduserpersona($idpersona, $this->id);
            }
        }
    }

    /**
     * @param string|array<int|string, string> $roles
     * @return list<string>
     */
    private static function normalizeRoleList($roles): array
    {
        $raw = is_array($roles) ? $roles : [$roles];
        $out = [];
        foreach ($raw as $role) {
            if (is_string($role)) {
                $role = trim($role);
                if ($role !== '') {
                    $out[] = $role;
                }
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param string|array<int|string, string> $route
     * @return list<string>
     */
    private static function expandRouteCandidates($route): array
    {
        $raw = is_array($route) ? $route : [$route];
        $out = [];
        foreach ($raw as $part) {
            if (is_string($part) && $part !== '') {
                $out[] = self::normalizeRbacRoute($part);
            }
        }
        if ($out === [] && is_array($route) && isset($route[0])) {
            $built = self::normalizeRbacRouteFromArray($route);
            if ($built !== '') {
                $out[] = $built;
            }
        }

        return array_values(array_unique(array_filter($out)));
    }

    /**
     * @param array<int|string, string> $route
     */
    private static function normalizeRbacRouteFromArray(array $route): string
    {
        $parts = [];
        foreach ($route as $k => $v) {
            if ($k === 0 && is_string($v)) {
                $parts[] = trim($v, '/');
            } elseif (is_string($k) && $k !== '0' && is_scalar($v)) {
                $parts[] = $k . '/' . $v;
            }
        }

        return self::normalizeRbacRoute(implode('/', $parts));
    }

    private static function normalizeRbacRoute(string $route): string
    {
        $route = '/' . ltrim(trim($route), '/');
        if (strncmp($route, '/api/', 5) === 0) {
            return BioenlaceSessionPermissions::unifyRoute($route);
        }
        if (preg_match('#^/(frontend|admin)/#', $route) === 1) {
            return $route;
        }

        $prefix = trim((string) (Yii::$app->params['path'] ?? ''), '/');
        if ($prefix !== '') {
            return '/' . $prefix . $route;
        }

        return $route;
    }

    private static function refreshSessionPermissionsForUserId(int $userId): void
    {
        if ($userId <= 0 || Yii::$app->user->isGuest || (int) Yii::$app->user->id !== $userId) {
            return;
        }
        $identity = Yii::$app->user->identity;
        if ($identity !== null) {
            BioenlaceAccessChecker::refreshForIdentity($identity);
        }
    }
}
