<?php

/**
 * Auditoría "clientes delgados" (plan web/docs/plans/clientes-contrato-api, fase 00).
 *
 * Cuenta patrones que el programa debe eliminar y compara contra un tope versionado
 * (tools/thin-clients-baseline.json). El tope solo puede bajar.
 *
 *   php tools/audit-thin-clients.php            Verifica; sale con 1 si algún contador sube.
 *   php tools/audit-thin-clients.php --report   Muestra contadores y archivos con más ocurrencias.
 *   php tools/audit-thin-clients.php --write    Baja el tope a los valores actuales (nunca lo sube).
 *   php tools/audit-thin-clients.php --write --init   Crea el tope inicial.
 */

$root = str_replace('\\', '/', dirname(__DIR__, 2));
$baselineFile = __DIR__ . '/thin-clients-baseline.json';

$skipDirs = ['vendor', 'node_modules', 'runtime', 'build', '.dart_tool', '.git', 'tests', 'test', 'assets', 'ios', 'android', 'macos', 'linux', 'windows'];

// Textos de infraestructura permitidos en cliente (sin red, permiso, error inesperado).
$infraTextFiles = [
    'mobile/packages/shared/lib/text/user_friendly_error.dart',
    'mobile/packages/shared/lib/text/shared_texts.dart',
    'mobile/personalsalud/lib/infra/app_texts.dart',
    'mobile/paciente/lib/utils/app_texts.dart',
];

$stripDart = static function (string $line): string {
    $line = preg_replace("/'(?:[^'\\\\]|\\\\.)*'/u", "''", $line) ?? $line;
    $line = preg_replace('/"(?:[^"\\\\]|\\\\.)*"/u', '""', $line) ?? $line;
    return preg_replace('#//.*$#', '', $line) ?? $line;
};

$dartRoots = ['mobile/personalsalud/lib', 'mobile/paciente/lib', 'mobile/packages/shared/lib'];

