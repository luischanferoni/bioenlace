<?php

namespace common\components\Domain\Organization\Efector\Application\Service;

use Yii;

/**
 * Anti-abuso para endpoints públicos de alta: honeypot + rate limit por IP.
 *
 * El honeypot es un campo oculto (`website` o `company`) que un humano nunca
 * completa; si llega con valor, se rechaza. El rate limit usa cache con una
 * ventana fija de una hora por IP.
 */
final class SignupAbuseGuard
{
    private const CACHE_PREFIX = 'signup_abuse:';

    /**
     * @param array<string, mixed> $payload body del request
     */
    public function assertAllowed(array $payload): void
    {
        $this->assertHoneypot($payload);
        $this->assertRateLimit();
    }

    private function assertHoneypot(array $payload): void
    {
        $honeypot = trim((string) ($payload['website'] ?? $payload['company'] ?? ''));
        if ($honeypot !== '') {
            throw new \DomainException('Solicitud rechazada.');
        }
    }

    private function assertRateLimit(): void
    {
        $ip = '';
        if (Yii::$app->has('request', true) && Yii::$app->request instanceof \yii\web\Request) {
            $ip = trim((string) Yii::$app->request->getUserIP());
        }
        if ($ip === '') {
            return;
        }

        $key = self::CACHE_PREFIX . 'rl:' . hash('sha256', $ip);
        $max = max(1, (int) (Yii::$app->params['signupMaxPerIpHour'] ?? 20));
        $count = (int) Yii::$app->cache->get($key);
        if ($count >= $max) {
            throw new \DomainException('Demasiados intentos. Probá de nuevo más tarde.');
        }
        Yii::$app->cache->set($key, $count + 1, 3600);
    }
}
