<?php

/**
 * Sincroniza las reglas de agente de `.cursor/rules/*.mdc` al formato nativo de
 * VS Code Copilot `.github/instructions/*.instructions.md`.
 *
 * Fuente única de verdad: `.cursor/rules`. Los `.instructions.md` son generados
 * y no se editan a mano.
 *
 * Mapeo de frontmatter:
 *   description                → description (descubrimiento on-demand)
 *   globs                      → applyTo     (se adjunta al editar esos archivos)
 *   alwaysApply: true          → applyTo: '**' (solo si no declara globs)
 *   sin globs ni alwaysApply   → sin applyTo (solo on-demand por description)
 *
 * Uso:
 *   php tools/sync-agent-rules.php            Regenera los .instructions.md
 *   php tools/sync-agent-rules.php --check    Sale con 1 si están desincronizados
 */

const SYNC_MARKER = '<!-- GENERADO por tools/sync-agent-rules.php — editar .cursor/rules/*.mdc -->';

$root = str_replace('\\', '/', dirname(__DIR__, 1));
$srcDir = $root . '/.cursor/rules';
$outDir = $root . '/.github/instructions';
$checkOnly = in_array('--check', $argv ?? [], true);

if (!is_dir($srcDir)) {
    fwrite(STDERR, "No existe {$srcDir}\n");
    exit(1);
}
if (!is_dir($outDir) && !mkdir($outDir, 0775, true) && !is_dir($outDir)) {
    fwrite(STDERR, "No se pudo crear {$outDir}\n");
    exit(1);
}

$ruleFiles = glob($srcDir . '/*.mdc') ?: [];
sort($ruleFiles);

$written = 0;
$outOfDate = [];
$expected = [];

foreach ($ruleFiles as $ruleFile) {
    $raw = (string) file_get_contents($ruleFile);
    $rule = parseRule($raw);
    $baseName = basename($ruleFile, '.mdc');
    $targetFile = $outDir . '/' . $baseName . '.instructions.md';
    $content = renderInstruction($baseName, $rule);
    $expected[$baseName] = true;

    $current = is_file($targetFile) ? (string) file_get_contents($targetFile) : null;
    if ($current === $content) {
        continue;
    }

    if ($checkOnly) {
        $outOfDate[] = $targetFile;
        continue;
    }

    file_put_contents($targetFile, $content);
    echo "actualizado  .github/instructions/{$baseName}.instructions.md\n";
    $written++;
}

$removed = 0;
foreach (glob($outDir . '/*.instructions.md') ?: [] as $existing) {
    $baseName = basename($existing, '.instructions.md');
    if (isset($expected[$baseName])) {
        continue;
    }
    $content = (string) file_get_contents($existing);
    if (strpos($content, SYNC_MARKER) === false) {
        continue; // archivo manual: no se toca
    }
    if ($checkOnly) {
        $outOfDate[] = $existing . ' (obsoleto)';
        continue;
    }
    unlink($existing);
    echo "eliminado    .github/instructions/{$baseName}.instructions.md\n";
    $removed++;
}

if ($checkOnly) {
    if ($outOfDate !== []) {
        fwrite(STDERR, "Reglas desincronizadas:\n  " . implode("\n  ", $outOfDate) . "\n");
        fwrite(STDERR, "Ejecutá: php tools/sync-agent-rules.php\n");
        exit(1);
    }
    echo "Reglas sincronizadas.\n";
    exit(0);
}

echo $written === 0 && $removed === 0
    ? "Sin cambios.\n"
    : "Listo: {$written} actualizados, {$removed} eliminados.\n";

/**
 * @return array{description: string, globs: string[], alwaysApply: bool, body: string, eol: string}
 */
function parseRule(string $raw): array
{
    $eol = strpos($raw, "\r\n") !== false ? "\r\n" : "\n";
    $defaults = ['description' => '', 'globs' => [], 'alwaysApply' => false, 'body' => $raw, 'eol' => $eol];

    if (!preg_match('/^---\r?\n(.*?)\r?\n---\r?\n?(.*)$/s', $raw, $m)) {
        return $defaults;
    }

    $front = [];
    $currentList = null;
    foreach (preg_split('/\r?\n/', $m[1]) as $line) {
        if ($currentList !== null && preg_match('/^\s*-\s*(.+?)\s*$/', $line, $lm)) {
            $front[$currentList][] = trim($lm[1]);
            continue;
        }
        if (preg_match('/^([A-Za-z0-9_]+):\s*(.*)$/', $line, $km)) {
            $key = $km[1];
            $value = trim($km[2]);
            if ($value === '') {
                $currentList = $key;
                $front[$key] = [];
            } else {
                $currentList = null;
                $front[$key] = $value;
            }
        }
    }

    $globs = $front['globs'] ?? [];
    if (is_string($globs)) {
        $globs = splitGlobs($globs);
    }

    return [
        'description' => trim((string) ($front['description'] ?? '')),
        'globs' => array_values(array_filter(array_map('trim', $globs), static function ($g) {
            return $g !== '';
        })),
        'alwaysApply' => strtolower(trim((string) ($front['alwaysApply'] ?? ''))) === 'true',
        'body' => $m[2],
        'eol' => $eol,
    ];
}

/**
 * Divide `globs: a,b` respetando llaves: un glob con `{php,css}` no se parte.
 *
 * @return string[]
 */
function splitGlobs(string $value): array
{
    $parts = [];
    $buffer = '';
    $depth = 0;
    $length = strlen($value);
    for ($i = 0; $i < $length; $i++) {
        $char = $value[$i];
        if ($char === '{') {
            $depth++;
        } elseif ($char === '}') {
            $depth--;
        } elseif ($char === ',' && $depth === 0) {
            $parts[] = trim($buffer);
            $buffer = '';
            continue;
        }
        $buffer .= $char;
    }
    $parts[] = trim($buffer);

    return array_values(array_filter($parts, static function ($g) {
        return $g !== '';
    }));
}

/**
 * @param array{description: string, globs: string[], alwaysApply: bool, body: string, eol: string} $rule
 */
function renderInstruction(string $baseName, array $rule): string
{
    $applyTo = $rule['globs'];
    if ($applyTo === [] && $rule['alwaysApply']) {
        $applyTo = ['**'];
    }

    $description = $rule['description'] !== '' ? $rule['description'] : 'Regla de proyecto: ' . $baseName;

    $front = ['---', 'description: ' . quoteYaml($description)];
    if ($applyTo !== []) {
        $quoted = array_map(static function ($glob) {
            return "'" . str_replace("'", "''", $glob) . "'";
        }, $applyTo);
        $front[] = 'applyTo: [' . implode(', ', $quoted) . ']';
    }
    $front[] = '---';

    $eol = $rule['eol'];
    $body = ltrim($rule['body'], "\r\n");

    return implode($eol, $front) . $eol . $eol . SYNC_MARKER . $eol . $eol . $body;
}

function quoteYaml(string $value): string
{
    return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
}
