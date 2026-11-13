<?php

/**
 * Gate de valores derivados (docs/decisions/valores-derivados-fuente-unica.md).
 *
 * Detecta literales de negocio que deben leerse de su fuente (catálogo de
 * precios, EncounterDefinition, metadata de roles) y compara contra un tope
 * versionado (tools/derived-values-baseline.json). El tope solo puede bajar.
 *
 *   php tools/audit-derived-values.php            Verifica; sale con 1 si algún contador sube.
 *   php tools/audit-derived-values.php --report   Muestra contadores y archivos con más ocurrencias.
 *   php tools/audit-derived-values.php --write    Baja el tope a los valores actuales (nunca lo sube).
 *   php tools/audit-derived-values.php --write --init   Crea el tope inicial.
 */

$root = str_replace('\\', '/', dirname(__DIR__, 2));
$baselineFile = __DIR__ . '/derived-values-baseline.json';

$skipDirs = ['vendor', 'node_modules', 'runtime', 'build', '.dart_tool', '.git', 'tests', 'test', 'assets', 'ios', 'android', 'macos', 'linux', 'windows'];

// La fuente declarativa no se audita: sus literales son el valor, no una copia.
$sourceFiles = [
    'web/common/components/Domain/Organization/Pes/Domain/Catalog/PricingPesByEncounterClassCatalog.php',
    'web/common/models/Clinical/EncounterDefinition.php',
];

$counters = [
    'literal_plan_volume' => [
        'desc' => 'Volumen mensual literal en el contrato del plan (sale del catálogo)',
        'roots' => ['web/common/components', 'web/common/models', 'web/admin', 'web/frontend', 'web/console'],
        'ext' => ['php'],
        'regex' => '/\battentions_per_month\b\s*[\'"]?\s*(?:=>|:)\s*[\'"]?\d+/u',
    ],
    'literal_max_pes' => [
        'desc' => 'Tope max_pes literal (se deriva de atenciones y capacidad)',
        'roots' => ['web/common/components', 'web/common/models', 'web/admin', 'web/frontend', 'web/console'],
        'ext' => ['php'],
        'regex' => '/\bmax_pes\b\s*[\'"]?\s*=>\s*\d+/u',
    ],
    'inline_addon_policy' => [
        'desc' => 'Política de addon escrita como booleano inline (sale de addonPlanValues)',
        'roots' => ['web/common/components', 'web/admin', 'web/frontend'],
        'ext' => ['php'],
        'regex' => '/\b(dictado_incluido|videollamada_permitida|whatsapp_permitido)\s*=\s*(?:true|false|[01])\s*;/u',
    ],
    'encounter_class_list' => [
        'desc' => 'Lista de clases de atención repetida (sale de EncounterDefinition)',
        'roots' => ['web/common/components', 'web/common/models', 'web/common/config', 'web/admin', 'web/frontend', 'mobile', 'institucional'],
        'ext' => ['php', 'dart', 'js'],
        'regex' => '/[\'"]AMB[\'"]\s*,\s*[\'"]EMER[\'"]|[\'"]EMER[\'"]\s*,\s*[\'"]IMP[\'"]/u',
    ],
    'dart_plan_volume' => [
        'desc' => 'Volumen mensual literal en Dart (debe venir de defaults del backend)',
        'roots' => ['mobile'],
        'ext' => ['dart'],
        'regex' => '/\battentions_per_month\b\s*[\'"]?\s*:\s*\d+/u',
    ],
    'inline_role_list' => [
        'desc' => 'Lista de roles inline (sale de Permission/metadata/roles.yaml)',
        'roots' => ['web/common/components', 'web/frontend', 'web/admin'],
        'ext' => ['php'],
        'regex' => '/in_array\([^)]*\[[^\]]*[\'"](?:medico|enfermeria|Administrativo|AdminEfector|facturista|nivelCentral|AdminMinisterio)[\'"]/u',
    ],
    'capacity_literal_in_metadata' => [
        'desc' => 'Capacidad de referencia (500/700/400) copiada fuera del catálogo',
        'roots' => ['web/common/components/Platform/Core/Product'],
        'ext' => ['php'],
        'regex' => '/\b(?:500|700|400)\b/u',
    ],
    'catalog_numeric_fallback' => [
        'desc' => 'Fallback numérico en el loader del catálogo (deuda a bajar)',
        'roots' => ['web/common/components/Platform/Core/Product'],
        'ext' => ['php'],
        'regex' => '/\?\?\s*-?\d+(?:\.\d+)?\s*[,;)\]]/u',
    ],
    'config_catch_default' => [
        'desc' => 'catch que devuelve un default en configuración/permisos (debe fallar o loguear)',
        'roots' => [
            'web/common/components/Platform/Core/Product',
            'web/common/components/Platform/Core/Permission',
        ],
        'ext' => ['php'],
        'multiline' => true,
        'regex' => '/catch\s*\([^)]*\)\s*\{[^}]{0,400}?return\s+(?:null|\[\]|0|0\.0|false|\'\'|"")\s*;/u',
    ],
];

