#!/usr/bin/env php
<?php

/**
 * Inventario heurístico de relaciones ActiveRecord Yii2:
 * métodos públicos getXxx() cuyo cuerpo usa hasOne/hasMany/viaTable/via(
 *
 * Uso (desde web/): php tools/inventory_ar_relations.php [--usage-count] [--json] [--legacy-getid] [--fail-on-legacy-getid]
 *
 * --legacy-getid: solo relaciones cuyo getter empieza por getId + mayúscula (patrón antiguo getIdXxx en vez de getXxx).
 * --fail-on-legacy-getid: junto con --legacy-getid, exit code 1 si el contador es > 0 (gate CI).
 */

declare(strict_types=1);

$base = dirname(__DIR__);
$roots = [
    $base . '/common/models',
    $base . '/frontend/controllers',
    $base . '/frontend/modules',
    $base . '/admin/controllers',
];

$excludeDirs = ['vendor', 'runtime', 'assets', 'node_modules', '.git'];

$usageCount = in_array('--usage-count', $argv, true);
$asJson = in_array('--json', $argv, true);
$legacyGetId = in_array('--legacy-getid', $argv, true);
$failOnLegacyGetId = in_array('--fail-on-legacy-getid', $argv, true);

function should_skip_dir(string $dir, array $excludeDirs): bool
{
    $leaf = basename($dir);
    return in_array($leaf, $excludeDirs, true);
}

/** @return iterable<string> */
function iter_php_files(array $roots, array $excludeDirs): iterable
{
    foreach ($roots as $root) {
        if (!is_dir($root)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );
        /** @var SplFileInfo $f */
        foreach ($it as $f) {
            if (!$f->isFile() || strtolower($f->getExtension()) !== 'php') {
                continue;
            }
            $path = $f->getPathname();
            foreach ($excludeDirs as $ex) {
                if (strpos($path, DIRECTORY_SEPARATOR . $ex . DIRECTORY_SEPARATOR) !== false) {
                    continue 2;
                }
            }
            yield $path;
        }
    }
}

function extract_namespace(string $content): ?string
{
    if (preg_match('/^\s*namespace\s+([^;\s]+)\s*;/m', $content, $m)) {
        return $m[1];
    }

    return null;
}

/** Extrae el cuerpo de función balanceando llaves desde la primera { */
function extract_function_body(string $content, int $openBracePos): ?string
{
    $len = strlen($content);
    $depth = 0;
    $started = false;
    for ($i = $openBracePos; $i < $len; $i++) {
        $c = $content[$i];
        if ($c === '{') {
            $depth++;
            $started = true;
        } elseif ($c === '}') {
            $depth--;
            if ($started && $depth === 0) {
                return substr($content, $openBracePos, $i - $openBracePos + 1);
            }
        }
    }

    return null;
}

function getter_to_magic_property(string $getterName): string
{
    if (strpos($getterName, 'get') !== 0) {
        return $getterName;
    }
    $rest = substr($getterName, 3);
    if ($rest === '') {
        return $rest;
    }

    return strtolower($rest[0]) . substr($rest, 1);
}

function looks_like_ar_relation(string $body): bool
{
    return strpos($body, 'hasOne(') !== false
        || strpos($body, 'hasMany(') !== false
        || strpos($body, 'viaTable(') !== false
        || strpos($body, 'via(') !== false;
}

function is_alias_return(string $body): bool
{
    $trim = preg_replace('/^\s+|\s+$/s', '', $body);
    // return $this->getSomething(); o return $this->getSomething(...)
    return (bool) preg_match('/^\{\s*return\s+\$this->get[A-Za-z0-9_]+\([^)]*\)\s*;\s*\}\s*$/s', $trim);
}

