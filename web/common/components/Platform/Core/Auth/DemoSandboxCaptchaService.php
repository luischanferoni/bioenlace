<?php

namespace common\components\Platform\Core\Auth;

use Yii;

/**
 * Captcha sin sesión PHP: challenge en cache + imagen data-URI (institucional → API cross-origin).
 */
final class DemoSandboxCaptchaService
{
    private const CACHE_PREFIX = 'demo_sandbox_captcha:';

    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function isRequired(): bool
    {
        if (!DemoSandboxAccessService::isEnabled()) {
            return false;
        }
        $cfg = $this->config();

        return (bool) ($cfg['require_captcha'] ?? true);
    }

    /**
     * @return array{challenge_id: string, image_data_url: string, expires_in: int}
     */
    public function issue(): array
    {
        if (!DemoSandboxAccessService::isEnabled()) {
            throw new \DomainException('El acceso demo no está habilitado en este entorno.');
        }

        $ttl = max(60, (int) ($this->config()['captcha_ttl_seconds'] ?? 300));
        $length = max(3, min(6, (int) ($this->config()['captcha_length'] ?? 4)));
        $code = $this->randomCode($length);
        $challengeId = bin2hex(random_bytes(16));

        Yii::$app->cache->set(
            self::CACHE_PREFIX . $challengeId,
            hash('sha256', strtoupper($code)),
            $ttl
        );

        return [
            'challenge_id' => $challengeId,
            'image_data_url' => $this->renderDataUrl($code),
            'expires_in' => $ttl,
        ];
    }

    /**
     * Valida y consume el challenge (un solo uso).
     */
    public function assertValid(?string $challengeId, ?string $code): void
    {
        if (!$this->isRequired()) {
            return;
        }

        $challengeId = trim((string) $challengeId);
        $code = strtoupper(trim((string) $code));
        if ($challengeId === '' || $code === '' || strlen($challengeId) > 64 || strlen($code) > 16) {
            throw new \DomainException('Completá el captcha.');
        }

        $key = self::CACHE_PREFIX . $challengeId;
        $expected = Yii::$app->cache->get($key);
        Yii::$app->cache->delete($key);

        if (!is_string($expected) || $expected === '') {
            throw new \DomainException('Captcha expirado. Actualizá la imagen e intentá de nuevo.');
        }

        if (!hash_equals($expected, hash('sha256', $code))) {
            throw new \DomainException('Captcha incorrecto.');
        }
    }

    private function randomCode(int $length): string
    {
        $alphabet = self::ALPHABET;
        $max = strlen($alphabet) - 1;
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }

        return $out;
    }

    private function renderDataUrl(string $code): string
    {
        if (function_exists('imagecreatetruecolor') && function_exists('imagepng')) {
            $png = $this->renderPng($code);
            if ($png !== null) {
                return 'data:image/png;base64,' . base64_encode($png);
            }
        }

        return 'data:image/svg+xml;base64,' . base64_encode($this->renderSvg($code));
    }

    private function renderPng(string $code): ?string
    {
        $width = 140;
        $height = 48;
        $im = @imagecreatetruecolor($width, $height);
        if ($im === false) {
            return null;
        }

        $bg = imagecolorallocate($im, 245, 247, 250);
        $fg = imagecolorallocate($im, 30, 41, 59);
        $noise = imagecolorallocate($im, 148, 163, 184);
        imagefilledrectangle($im, 0, 0, $width, $height, $bg);

        for ($i = 0; $i < 40; $i++) {
            imagesetpixel($im, random_int(0, $width - 1), random_int(0, $height - 1), $noise);
        }
        for ($i = 0; $i < 4; $i++) {
            imageline(
                $im,
                random_int(0, $width / 2),
                random_int(0, $height - 1),
                random_int($width / 2, $width - 1),
                random_int(0, $height - 1),
                $noise
            );
        }

        $font = 5;
        $charW = imagefontwidth($font);
        $charH = imagefontheight($font);
        $totalW = $charW * strlen($code);
        $x = (int) (($width - $totalW) / 2);
        $y = (int) (($height - $charH) / 2);
        imagestring($im, $font, $x, $y, $code, $fg);

        ob_start();
        imagepng($im);
        $bin = ob_get_clean();
        imagedestroy($im);

        return is_string($bin) ? $bin : null;
    }

    private function renderSvg(string $code): string
    {
        $escaped = htmlspecialchars($code, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="140" height="48" viewBox="0 0 140 48" role="img" aria-label="captcha">
  <rect width="140" height="48" fill="#f5f7fa"/>
  <text x="70" y="30" text-anchor="middle" font-family="ui-monospace,Consolas,monospace" font-size="22" fill="#1e293b" letter-spacing="4">{$escaped}</text>
</svg>
SVG;
    }

    /**
     * @return array<string, mixed>
     */
    private function config(): array
    {
        $cfg = Yii::$app->params['demo_sandbox'] ?? [];

        return is_array($cfg) ? $cfg : [];
    }
}
