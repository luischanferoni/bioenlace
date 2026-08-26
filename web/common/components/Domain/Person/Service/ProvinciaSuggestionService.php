<?php

namespace common\components\Domain\Person\Service;

use common\models\Provincia;
use Yii;

/**
 * Ordena provincias para contexto paciente (geolocalización IP + vecinos).
 *
 * El grafo de vecinos es constante de dominio (geografía INDEC estable), no metadata
 * de producto ni filas de BD: no hay beneficio en YAML/admin para un mapa fijo de 24 claves.
 */
final class ProvinciaSuggestionService
{
    /** @var list<string> */
    private const FALLBACK_COD_INDEC = ['86', '14', '06', '82', '02'];

    /**
     * Vecinos por cod_indec (INDEC) para priorizar provincias según geolocalización.
     *
     * @var array<string, list<string>>
     */
    private const VECINOS_POR_COD_INDEC = [
        '02' => ['06', '14', '82'],
        '06' => ['02', '14', '82', '86'],
        '10' => ['14', '18', '22', '86'],
        '14' => ['06', '10', '18', '22', '86'],
        '18' => ['10', '14', '22', '86'],
        '22' => ['14', '18', '86', '34'],
        '26' => ['30', '42', '50', '62'],
        '30' => ['26', '42', '50'],
        '34' => ['22', '86', '90'],
        '38' => ['46', '66', '90'],
        '42' => ['26', '30', '50', '62', '74'],
        '46' => ['38', '66', '90'],
        '50' => ['26', '30', '42', '62', '70', '74'],
        '54' => ['58', '78', '86'],
        '58' => ['54', '78', '86'],
        '62' => ['26', '42', '50', '74'],
        '66' => ['38', '46', '90'],
        '70' => ['50', '74', '82'],
        '74' => ['42', '50', '62', '70', '82'],
        '78' => ['54', '58', '86'],
        '82' => ['06', '14', '18', '22', '86'],
        '86' => ['10', '14', '22', '66', '82', '90'],
        '90' => ['34', '38', '46', '66', '82', '86'],
        '94' => ['78', '26', '58'],
    ];

    /**
     * Todas las provincias de BD, primero las más cercanas a la IP del cliente.
     *
     * @return list<array{id_provincia: int, nombre: string, cod_indec: string}>
     */
    public function listarOrdenadasPorProximidadIp(?string $ip = null): array
    {
        $provincias = Provincia::find()->all();
        if ($provincias === []) {
            return [];
        }

        $byCod = [];
        foreach ($provincias as $provincia) {
            $cod = str_pad(trim((string) $provincia->cod_indec), 2, '0', STR_PAD_LEFT);
            $byCod[$cod] = $provincia;
        }

        $ip = $ip ?? $this->resolveClientIp();
        $codIndec = $this->resolveCodIndecFromIp($ip);
        $vecinos = self::VECINOS_POR_COD_INDEC;

        $orderedCods = [];
        if ($codIndec !== null && $codIndec !== '' && isset($byCod[$codIndec])) {
            $orderedCods[] = $codIndec;
            foreach ($vecinos[$codIndec] ?? [] as $vecino) {
                $vecino = str_pad(trim((string) $vecino), 2, '0', STR_PAD_LEFT);
                if (!in_array($vecino, $orderedCods, true) && isset($byCod[$vecino])) {
                    $orderedCods[] = $vecino;
                }
            }
        } else {
            foreach (self::FALLBACK_COD_INDEC as $cod) {
                if (!isset($byCod[$cod])) {
                    continue;
                }
                if (!in_array($cod, $orderedCods, true)) {
                    $orderedCods[] = $cod;
                }
                foreach ($vecinos[$cod] ?? [] as $vecino) {
                    $vecino = str_pad(trim((string) $vecino), 2, '0', STR_PAD_LEFT);
                    if (!in_array($vecino, $orderedCods, true) && isset($byCod[$vecino])) {
                        $orderedCods[] = $vecino;
                    }
                }
            }
        }

        $remaining = array_keys($byCod);
        usort($remaining, static function (string $a, string $b) use ($byCod): int {
            return strcasecmp((string) $byCod[$a]->nombre, (string) $byCod[$b]->nombre);
        });
        foreach ($remaining as $cod) {
            if (!in_array($cod, $orderedCods, true)) {
                $orderedCods[] = $cod;
            }
        }

        $out = [];
        foreach ($orderedCods as $cod) {
            if (!isset($byCod[$cod])) {
                continue;
            }
            $out[] = $this->exportProvincia($byCod[$cod]);
        }

        return $out;
    }

    /**
     * @return list<array{id_provincia: int, nombre: string, cod_indec: string}>
     */
    public function sugerirPorIp(?string $ip = null): array
    {
        return $this->listarOrdenadasPorProximidadIp($ip);
    }

    /**
     * @return array{id_provincia: int, nombre: string, cod_indec: string}
     */
    private function exportProvincia(Provincia $provincia): array
    {
        return [
            'id_provincia' => (int) $provincia->id_provincia,
            'nombre' => (string) $provincia->nombre,
            'cod_indec' => str_pad(trim((string) $provincia->cod_indec), 2, '0', STR_PAD_LEFT),
        ];
    }

    private function resolveClientIp(): string
    {
        if (!Yii::$app->has('request')) {
            return '';
        }
        $req = Yii::$app->request;
        if (!$req instanceof \yii\web\Request) {
            return '';
        }

        return (string) $req->userIP;
    }

    private function resolveCodIndecFromIp(string $ip): ?string
    {
        if ($ip === '' || $this->isPrivateIp($ip)) {
            return '86';
        }

        try {
            $url = 'http://ip-api.com/json/' . rawurlencode($ip) . '?fields=status,countryCode,regionName';
            $raw = @file_get_contents($url);
            if ($raw === false) {
                return null;
            }
            $data = json_decode($raw, true);
            if (!is_array($data) || ($data['status'] ?? '') !== 'success') {
                return null;
            }
            if (strtoupper((string) ($data['countryCode'] ?? '')) !== 'AR') {
                return null;
            }
            $region = trim((string) ($data['regionName'] ?? ''));
            if ($region === '') {
                return null;
            }

            return $this->matchProvinciaCodIndec($region);
        } catch (\Throwable $e) {
            Yii::warning('GeoIP provincia: ' . $e->getMessage(), 'paciente_contexto');

            return null;
        }
    }

    private function matchProvinciaCodIndec(string $regionName): ?string
    {
        $norm = $this->normalize($regionName);
        foreach (Provincia::find()->all() as $provincia) {
            $pNorm = $this->normalize((string) $provincia->nombre);
            if ($pNorm === $norm || str_contains($pNorm, $norm) || str_contains($norm, $pNorm)) {
                return str_pad(trim((string) $provincia->cod_indec), 2, '0', STR_PAD_LEFT);
            }
        }

        return null;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtoupper(trim($value));
        $value = str_replace(
            ['Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ', 'CIUDAD AUTÓNOMA DE BUENOS AIRES', 'CABA', 'CAPITAL FEDERAL'],
            ['A', 'E', 'I', 'O', 'U', 'N', 'BUENOS AIRES', 'BUENOS AIRES', 'BUENOS AIRES'],
            $value
        );

        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }

    private function isPrivateIp(string $ip): bool
    {
        return !filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }
}