/** @return array<int, array{class:string,method:string,property:string,file:string,line:int,alias:bool}> */
function scan_file(string $path, ?string $ns): array
{
    $content = file_get_contents($path);
    if ($content === false) {
        return [];
    }
    $lines = explode("\n", $content);
    $out = [];

    if (preg_match_all(
        '/\b(?:public|protected)\s+function\s+(get[A-Za-z0-9_]+)\s*\([^)]*\)\s*\{/m',
        $content,
        $matches,
        PREG_OFFSET_CAPTURE
    )) {
        foreach ($matches[0] as $idx => $full) {
            $methodName = $matches[1][$idx][0];
            $openBraceOffset = $full[1] + strlen($full[0]) - 1;
            $body = extract_function_body($content, $openBraceOffset);
            if ($body === null || !looks_like_ar_relation($body)) {
                continue;
            }
            $lineNum = substr_count(substr($content, 0, $matches[0][$idx][1]), "\n") + 1;
            $shortClass = basename($path, '.php');
            $classFqn = ($ns !== null && $ns !== '') ? $ns . '\\' . $shortClass : $shortClass;
            $out[] = [
                'class' => $classFqn,
                'method' => $methodName,
                'property' => getter_to_magic_property($methodName),
                'file' => $path,
                'line' => $lineNum,
                'alias' => is_alias_return($body),
            ];
        }
    }

    return $out;
}

/** Conteo aproximado de ->propiedad en PHP del árbol web/ (excluye vendor) */
function count_magic_usage(string $base, string $property): int
{
    $needle = '->' . $property;
    $n = 0;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
    );
    /** @var SplFileInfo $f */
    foreach ($it as $f) {
        if (!$f->isFile() || strtolower($f->getExtension()) !== 'php') {
            continue;
        }
        $p = $f->getPathname();
        if (strpos($p, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) !== false
            || strpos($p, DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR) !== false) {
            continue;
        }
        $c = @file_get_contents($p);
        if ($c === false) {
            continue;
        }
        $n += substr_count($c, $needle);
    }

    return $n;
}

$rows = [];
foreach (iter_php_files($roots, $excludeDirs) as $path) {
    $content = file_get_contents($path);
    $ns = $content !== false ? extract_namespace($content) : null;
    foreach (scan_file($path, $ns) as $row) {
        if ($usageCount) {
            $row['usage_count'] = count_magic_usage($base, $row['property']);
        }
        $rows[] = $row;
    }
}

usort($rows, static function ($a, $b) {
    return [$a['class'], $a['method']] <=> [$b['class'], $b['method']];
});

if ($legacyGetId) {
    $rows = array_values(array_filter(
        $rows,
        static fn (array $r): bool => (bool) preg_match('/^getId[A-Z]/', $r['method'])
    ));
}

if ($asJson) {
    echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    if ($failOnLegacyGetId && $legacyGetId && count($rows) > 0) {
        fwrite(STDERR, "inventory_ar_relations: hay relaciones getIdXxx legacy (contador=" . count($rows) . ").\n");
        exit(1);
    }
    exit(0);
}

$title = $legacyGetId
    ? 'Relaciones con getter getIdXxx (candidatas a convención getXxx / $model->xxx)'
    : 'Total relaciones AR detectadas (heurística)';
echo $title . ': ' . count($rows) . "\n\n";
$prev = null;
foreach ($rows as $row) {
    $key = $row['class'] . '::' . $row['method'];
    if ($prev !== $row['class']) {
        echo "\n## " . $row['class'] . "\n";
        $prev = $row['class'];
    }
    $u = isset($row['usage_count']) ? ' usages≈' . $row['usage_count'] : '';
    $al = $row['alias'] ? ' [alias]' : '';
    echo sprintf(
        "  %s → \$model->%s  (%s:%d)%s%s\n",
        $row['method'],
        $row['property'],
        str_replace($base . DIRECTORY_SEPARATOR, '', $row['file']),
        $row['line'],
        $al,
        $u
    );
}

if ($failOnLegacyGetId && $legacyGetId && count($rows) > 0) {
    fwrite(STDERR, "inventory_ar_relations: hay relaciones getIdXxx legacy (contador=" . count($rows) . ").\n");
    exit(1);
}