$counters = [
    'api_controller_literal_messages' => [
        'desc' => 'Mensajes literales en controllers de la API (deben salir del catálogo del backend)',
        'roots' => ['web/frontend/modules/api'],
        'ext' => ['php'],
        'regex' => '/[\'"]message[\'"]\s*=>\s*[\'"]/u',
    ],
    'domain_literal_messages' => [
        'desc' => 'Mensajes literales en servicios de dominio (excepciones o message con texto)',
        'roots' => ['web/common/components/Domain'],
        'ext' => ['php'],
        'regex' => '/((?<!Api)Exception\(\s*[\'"]|[\'"]message[\'"]\s*=>\s*[\'"])/u',
    ],
    'php_embedded_prompts' => [
        'desc' => 'Prompts de IA embebidos en PHP (deben vivir en YAML, como Guide y Preprocess)',
        'roots' => ['web/common/components', 'web/common/models', 'web/frontend/modules/api'],
        'ext' => ['php'],
        'regex' => '/(Sos (un|una|el) |Eres (un|una) |Actu[aá] como )/u',
    ],
    'mobile_literal_ui_text' => [
        'desc' => 'Textos de usuario literales en Dart (Text, SnackBar, _snack)',
        'roots' => $dartRoots,
        'ext' => ['dart'],
        'regex' => '/(\bText\(\s*[\'"]\p{Lu}|_snack\(\s*[\'"]|SnackBar\(\s*content:\s*(const\s+)?Text\(\s*[\'"])/u',
    ],
    'mobile_literal_ui_text_multiline' => [
        'desc' => 'Textos de usuario literales en Dart partidos en varias líneas (argumento solo en su línea, o message:/titulo:/title:)',
        'roots' => $dartRoots,
        'ext' => ['dart'],
        'regex' => '/(^\s*(const\s+)?[\'"]\p{Lu}[^\'"$]*\s[^\'"$]*[\'"],?\s*$|\b(message|mensaje|titulo|title|error)\s*:\s*[\'"]\p{Lu}[^\'"$]*\s)/u',
        // Mensajes técnicos (excepciones, logs, avisos de desarrollo) que el usuario no ve.
        'skip_if_prev' => '/(Exception\(|debugPrint\(|\bprint\(|\bthrow\b|Sin retrocompatibilidad|Firebase no listo)/u',
    ],
    'mobile_consulta_in_code' => [
        'desc' => '«consulta» en identificadores Dart (fuera de strings y comentarios)',
        'roots' => $dartRoots,
        'ext' => ['dart'],
        'regex' => '/consulta/iu',
        'strip' => $stripDart,
    ],
    'mobile_consulta_file_names' => [
        'desc' => 'Archivos Dart con «consulta» en el nombre',
        'roots' => $dartRoots,
        'ext' => ['dart'],
        'file_name_regex' => '/consulta/i',
    ],
    'mobile_episode_type_branches' => [
        'desc' => 'Ramas por tipo de episodio fijo en Dart',
        'roots' => $dartRoots,
        'ext' => ['dart'],
        'regex' => '/(==|!=)\s*[\'"](GUARDIA|INTERNACION|CIRUGIA|TURNO|ENCOUNTER)[\'"]|case\s+[\'"](GUARDIA|INTERNACION|CIRUGIA|TURNO|ENCOUNTER)[\'"]/u',
    ],
    'backend_consulta_file_names' => [
        'desc' => 'Archivos PHP con «consulta» en el nombre (incluye conceptos diferenciados como teleconsulta)',
        'roots' => ['web/common/components', 'web/common/models', 'web/frontend/modules/api', 'web/frontend/controllers'],
        'ext' => ['php'],
        'file_name_regex' => '/consulta/i',
    ],
    'backend_consulta_routes' => [
        'desc' => 'Rutas de la API con «consulta» en la configuración de URLs',
        'roots' => ['web/frontend/config'],
        'ext' => ['php'],
        'regex' => '#api/<version:[^>]+>/[^\']*consulta#i',
    ],
    'web_js_html_concat' => [
        'desc' => 'HTML concatenado en JS (viola Camino A)',
        'roots' => ['web/frontend/web/js'],
        'ext' => ['js'],
        'regex' => '/(innerHTML\s*\+?=\s*[\'"`]<|\bhtml\s*\+=)/u',
    ],
    'web_js_literal_ui_text' => [
        'desc' => 'Textos de UI literales en JS (alert, toast, textContent)',
        'roots' => ['web/frontend/web/js'],
        'ext' => ['js'],
        'regex' => '/(alert|toast|showError|showMessage|textContent\s*=)\s*\(?\s*[\'"`]\p{Lu}/u',
    ],
    'web_views_logic' => [
        'desc' => 'Lógica de request, rol o URL dentro de vistas PHP (deben ser delgadas)',
        'roots' => ['web/frontend/views'],
        'ext' => ['php'],
        'regex' => '/(Yii::\$app->(request|user)|hasRole|http_build_query|\/api\/v1)/u',
    ],
    'web_episode_type_branches' => [
        'desc' => 'Ramas por tipo de episodio fijo en JS',
        'roots' => ['web/frontend/web/js'],
        'ext' => ['js'],
        'regex' => '/(===?|!==?)\s*[\'"](GUARDIA|INTERNACION|CIRUGIA|TURNO|ENCOUNTER)[\'"]|case\s+[\'"](GUARDIA|INTERNACION|CIRUGIA|TURNO|ENCOUNTER)[\'"]/u',
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
        // Los directorios `assets` solo se excluyen donde no son parte del código auditado.
        $skip = $id === 'web_js_html_concat' || str_starts_with($rel, 'web/frontend/web/js')
            ? array_values(array_diff($skipDirs, ['assets']))
            : $skipDirs;
        foreach (listFiles($root, $rel, $def['ext'], $skip) as $path) {
            $short = substr($path, strlen($root) + 1);
            if (in_array($short, $infraTextFiles, true)) {
                continue;
            }
            if (isset($def['file_name_regex'])) {
                if (preg_match($def['file_name_regex'], basename($path))) {
                    $count++;
                    $perFile[$short] = 1;
                }
                continue;
            }
            $strip = $def['strip'] ?? null;
            $n = 0;
            $prev = '';
            foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
                $subject = $strip ? $strip($line) : $line;
                $technical = isset($def['skip_if_prev']) && preg_match($def['skip_if_prev'], $prev . ' ' . $line);
                $prev = $line;
                if (!$technical && preg_match($def['regex'], $subject)) {
                    $n++;
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
        printf("%-36s %6d  %s\n", $id, $results[$id], $def['desc']);
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
    echo "Tope actualizado en tools/thin-clients-baseline.json\n";
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
