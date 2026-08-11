<?php

namespace common\components\Platform\Core\Auth;

use common\components\Platform\Core\Auth\DemoSandboxCaptchaService;
use common\models\Clinical\Encounter;
use common\models\Clinical\EncounterDefinition;
use common\models\Efector;
use common\models\Person\Persona;
use common\models\Platform\DemoSandboxAccess;
use common\models\Platform\DemoSandboxSession;
use common\models\Servicio;
use common\models\User;
use Firebase\JWT\JWT;
use Yii;
use yii\db\Query;

/**
 * Acceso demo sandbox desde el sitio institucional (código de un solo uso).
 *
 * Staff: siempre efímero. El médico + seed se crean en issue() (POST demo-acceso),
 * no en la cuenta legacy medico_med_general_*.
 *
 * @see web/docs/plans/demo-sandbox-institucional/design.md
 */
final class DemoSandboxAccessService
{
    public const MODE_EPHEMERAL = 'ephemeral';

    public const MODE_SHARED_ACCOUNT = 'shared_account';

    public static function isEnabled(): bool
    {
        return (bool) (Yii::$app->params['demo_sandbox_habilitado'] ?? false);
    }

    /**
     * Perfiles ofrecidos al CTA.
     *
     * @return list<array{role: string, label: string, mode: string}>
     */
    public static function listProfiles(): array
    {
        $cfg = self::config();
        $out = [];

        $profiles = is_array($cfg['profiles'] ?? null) ? $cfg['profiles'] : [];
        if ($profiles !== []) {
            foreach ($profiles as $role => $row) {
                if (!is_string($role) || !is_array($row)) {
                    continue;
                }
                // Staff siempre efímero en el catálogo público.
                $mode = DemoSandboxAccess::isEphemeralStaffRole($role)
                    ? self::MODE_EPHEMERAL
                    : self::normalizeMode((string) ($row['mode'] ?? self::MODE_EPHEMERAL));
                if ($mode === self::MODE_SHARED_ACCOUNT) {
                    $username = trim((string) ($row['username'] ?? ''));
                    if ($username === '') {
                        continue;
                    }
                }
                $out[] = [
                    'role' => $role,
                    'label' => trim((string) ($row['label'] ?? $role)),
                    'mode' => $mode,
                ];
            }

            return $out;
        }

        // Sin profiles: staff efímero por defecto.
        $out[] = [
            'role' => DemoSandboxAccess::ROLE_STAFF,
            'label' => 'Médico demo (captura y turnos)',
            'mode' => self::MODE_EPHEMERAL,
        ];

        $accounts = is_array($cfg['accounts'] ?? null) ? $cfg['accounts'] : [];
        if (isset($accounts[DemoSandboxAccess::ROLE_PACIENTE]) && is_array($accounts[DemoSandboxAccess::ROLE_PACIENTE])) {
            $username = trim((string) ($accounts[DemoSandboxAccess::ROLE_PACIENTE]['username'] ?? ''));
            if ($username !== '') {
                $out[] = [
                    'role' => DemoSandboxAccess::ROLE_PACIENTE,
                    'label' => trim((string) ($accounts[DemoSandboxAccess::ROLE_PACIENTE]['label'] ?? 'Paciente demo')),
                    'mode' => self::MODE_SHARED_ACCOUNT,
                ];
            }
        }

        return $out;
    }

