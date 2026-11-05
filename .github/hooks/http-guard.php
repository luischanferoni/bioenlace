<?php

/**
 * Hook `preToolUse`: bloquea ediciones que introducen transporte HTTP ad-hoc.
 *
 * Contrato: JSON del evento por stdin, un objeto JSON por stdout.
 * `permissionDecision: deny` corta la herramienta; `{}` no decide nada (flujo normal).
 *
 * Falla abierto a propósito: si el payload no se puede interpretar, no bloquea. Es una
 * red de seguridad además de las instrucciones, no la única defensa.
 *
 * @see .cursor/rules/common-components-organizacion.mdc
 * @see web/common/tests/unit/platform/core/ArchitectureHttpUsageTest.php
 */

const AD_HOC_PATTERN = '/\bnew\s+(?:\\\\?yii\\\\httpclient\\\\)?Client\s*\('
    . '|\bcurl_init\s*\('
    . '|\bcurl_exec\s*\('
    . '|\bfile_get_contents\s*\(\s*[\'"]https?:/i';

const ALLOWED_SEGMENTS = [
    'web/common/components/Shared/Infrastructure/Http/',
    '/Infrastructure/External/',
];

/** Los tests arman el `Client` con transporte falso: mismo criterio que ArchitectureHttpUsageTest. */
const ALLOWED_PATH_FRAGMENT = 'web/common/tests/';

$raw = (string) stream_get_contents(STDIN);
$event = json_decode($raw, true);
if (!is_array($event)) {
    echo '{}';
    exit(0);
}

$toolName = strtolower((string) ($event['toolName'] ?? ''));
if (!preg_match('/edit|create|write|patch|replace/', $toolName)) {
    echo '{}';
    exit(0);
}

// Deuda aceptada: misma fuente que ArchitectureHttpUsageTest.
$legacy = [];
$baselineFile = dirname(__DIR__, 2) . '/web/tools/architecture-http-legacy.json';
if (is_file($baselineFile)) {
    $decoded = json_decode((string) file_get_contents($baselineFile), true);
    if (is_array($decoded)) {
        $legacy = array_merge(
            (array) ($decoded['adHoc'] ?? []),
            (array) ($decoded['applicationHttp'] ?? [])
        );
    }
}

$values = [];
$newContent = [];
flatten($event['toolArgs'] ?? [], $values, $newContent, 0, '');

$targetPath = null;
foreach ($values as $value) {
    if (preg_match('#(?:^|/)web/common/[^\s"\']+\.php$#', $value)
        && substr($value, -4) === '.php'
    ) {
        $targetPath = $value;
        break;
    }
}

$violates = false;
foreach ($newContent as $blob) {
    if (preg_match(AD_HOC_PATTERN, $blob)) {
        $violates = true;
        break;
    }
}

// Sin ruta explícita (p. ej. apply_patch): se busca la ruta dentro del propio diff.
if ($violates && $targetPath === null) {
    foreach ($values as $value) {
        if (preg_match('#(web/common/(?:components|models)/[^\s"\']+\.php)#', $value, $match)) {
            $targetPath = $match[1];
            break;
        }
    }
}

if (!$violates || $targetPath === null || isAllowed($targetPath, $legacy)) {
    echo '{}';
    exit(0);
}

echo json_encode([
    'permissionDecision' => 'deny',
    'permissionDecisionReason' => 'Transporte HTTP ad-hoc en ' . $targetPath . '. '
        . 'Un cliente de API externa es un ACL: '
        . 'Domain/<BC>/[<Modulo>/]Infrastructure/External/<Sistema>/<Sistema>ApiClient.php. '
        . 'El transporte reutilizable vive en Shared/Infrastructure/Http/. '
        . 'Ver .cursor/rules/common-components-organizacion.mdc (prohibiciones por capa) '
        . 'y .cursor/rules/dry-solid.mdc.',
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
exit(0);

/**
 * Aplana `toolArgs` en valores normalizados, separando el contenido nuevo del viejo.
 *
 * @param mixed $node
 * @param string[] $values todas las rutas y textos normalizados
 * @param string[] $newContent solo lo que se agrega (no `old*`), sin las líneas removidas de un diff
 */
function flatten($node, array &$values, array &$newContent, int $depth, string $key): void
{
    if ($depth > 8) {
        return;
    }

    if (is_string($node)) {
        $normalized = str_replace('\\', '/', $node);
        $values[] = $normalized;

        if (preg_match('/old/i', $key)) {
            return;
        }
        if (preg_match('/patch|diff/i', $key)) {
            $added = [];
            foreach (preg_split('/\r?\n/', $normalized) as $line) {
                if (isset($line[0]) && $line[0] === '+') {
                    $added[] = substr($line, 1);
                }
            }
            $normalized = implode("\n", $added);
        }
        $newContent[] = $normalized;

        return;
    }

    if (!is_array($node)) {
        return;
    }

    foreach ($node as $childKey => $child) {
        flatten($child, $values, $newContent, $depth + 1, is_string($childKey) ? $childKey : $key);
    }
}

/**
 * @param string[] $legacy rutas relativas a `web/common`
 */
function isAllowed(string $path, array $legacy = []): bool
{
    if (strpos($path, ALLOWED_PATH_FRAGMENT) !== false) {
        return true;
    }

    foreach (ALLOWED_SEGMENTS as $segment) {
        if (strpos($path, $segment) !== false) {
            return true;
        }
    }

    foreach ($legacy as $entry) {
        if (strpos($path, 'web/common/' . $entry) !== false) {
            return true;
        }
    }

    return false;
}
