<?php
/**
 * Regenera departamentos-localidades-argentina.json.gz desde la API Georef.
 *
 * Uso (desde esta carpeta):
 *   php _build_geo_seed.php
 *
 * Fuente: https://apis.datos.gob.ar/georef/api/
 */
declare(strict_types=1);

$base = 'https://apis.datos.gob.ar/georef/api';
$outJson = __DIR__ . '/departamentos-localidades-argentina.json';
$outGz = $outJson . '.gz';

function georef_get(string $url): array
{
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 120,
            'header' => "Accept: application/json\r\n",
        ],
    ]);
    $raw = file_get_contents($url, false, $ctx);
    if ($raw === false) {
        throw new RuntimeException('HTTP fail: ' . $url);
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new RuntimeException('JSON inválido: ' . $url);
    }

    return $data;
}

$departamentos = [];
$inicio = 0;
$max = 5000;
do {
    $page = georef_get($base . '/departamentos?campos=id,nombre,provincia.id&max=' . $max . '&inicio=' . $inicio);
    $batch = $page['departamentos'] ?? [];
    foreach ($batch as $d) {
        $id = (string) ($d['id'] ?? '');
        $departamentos[] = [
            'cod_indec' => $id,
            'nombre' => (string) ($d['nombre'] ?? ''),
            'cod_provincia' => str_pad((string) ($d['provincia']['id'] ?? substr($id, 0, 2)), 2, '0', STR_PAD_LEFT),
        ];
    }
    $inicio += count($batch);
    $total = (int) ($page['total'] ?? $inicio);
} while ($inicio < $total && $batch !== []);

// Endpoint localidades: total conocido ~4037; no pagina de forma fiable → un solo request amplio.
$locPage = georef_get($base . '/localidades?campos=id,nombre,departamento.id,codigo_postal&max=5000');
$localidades = [];
foreach ($locPage['localidades'] ?? [] as $l) {
    $dept = (string) ($l['departamento']['id'] ?? '');
    $bahra = (string) ($l['id'] ?? '');
    $cp = preg_replace('/\D+/', '', (string) ($l['codigo_postal'] ?? '')) ?: substr(preg_replace('/\D+/', '', $bahra) ?: '0', -5);
    $localidades[] = [
        'cod_bahra' => $bahra,
        'nombre' => (string) ($l['nombre'] ?? ''),
        'cod_departamento' => $dept,
        'cod_postal' => str_pad(substr((string) $cp, 0, 5), 5, '0', STR_PAD_LEFT),
    ];
}

$payload = [
    'version' => 1,
    'source' => 'georef',
    'departamentos' => $departamentos,
    'localidades' => $localidades,
];

$json = json_encode($payload, JSON_UNESCAPED_UNICODE);
if ($json === false) {
    throw new RuntimeException('json_encode falló');
}
file_put_contents($outJson, $json);
file_put_contents($outGz, gzencode($json, 9));

echo 'dept=' . count($departamentos) . ' loc=' . count($localidades)
    . ' json=' . strlen($json) . ' gz=' . filesize($outGz) . PHP_EOL;
