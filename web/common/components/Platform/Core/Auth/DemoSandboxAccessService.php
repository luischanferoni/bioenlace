<?php

namespace common\components\Platform\Core\Auth;

use common\models\Platform\DemoSandboxAccess;
use common\models\User;
use Yii;
use yii\db\Query;

/**
 * Acceso demo sandbox desde el sitio institucional (código de un solo uso).
 *
 * Params: demo_sandbox_habilitado + demo_sandbox (accounts, ttl, rate limit).
 *
 * @see web/docs/plans/demo-sandbox-institucional/design.md
 */
final class DemoSandboxAccessService
{
    public static function isEnabled(): bool
    {
        return (bool) (Yii::$app->params['demo_sandbox_habilitado'] ?? false);
    }

    /**
     * Perfiles ofrecidos al CTA (solo los que tienen username configurado).
     *
     * @return list<array{role: string, label: string}>
     */
    public static function listProfiles(): array
    {
        $cfg = self::config();
        $accounts = is_array($cfg['accounts'] ?? null) ? $cfg['accounts'] : [];
        $out = [];
        foreach ($accounts as $role => $row) {
            if (!is_string($role) || !is_array($row)) {
                continue;
            }
            $username = trim((string) ($row['username'] ?? ''));
            if ($username === '') {
                continue;
            }
            $out[] = [
                'role' => $role,
                'label' => trim((string) ($row['label'] ?? $role)),
            ];
        }

        return $out;
    }

    /**
     * Crea un código de un solo uso y URL de entrada a la app.
     *
     * @param array<string, mixed> $input role, email?, website? (honeypot), ip?, user_agent?
     * @return array{enter_url: string, expires_at: string, role: string, label: string}
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
        if (!in_array($role, DemoSandboxAccess::roleValues(), true)) {
            throw new \DomainException('Rol demo inválido.');
        }

        $account = $this->resolveAccount($role);
        $username = $account['username'];
        $user = User::findOne(['username' => $username]);
        if ($user === null || (int) $user->status !== User::STATUS_ACTIVE) {
            throw new \DomainException(
                'La cuenta demo no está disponible. Ejecutá el seed clínico o revisá demo_sandbox.accounts.'
            );
        }

        $ip = trim((string) ($input['ip'] ?? ''));
        if ($ip === '') {
            $ip = (string) (Yii::$app->request->userIP ?? '');
        }
        $this->assertRateLimit($ip !== '' ? $ip : null);

        $ttl = max(60, (int) ($this->config()['ttl_seconds'] ?? 900));
        $now = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', time() + $ttl);
        $plain = bin2hex(random_bytes(24));

        $row = new DemoSandboxAccess();
        $row->code_hash = self::hashCode($plain);
        $row->role = $role;
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
        if (!$row->save(false)) {
            throw new \RuntimeException('No se pudo persistir el acceso demo.');
        }

        return [
            'enter_url' => $this->buildEnterUrl($plain),
            'expires_at' => $expiresAt,
            'role' => $role,
            'label' => $account['label'],
        ];
    }

    /**
     * Consume el código y devuelve el usuario a loguear.
     */
    public function consume(string $plainCode): User
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

        $user = User::findOne((int) $row->id_user);
        if ($user === null || (int) $user->status !== User::STATUS_ACTIVE) {
            throw new \DomainException('La cuenta demo no está disponible.');
        }

        $row->used_at = date('Y-m-d H:i:s');
        $row->save(false);

        return $user;
    }

    public static function hashCode(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /**
     * @return array{username: string, label: string}
     */
    private function resolveAccount(string $role): array
    {
        $accounts = $this->config()['accounts'] ?? [];
        if (!is_array($accounts) || !isset($accounts[$role]) || !is_array($accounts[$role])) {
            throw new \DomainException('No hay cuenta demo configurada para ese perfil.');
        }
        $username = trim((string) ($accounts[$role]['username'] ?? ''));
        if ($username === '') {
            throw new \DomainException('No hay cuenta demo configurada para ese perfil.');
        }

        return [
            'username' => $username,
            'label' => trim((string) ($accounts[$role]['label'] ?? $role)),
        ];
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