function listFiles(string $root, string $rel, array $ext, array $skipDirs): array
{
    $base = "$root/$rel";
    if (!is_dir($base)) {
        return [];
    }
    $out = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
            static function (SplFileInfo $f) use ($skipDirs): bool {
                return !($f->isDir() && in_array($f->getFilename(), $skipDirs, true));
            }
        )
    );
    foreach ($it as $f) {
        /** @var SplFileInfo $f */
        if ($f->isFile() && in_array(strtolower($f->getExtension()), $ext, true)) {
            $path = str_replace('\\', '/', $f->getPathname());
            if (preg_match('/\.(g|freezed)\.dart$/', $path) || str_ends_with($path, 'firebase_options.dart')) {
                continue;
            }
            $out[] = $path;
        }
    }
    sort($out);
    return $out;
}

$results = [];
$detail = [];
foreach ($counters as $id => $def) {
    $count = 0;
    $perFile = [];
    foreach ($def['roots'] as $rel) {
        foreach (listFiles($root, $rel, $def['ext'], $skipDirs) as $path) {
            $short = substr($path, strlen($root) + 1);
            if (in_array($short, $sourceFiles, true)) {
                continue;
            }
            $n = 0;
            if (!empty($def['multiline'])) {
                $n = (int) preg_match_all($def['regex'], (string) file_get_contents($path));
            } else {
                foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
                    if (preg_match($def['regex'], $line)) {
                        $n++;
                    }
                }
            }
            if ($n > 0) {
                $count += $n;
                $perFile[$short] = $n;
            }
        }
    }
    arsort($perFile);
    $results[$id] = $count;
    $detail[$id] = $perFile;
}

$args = array_slice($argv, 1);
$report = in_array('--report', $args, true);
$write = in_array('--write', $args, true);
$init = in_array('--init', $args, true);

$baseline = is_file($baselineFile)
    ? (json_decode((string) file_get_contents($baselineFile), true)['counters'] ?? [])
    : [];

if ($report) {
    foreach ($counters as $id => $def) {
        printf("%-32s %6d  %s\n", $id, $results[$id], $def['desc']);
        foreach (array_slice($detail[$id], 0, 5, true) as $file => $n) {
            printf("    %5d  %s\n", $n, $file);
        }
    }
    exit(0);
}

if ($write) {
    if (!$baseline && !$init) {
        fwrite(STDERR, "No hay tope previo. Usá --write --init para crearlo.\n");
        exit(2);
    }
    $new = [];
    $raised = [];
    foreach ($results as $id => $value) {
        $prev = $baseline[$id] ?? null;
        if ($prev !== null && $value > $prev) {
            $raised[] = "$id ($prev -> $value)";
            $new[$id] = $prev;
        } else {
            $new[$id] = $value;
        }
    }
    file_put_contents(
        $baselineFile,
        json_encode(['counters' => $new], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
    );
    echo "Tope actualizado en tools/derived-values-baseline.json\n";
    if ($raised) {
        fwrite(STDERR, "No se subió el tope de: " . implode(', ', $raised) . "\n");
        exit(1);
    }
    exit(0);
}

$failed = false;
foreach ($results as $id => $value) {
    $limit = $baseline[$id] ?? null;
    if ($limit === null) {
        fwrite(STDERR, "SIN TOPE  $id = $value (ejecutá --write --init)\n");
        $failed = true;
        continue;
    }
    if ($value > $limit) {
        fwrite(STDERR, "SUBIÓ     $id: $limit -> $value\n");
        foreach (array_slice($detail[$id], 0, 5, true) as $file => $n) {
            fwrite(STDERR, "           $n  $file\n");
        }
        $failed = true;
    } elseif ($value < $limit) {
        echo "BAJÓ      $id: $limit -> $value (corré --write para fijar el nuevo tope)\n";
    } else {
        echo "OK        $id = $value\n";
    }
}
exit($failed ? 1 : 0);
