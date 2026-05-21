<?php
/**
 * Corrige namespaces según ruta del archivo bajo common/components/.
 */
$root = dirname(__DIR__);
$base = $root . '/common/components';

$domains = ['Scheduling', 'Person', 'Organization', 'Core', 'Assistant', 'Clinical', 'Ui'];

foreach ($domains as $domain) {
    $dir = $base . '/' . $domain;
    if (!is_dir($dir)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $path = $file->getPathname();
        $rel = str_replace('\\', '/', substr($path, strlen($base) + 1));
        $relDir = dirname($rel);
        $expectedNs = 'common\\components\\' . str_replace('/', '\\', $relDir);
        $content = file_get_contents($path);
        $newContent = preg_replace(
            '/namespace\s+[^;]+;/',
            'namespace ' . $expectedNs . ';',
            $content,
            1
        );
        if ($newContent !== $content) {
            file_put_contents($path, $newContent);
        }
    }
}

// Segunda pasada: reemplazar use/imports incorrectos de la migración rota
$broken = [
    'common\\components\\Scheduling\\Service\\Turnos\\' => 'common\\components\\Scheduling\\Service\\',
    'common\\components\\Scheduling\\Service\\Persona\\' => 'common\\components\\Person\\Service\\',
    'common\\components\\Scheduling\\Service\\Consulta\\' => 'common\\components\\Clinical\\Legacy\\',
    'common\\components\\Scheduling\\Service\\SesionOperativa\\' => 'common\\components\\Organization\\Service\\SesionOperativa\\',
    'common\\components\\Scheduling\\Service\\ProfesionalEfectorServicio\\' => 'common\\components\\Organization\\Service\\ProfesionalEfectorServicio\\',
    'common\\components\\Scheduling\\Service\\Push\\' => 'common\\components\\Core\\Service\\Push\\',
    'common\\components\\Scheduling\\Service\\Notificaciones\\' => 'common\\components\\Core\\Service\\Notificaciones\\',
    'common\\components\\Scheduling\\Service\\Efectores\\' => 'common\\components\\Organization\\Service\\Efectores\\',
    'common\\components\\Scheduling\\Service\\Servicios\\' => 'common\\components\\Organization\\Service\\Servicios\\',
    'common\\components\\Scheduling\\Service\\Actions\\' => 'common\\components\\Core\\Service\\Actions\\',
    'common\\components\\Scheduling\\Service\\Assistant\\' => 'common\\components\\Assistant\\Service\\',
];
uksort($broken, static fn ($a, $b) => strlen($b) <=> strlen($a));

$scan = [$root . '/common', $root . '/frontend', $root . '/backend', $root . '/console'];
$n = 0;
foreach ($scan as $dir) {
    if (!is_dir($dir)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile() || $f->getExtension() !== 'php') {
            continue;
        }
        if (strpos($f->getPathname(), '/vendor/') !== false) {
            continue;
        }
        $c = file_get_contents($f->getPathname());
        $nc = $c;
        foreach ($broken as $from => $to) {
            $nc = str_replace($from, $to, $nc);
        }
        if ($nc !== $c) {
            file_put_contents($f->getPathname(), $nc);
            $n++;
        }
    }
}

// Eliminar carpeta Services duplicada
$servicesDir = $base . '/Services';
if (is_dir($servicesDir)) {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($servicesDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $f) {
        if ($f->isDir()) {
            rmdir($f->getPathname());
        } else {
            unlink($f->getPathname());
        }
    }
    rmdir($servicesDir);
    echo "Removed legacy Services/ folder.\n";
}

echo "Fixed imports in {$n} files.\n";
