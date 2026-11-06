<?php

namespace common\tests\unit\platform\core;

use Codeception\Test\Unit;
use Yii;
use yii\helpers\FileHelper;

/**
 * Invariante de arquitectura: el I/O contra APIs externas no se escribe a mano.
 *
 * - Un cliente de API externa es un ACL: `Domain/<BC>/[<Modulo>/]Infrastructure/External/<Sistema>/`.
 * - El transporte reutilizable (timeouts, retry, idempotencia, JSON, mapeo de error y log)
 *   vive en `Shared/Infrastructure/Http/`.
 * - `Application` no hace red.
 *
 * No hay allowlist: la deuda llegó a cero con el plan `deduplicacion-http`. Un archivo nuevo
 * que use transporte directo rompe este test y el hook `.github/hooks/http-guard.json`.
 *
 * @see .cursor/rules/dry-solid.mdc
 * @see .cursor/rules/common-components-organizacion.mdc
 */
final class ArchitectureHttpUsageTest extends Unit
{
    /** Fragmentos de ruta donde el uso directo del transporte está permitido. */
    private const ALLOWED_SEGMENTS = [
        '/Infrastructure/External/',
        '/Shared/Infrastructure/Http/',
    ];

    private const AD_HOC = '/\bnew\s+(?:\\\\?yii\\\\httpclient\\\\)?Client\s*\('
        . '|\bcurl_init\s*\('
        . '|\bcurl_exec\s*\('
        . '|\bfile_get_contents\s*\(\s*[\'"]https?:/i';

    private const AD_HOC_ANY = '/\bnew\s+(?:\\\\?yii\\\\httpclient\\\\)?Client\s*\('
        . '|\\\\?yii\\\\httpclient\\\\'
        . '|\bcurl_init\s*\('
        . '|\bcurl_exec\s*\('
        . '|\bfile_get_contents\s*\(\s*[\'"]https?:/i';

    public function testHttpTransportOnlyInExternalAclOrSharedBase(): void    {
        $offenders = [];
        foreach ($this->scan(['components', 'models'], self::AD_HOC) as $file) {
            if ($this->isAllowed($file)) {
                continue;
            }
            $offenders[] = $file;
        }

        $this->assertSame([], $offenders, $this->remediation(
            'Cliente HTTP ad-hoc fuera de un ACL o del base compartido',
            $offenders
        ));
    }

    public function testApplicationLayerHasNoNetworkIo(): void
    {
        $offenders = [];
        foreach ($this->scan(['components'], self::AD_HOC_ANY) as $file) {
            if (strpos($file, '/Application/') === false || $this->isAllowed($file)) {
                continue;
            }
            $offenders[] = $file;
        }

        $this->assertSame([], $offenders, $this->remediation(
            'I/O de red en Application',
            $offenders
        ));
    }

    /**
     * @param string[] $roots relativos a `@common`
     * @return string[] rutas relativas a `@common`, con `/`
     */
    private function scan(array $roots, string $pattern): array
    {
        $base = $this->commonRoot();
        $found = [];

        foreach ($roots as $root) {
            $dir = $base . '/' . $root;
            if (!is_dir($dir)) {
                continue;
            }

            $files = FileHelper::findFiles($dir, ['only' => ['*.php']]);
            sort($files);

            foreach ($files as $absolute) {
                $relative = str_replace('\\', '/', substr($absolute, strlen($base) + 1));
                if (strpos($relative, '/tests/') !== false || strpos($relative, '/runtime/') !== false) {
                    continue;
                }

                if (preg_match($pattern, (string) file_get_contents($absolute))) {
                    $found[] = $relative;
                }
            }
        }

        $found = array_values(array_unique($found));
        sort($found);

        return $found;
    }

    private function isAllowed(string $relativePath): bool
    {
        foreach (self::ALLOWED_SEGMENTS as $segment) {
            if (strpos($relativePath, $segment) !== false) {
                return true;
            }
        }

        return false;
    }

    private function commonRoot(): string
    {
        return rtrim(str_replace('\\', '/', (string) Yii::getAlias('@common')), '/');
    }

    /**
     * @param string[] $offenders
     */
    private function remediation(string $title, array $offenders): string
    {
        if ($offenders === []) {
            return $title;
        }

        return $title . ":\n  " . implode("\n  ", $offenders) . "\n\n"
            . "Qué hacer: si el cliente es de un proveedor concreto, moverlo a\n"
            . "`Domain/<BC>/[<Modulo>/]Infrastructure/External/<Sistema>/<Sistema>ApiClient.php`.\n"
            . "Si el problema es transporte (timeouts, retry, idempotencia, mapeo de error, log),\n"
            . "usar/crear el base en `Shared/Infrastructure/Http/`.\n"
            . 'Ver .cursor/rules/dry-solid.mdc.' . "\n";
    }
}
