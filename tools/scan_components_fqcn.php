<?php
/**
 * Scan PHP files under /web for class/interface/trait declarations inside components folders
 * and report duplicate FQCNs.
 *
 * Usage: php tools/scan_components_fqcn.php
 */

$root = __DIR__ . '/../web';
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

$items = [];

foreach ($it as $f) {
    if (!$f->isFile()) {
        continue;
    }
    if (substr($f->getFilename(), -4) !== '.php') {
        continue;
    }

    $path = str_replace('\\', '/', $f->getPathname());
    if (strpos($path, '/vendor/') !== false) {
        continue;
    }
    if (strpos($path, '/components/') === false && !preg_match('~/(modules/[^/]+/components)/~', $path)) {
        continue;
    }

    $code = @file_get_contents($f->getPathname());
    if ($code === false) {
        continue;
    }

    $ns = '';
    if (preg_match('/namespace\s+([^;\s]+)\s*;/', $code, $m)) {
        $ns = $m[1];
    }

    if (preg_match_all('/\b(class|interface|trait)\s+([A-Za-z_][A-Za-z0-9_]*)/', $code, $mm, PREG_SET_ORDER)) {
        foreach ($mm as $d) {
            $type = $d[1];
            $name = $d[2];
            $fqcn = $ns ? ($ns . '\\' . $name) : $name;
            $items[$fqcn][] = [$type, $f->getPathname()];
        }
    }
}

$dups = [];
foreach ($items as $fqcn => $arr) {
    if (count($arr) > 1) {
        $dups[$fqcn] = $arr;
    }
}

uasort($dups, static function ($a, $b) {
    return count($b) <=> count($a);
});

foreach ($dups as $fqcn => $arr) {
    echo PHP_EOL . '=== ' . $fqcn . ' (' . count($arr) . ') ===' . PHP_EOL;
    foreach ($arr as $row) {
        echo $row[0] . "\t" . $row[1] . PHP_EOL;
    }
}

echo PHP_EOL . 'Total FQCN: ' . count($items) . PHP_EOL;
echo 'Duplicate FQCN: ' . count($dups) . PHP_EOL;

