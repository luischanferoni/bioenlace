<?php
/**
 * Mueve common/components/Services/* a dominios y actualiza namespaces.
 * Uso: php tools/migrate_phase3_services.php
 */
$root = dirname(__DIR__);
$servicesDir = $root . '/common/components/Services';

$dirMap = [
    'ProfesionalEfectorServicio' => 'Organization/Service/ProfesionalEfectorServicio',
    'SesionOperativa' => 'Organization/Service/SesionOperativa',
    'Efectores' => 'Organization/Service/Efectores',
    'Servicios' => 'Organization/Service/Servicios',
    'Quirofano' => 'Scheduling/Service/Quirofano',
    'Turnos' => 'Scheduling/Service',
    'Persona' => 'Person/Service',
    'Notificaciones' => 'Core/Service/Notificaciones',
    'Push' => 'Core/Service/Push',
    'Actions' => 'Core/Service/Actions',
    'Assistant' => 'Assistant/Service',
    'Consulta' => 'Clinical/Legacy',
];

$namespaceMap = [];
foreach ($dirMap as $from => $to) {
    $namespaceMap['common\\components\\Services\\' . $from . '\\'] =
        'common\\components\\' . str_replace('/', '\\', $to) . '\\';
}
$namespaceMap['common\\components\\Services\\'] = 'common\\components\\Scheduling\\Service\\'; // fallback unused

uksort($namespaceMap, static fn ($a, $b) => strlen($b) <=> strlen($a));

function applyMap(string $content, array $map): string
{
    foreach ($map as $from => $to) {
        $content = str_replace($from, $to, $content);
    }
    $content = str_replace(
        'common\\components\\Services\\RegistroService',
        'common\\components\\Person\\Service\\RegistroService',
        $content
    );

    return $content;
}

function destPathFor(string $servicesDir, string $src, string $root, array $dirMap): string
{
    $rel = str_replace('\\', '/', substr($src, strlen($servicesDir) + 1));
    if ($rel === 'RegistroService.php') {
        return $root . '/common/components/Person/Service/RegistroService.php';
    }
    $parts = explode('/', $rel);
    $folder = $parts[0];
    $file = $parts[count($parts) - 1];
    $target = $dirMap[$folder] ?? ('Scheduling/Service/' . $folder);
    if (count($parts) > 2) {
        $sub = implode('/', array_slice($parts, 1, -1));
        return $root . '/common/components/' . $target . '/' . $sub . '/' . $file;
    }

    return $root . '/common/components/' . $target . '/' . $file;
}

if (!is_dir($servicesDir)) {
    echo "Services/ ya no existe; omitiendo move.\n";
} else {
    $moved = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($servicesDir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
            continue;
        }
        $src = $file->getPathname();
        $content = file_get_contents($src);
        $newContent = applyMap($content, $namespaceMap);
        $dest = destPathFor($servicesDir, $src, $root, $dirMap);
        $destDir = dirname($dest);
        if (!is_dir($destDir)) {
            mkdir($destDir, 0777, true);
        }
        file_put_contents($dest, $newContent);
        $moved[] = $src;
    }
    foreach ($moved as $src) {
        unlink($src);
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($servicesDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $f) {
        if ($f->isDir()) {
            @rmdir($f->getPathname());
        }
    }
    @rmdir($servicesDir);
    echo 'Moved ' . count($moved) . " PHP files.\n";
}

$scanDirs = [$root . '/common', $root . '/frontend', $root . '/backend', $root . '/console', $root . '/docs'];
$updatedFiles = 0;
foreach ($scanDirs as $dir) {
    if (!is_dir($dir)) {
        continue;
    }
    $it2 = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($it2 as $f) {
        if (!$f->isFile() || $f->getExtension() !== 'php') {
            continue;
        }
        if (strpos($f->getPathname(), DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) !== false) {
            continue;
        }
        $c = file_get_contents($f->getPathname());
        $nc = applyMap($c, $namespaceMap);
        if ($nc !== $c) {
            file_put_contents($f->getPathname(), $nc);
            $updatedFiles++;
        }
    }
}
echo "Updated {$updatedFiles} PHP files.\n";