    /**
     * Crea un código de un solo uso y URL de entrada a la app.
     * Staff: provisiona médico efímero + seed aquí (antes del redirect).
     *
     * @param array<string, mixed> $input role, email?, website? (honeypot), captcha?, captcha_challenge_id?, ip?, user_agent?
     * @return array{
     *     enter_url: string,
     *     expires_at: string,
     *     role: string,
     *     label: string,
     *     mode: string,
     *     username?: string,
     *     id_efector?: int,
     *     id_pes?: int
     * }
     */
    public function issue(array $input): array
    {
        if (!self::isEnabled()) {
            throw new \DomainException('El acceso demo no está habilitado en este entorno.');
        }

        $honeypot = trim((string) ($input['website'] ?? $input['company'] ?? ''));
        if ($honeypot !== '') {
            throw new \DomainException('Solicitud rechazada.');
        }

        $role = trim((string) ($input['role'] ?? DemoSandboxAccess::ROLE_STAFF));
        if ($role === '') {
            $role = DemoSandboxAccess::ROLE_STAFF;
        }
        if (!in_array($role, DemoSandboxAccess::roleValues(), true)) {
            throw new \DomainException('Rol demo inválido.');
        }

        $profile = $this->resolveProfile($role);
        // Staff: hard-force ephemeral (ignora accounts/shared legacy).
        $mode = DemoSandboxAccess::isEphemeralStaffRole($role)
            ? self::MODE_EPHEMERAL
            : $profile['mode'];

        $ip = trim((string) ($input['ip'] ?? ''));
        if ($ip === '') {
            $ip = (string) (Yii::$app->request->userIP ?? '');
        }
        $this->assertRateLimit($ip !== '' ? $ip : null);

        (new DemoSandboxCaptchaService())->assertValid(
            isset($input['captcha_challenge_id']) ? (string) $input['captcha_challenge_id'] : null,
            isset($input['captcha']) ? (string) $input['captcha'] : (isset($input['verifyCode']) ? (string) $input['verifyCode'] : null)
        );

        $ttl = max(60, (int) ($this->config()['ttl_seconds'] ?? 900));
        $now = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', time() + $ttl);
        $plain = bin2hex(random_bytes(24));

        $idUser = null;
        $username = null;
        $idEfector = null;
        $idPes = null;
        $sessionId = null;
        $efectorCodigoSisa = '';
        $efectorNombre = '';
        $seedPayload = [];

        if ($mode === self::MODE_EPHEMERAL) {
            // Provision ANTES de persistir el código: así id_user nunca queda null/0 ni apunta al seed 863.
            $staffKind = $role === DemoSandboxAccess::ROLE_ENFERMERIA ? 'enfermeria' : 'medico';
            $provisioned = (new DemoSandboxSessionService())->provisionEphemeralStaff(null, $staffKind);
            $user = $provisioned['user'];
            $session = $provisioned['session'];
            $username = (string) $user->username;
            if ($username === '' || str_starts_with($username, 'medico_med_general_')) {
                throw new \RuntimeException(
                    'Provision demo devolvió usuario legacy/inválido (' . $username . ').'
                );
            }
            $idUser = (int) $user->id;
            $idEfector = (int) $session->id_efector;
            DemoSandboxSessionService::assertIdEfectorEsPlantillaDev($idEfector);
            $idPes = (int) $session->id_pes;
            $sessionId = (int) $session->id;
            $efectorRow = \common\models\Efector::findOne($idEfector);
            $efectorCodigoSisa = $efectorRow !== null ? trim((string) $efectorRow->codigo_sisa) : '';
            $efectorNombre = $efectorRow !== null ? trim((string) $efectorRow->nombre) : '';
            $seedPayload = $session->getSeedPayload();
        } else {
            $sharedUsername = $profile['username'];
            $user = User::findOne(['username' => $sharedUsername]);
            if ($user === null || (int) $user->status !== User::STATUS_ACTIVE) {
                throw new \DomainException(
                    'La cuenta demo no está disponible. Ejecutá el seed clínico o revisá demo_sandbox.'
                );
            }
            $idUser = (int) $user->id;
            $username = (string) $user->username;
        }

        $row = new DemoSandboxAccess();
        $row->code_hash = self::hashCode($plain);
        $row->role = $role;
        if ($row->hasAttribute('mode')) {
            $row->mode = $mode;
        }
        $row->username = $username;
        $row->id_user = $idUser;
        $email = trim((string) ($input['email'] ?? ''));
        $row->email = $email !== '' ? mb_substr($email, 0, 255) : null;
        $row->ip = $ip !== '' ? mb_substr($ip, 0, 45) : null;
        $ua = trim((string) ($input['user_agent'] ?? ''));
        if ($ua === '' && Yii::$app->request !== null) {
            $ua = (string) Yii::$app->request->userAgent;
        }
        $row->user_agent = $ua !== '' ? mb_substr($ua, 0, 512) : null;
        $row->expires_at = $expiresAt;
        $row->created_at = $now;
        if (!$row->save(false)) {
            throw new \RuntimeException('No se pudo persistir el acceso demo.');
        }

        if ($sessionId !== null && $sessionId > 0) {
            DemoSandboxSession::updateAll(
                ['id_access' => (int) $row->id],
                ['id' => $sessionId]
            );
        }

        Yii::info(
            'demo-acceso issue mode=' . $mode
            . ' user=' . (string) $username
            . ' efector=' . (int) ($idEfector ?? 0)
            . ' sisa=' . (string) ($efectorCodigoSisa ?? '')
            . ' pes=' . (int) ($idPes ?? 0),
            'demo.sandbox'
        );

        $out = [
            'enter_url' => $this->buildEnterUrl($plain),
            'expires_at' => $expiresAt,
            'role' => $role,
            'label' => $profile['label'],
            'mode' => $mode,
            'username' => (string) $username,
        ];
        if ($idEfector !== null) {
            $out['id_efector'] = $idEfector;
            if (($efectorCodigoSisa ?? '') !== '') {
                $out['efector_codigo_sisa'] = $efectorCodigoSisa;
            }
            if (($efectorNombre ?? '') !== '') {
                $out['efector_nombre'] = $efectorNombre;
            }
        }
        if ($idPes !== null) {
            $out['id_pes'] = $idPes;
        }
        if ($seedPayload !== []) {
            $out['seed'] = [
                'pacientes' => count($seedPayload['paciente_ids'] ?? []),
                'turnos' => count($seedPayload['turno_ids'] ?? []),
                'encounters' => count($seedPayload['encounter_ids'] ?? []),
                'async_vr' => count($seedPayload['async_encounter_ids'] ?? []),
                'guardia' => count($seedPayload['guardia_ids'] ?? []),
                'internacion' => count($seedPayload['internacion_ids'] ?? []),
                'fecha_turnos' => (string) ($seedPayload['fecha_turnos'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * Consume el código y devuelve el usuario a loguear.
     *
     * @return array{user: User, session_id: int|null}
     */
    public function consume(string $plainCode): array
    {
        if (!self::isEnabled()) {
            throw new \DomainException('El acceso demo no está habilitado en este entorno.');
        }

        $plainCode = trim($plainCode);
        if ($plainCode === '' || strlen($plainCode) > 128) {
            throw new \DomainException('Código inválido o expirado.');
        }

        $hash = self::hashCode($plainCode);
        /** @var DemoSandboxAccess|null $row */
        $row = DemoSandboxAccess::find()
            ->where(['code_hash' => $hash])
            ->one();

        if ($row === null || $row->isUsed() || $row->isExpired()) {
            throw new \DomainException('Código inválido o expirado.');
        }

        $sessionId = null;

        // Staff efímero: usuario ya provisionado en issue (demo_m_* / demo_e_*), nunca seed legacy.
        if (DemoSandboxAccess::isEphemeralStaffRole((string) $row->role)) {
            $username = (string) ($row->username ?? '');
            $prefix = (string) $row->role === DemoSandboxAccess::ROLE_ENFERMERIA ? 'demo_e_' : 'demo_m_';
            if ((int) $row->id_user <= 0 || str_starts_with($username, 'medico_med_general_')) {
                throw new \DomainException(
                    'Código demo inválido (cuenta legacy). Solicitá un acceso nuevo desde el sitio institucional.'
                );
            }
            $user = User::findOne((int) $row->id_user);
            if ($user === null || (int) $user->status !== User::STATUS_ACTIVE) {
                throw new \DomainException('La cuenta demo no está disponible.');
            }
            if (!str_starts_with((string) $user->username, $prefix)) {
                throw new \DomainException(
                    'Usuario demo inesperado (' . $user->username . '). Solicitá un acceso nuevo.'
                );
            }
            /** @var DemoSandboxSession|null $session */
            $session = DemoSandboxSession::find()
                ->where(['id_access' => (int) $row->id, 'purged_at' => null])
                ->orderBy(['id' => SORT_DESC])
                ->one();
            if ($session === null) {
                $session = DemoSandboxSession::find()
                    ->where(['id_user' => (int) $user->id, 'purged_at' => null])
                    ->orderBy(['id' => SORT_DESC])
                    ->one();
            }
            $sessionId = $session !== null ? (int) $session->id : null;
        } else {
            $user = User::findOne((int) $row->id_user);
            if ($user === null || (int) $user->status !== User::STATUS_ACTIVE) {
                throw new \DomainException('La cuenta demo no está disponible.');
            }
        }

        $row->used_at = date('Y-m-d H:i:s');
        $row->save(false);

        return [
            'user' => $user,
            'session_id' => $sessionId,
        ];
    }

    public static function hashCode(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /**
     * @return array{username: string|null, label: string, mode: string}
     */
    private function resolveProfile(string $role): array
    {
        $cfg = $this->config();
        $profiles = is_array($cfg['profiles'] ?? null) ? $cfg['profiles'] : [];
        if (isset($profiles[$role]) && is_array($profiles[$role])) {
            $row = $profiles[$role];
            $mode = DemoSandboxAccess::isEphemeralStaffRole($role)
                ? self::MODE_EPHEMERAL
                : self::normalizeMode((string) ($row['mode'] ?? self::MODE_EPHEMERAL));

            return [
                'username' => trim((string) ($row['username'] ?? '')) ?: null,
                'label' => trim((string) ($row['label'] ?? $role)),
                'mode' => $mode,
            ];
        }

        if ($role === DemoSandboxAccess::ROLE_STAFF) {
            return [
                'username' => null,
                'label' => 'Médico demo',
                'mode' => self::MODE_EPHEMERAL,
            ];
        }
        if ($role === DemoSandboxAccess::ROLE_ENFERMERIA) {
            return [
                'username' => null,
                'label' => 'Enfermería demo',
                'mode' => self::MODE_EPHEMERAL,
            ];
        }

        $accounts = is_array($cfg['accounts'] ?? null) ? $cfg['accounts'] : [];
        if (isset($accounts[$role]) && is_array($accounts[$role])) {
            $username = trim((string) ($accounts[$role]['username'] ?? ''));
            if ($username === '') {
                throw new \DomainException('No hay cuenta demo configurada para ese perfil.');
            }

            return [
                'username' => $username,
                'label' => trim((string) ($accounts[$role]['label'] ?? $role)),
                'mode' => self::MODE_SHARED_ACCOUNT,
            ];
        }

        throw new \DomainException('No hay perfil demo configurado para ese rol.');
    }

    private static function normalizeMode(string $mode): string
    {
        $mode = strtolower(trim($mode));
        if ($mode === self::MODE_SHARED_ACCOUNT || $mode === 'shared') {
            return self::MODE_SHARED_ACCOUNT;
        }

        return self::MODE_EPHEMERAL;
    }

    private function assertRateLimit(?string $ip): void
    {
        if ($ip === null || $ip === '') {
            return;
        }
        $max = max(1, (int) ($this->config()['max_per_ip_hour'] ?? 10));
        $since = date('Y-m-d H:i:s', time() - 3600);
        $count = (int) (new Query())
            ->from(DemoSandboxAccess::tableName())
            ->where(['ip' => $ip])
            ->andWhere(['>=', 'created_at', $since])
            ->count();
        if ($count >= $max) {
            throw new \DomainException('Demasiados intentos. Probá de nuevo más tarde.');
        }
    }

    /**
     * Acceso demo para app Personal de Salud: provisiona médico efímero + seed y emite JWT
     * con contexto operativo (AMB en plantilla DEV). Sin password ni enter_url.
     *
     * Captcha: solo si demo_sandbox.require_captcha_mobile=true (default false).
     *
     * @param array<string, mixed> $input email?, website? (honeypot), captcha?, captcha_challenge_id?, ip?, user_agent?
     * @return array{
     *     token: string,
     *     expires_at: string,
     *     user: array{id: int, name: string, email: string, role: string},
     *     persona: array{id_persona: int, nombre: string, apellido: string, documento: string},
     *     session: array{
     *         id_efector: int,
     *         efector_nombre: string,
     *         efector_codigo_sisa: string,
     *         id_servicio: int,
     *         servicio_nombre: string,
     *         id_profesional_efector_servicio: int,
     *         encounter_class: string,
     *         encounter_class_label: string,
     *         demo_sandbox_session_id: int
     *     },
     *     seed: array<string, mixed>,
     *     username: string,
     *     mode: string,
     *     role: string,
     *     label: string
     * }
     */
    public function issueMobileStaff(array $input): array
    {
        if (!self::isEnabled()) {
            throw new \DomainException('El acceso demo no está habilitado en este entorno.');
        }

        $honeypot = trim((string) ($input['website'] ?? $input['company'] ?? ''));
        if ($honeypot !== '') {
            throw new \DomainException('Solicitud rechazada.');
        }

        $ip = trim((string) ($input['ip'] ?? ''));
        if ($ip === '') {
            $ip = (string) (Yii::$app->request->userIP ?? '');
        }
        $this->assertRateLimit($ip !== '' ? $ip : null);

        $cfg = $this->config();
        if ((bool) ($cfg['require_captcha_mobile'] ?? false)) {
            (new DemoSandboxCaptchaService())->assertValid(
                isset($input['captcha_challenge_id']) ? (string) $input['captcha_challenge_id'] : null,
                isset($input['captcha']) ? (string) $input['captcha'] : (isset($input['verifyCode']) ? (string) $input['verifyCode'] : null)
            );
        }

        $mobileRole = trim((string) ($input['role'] ?? DemoSandboxAccess::ROLE_STAFF));
        if ($mobileRole === '') {
            $mobileRole = DemoSandboxAccess::ROLE_STAFF;
        }
        $staffKind = $mobileRole === DemoSandboxAccess::ROLE_ENFERMERIA ? 'enfermeria' : 'medico';
        $provisioned = (new DemoSandboxSessionService())->provisionEphemeralStaff(null, $staffKind);
        $user = $provisioned['user'];
        /** @var DemoSandboxSession $session */
        $session = $provisioned['session'];
        $username = (string) $user->username;
        if ($username === '' || str_starts_with($username, 'medico_med_general_')) {
            throw new \RuntimeException(
                'Provision demo devolvió usuario legacy/inválido (' . $username . ').'
            );
        }

        $idEfector = (int) $session->id_efector;
        DemoSandboxSessionService::assertIdEfectorEsPlantillaDev($idEfector);
        $idPes = (int) $session->id_pes;
        $idServicio = (int) $session->id_servicio;
        $idPersona = (int) $session->id_persona;

        $persona = Persona::findOne($idPersona);
        if ($persona === null) {
            throw new \RuntimeException('Persona demo no encontrada tras el provision.');
        }

        $efector = Efector::findOne($idEfector);
        $efectorNombre = $efector !== null ? trim((string) $efector->nombre) : 'Demo DEV';
        $efectorSisa = $efector !== null ? trim((string) $efector->codigo_sisa) : '';
        $servicio = Servicio::findOne($idServicio);
        $servicioNombre = $servicio !== null ? trim((string) $servicio->nombre) : 'MED GENERAL';

        $sessionTtl = max(600, (int) ($cfg['session_ttl_seconds'] ?? 14400));
        $now = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', time() + $sessionTtl);
        $plain = bin2hex(random_bytes(24));

        $row = new DemoSandboxAccess();
        $row->code_hash = self::hashCode($plain);
        $row->role = $mobileRole === DemoSandboxAccess::ROLE_ENFERMERIA
            ? DemoSandboxAccess::ROLE_ENFERMERIA
            : DemoSandboxAccess::ROLE_STAFF;
        if ($row->hasAttribute('mode')) {
            $row->mode = self::MODE_EPHEMERAL;
        }
        $row->username = $username;
        $row->id_user = (int) $user->id;
        $email = trim((string) ($input['email'] ?? ''));
        $row->email = $email !== '' ? mb_substr($email, 0, 255) : null;
        $row->ip = $ip !== '' ? mb_substr($ip, 0, 45) : null;
        $ua = trim((string) ($input['user_agent'] ?? ''));
        if ($ua === '' && Yii::$app->request !== null) {
            $ua = (string) Yii::$app->request->userAgent;
        }
        $row->user_agent = $ua !== '' ? mb_substr($ua, 0, 512) : null;
        $row->expires_at = $expiresAt;
        $row->created_at = $now;
        $row->used_at = $now;
        if (!$row->save(false)) {
            throw new \RuntimeException('No se pudo persistir el acceso demo móvil.');
        }

        DemoSandboxSession::updateAll(
            ['id_access' => (int) $row->id],
            ['id' => (int) $session->id]
        );

        $seedPayload = $session->getSeedPayload();
        $encounterClass = Encounter::ENCOUNTER_CLASS_AMB;
        $encounterLabel = (string) (EncounterDefinition::ENCOUNTER_CLASS[$encounterClass] ?? 'Ambulatoria');

        $payload = [
            'user_id' => (int) $user->id,
            'email' => (string) $user->email,
            'role' => 'medico',
            'id_persona' => $idPersona,
            'id_efector' => $idEfector,
            'id_profesional_efector_servicio' => $idPes,
            'servicio_actual' => $idServicio,
            'encounter_class' => $encounterClass,
            'demo_sandbox' => true,
            'demo_sandbox_session_id' => (int) $session->id,
            'iat' => time(),
            'exp' => time() + $sessionTtl,
        ];
        $token = JWT::encode($payload, Yii::$app->params['jwtSecret'], 'HS256');

        Yii::info(
            'demo-acceso-mobile user=' . $username
            . ' efector=' . $idEfector
            . ' sisa=' . $efectorSisa
            . ' pes=' . $idPes,
            'demo.sandbox'
        );

        return [
            'token' => $token,
            'expires_at' => $expiresAt,
            'user' => [
                'id' => (int) $user->id,
                'name' => (string) $user->username,
                'email' => (string) $user->email,
                'role' => 'medico',
            ],
            'persona' => [
                'id_persona' => $idPersona,
                'nombre' => (string) $persona->nombre,
                'apellido' => (string) $persona->apellido,
                'documento' => (string) $persona->documento,
            ],
            'session' => [
                'id_efector' => $idEfector,
                'efector_nombre' => $efectorNombre,
                'efector_codigo_sisa' => $efectorSisa,
                'id_servicio' => $idServicio,
                'servicio_nombre' => $servicioNombre,
                'id_profesional_efector_servicio' => $idPes,
                'encounter_class' => $encounterClass,
                'encounter_class_label' => $encounterLabel,
                'demo_sandbox_session_id' => (int) $session->id,
            ],
            'seed' => [
                'pacientes' => count($seedPayload['paciente_ids'] ?? []),
                'turnos' => count($seedPayload['turno_ids'] ?? []),
                'encounters' => count($seedPayload['encounter_ids'] ?? []),
                'async_vr' => count($seedPayload['async_encounter_ids'] ?? []),
                'guardia' => count($seedPayload['guardia_ids'] ?? []),
                'internacion' => count($seedPayload['internacion_ids'] ?? []),
                'fecha_turnos' => (string) ($seedPayload['fecha_turnos'] ?? ''),
            ],
            'username' => $username,
            'mode' => self::MODE_EPHEMERAL,
            'role' => DemoSandboxAccess::ROLE_STAFF,
            'label' => 'Médico demo (captura y turnos)',
        ];
    }

    private function buildEnterUrl(string $plainCode): string
    {
        $base = trim((string) ($this->config()['app_base_url'] ?? ''));
        if ($base === '') {
            return Yii::$app->urlManager->createAbsoluteUrl([
                'site/demo-entrar',
                'code' => $plainCode,
            ]);
        }

        return rtrim($base, '/') . '/site/demo-entrar?code=' . rawurlencode($plainCode);
    }

    /**
     * @return array<string, mixed>
     */
    private static function config(): array
    {
        $cfg = Yii::$app->params['demo_sandbox'] ?? [];

        return is_array($cfg) ? $cfg : [];
    }
}
