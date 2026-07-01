<?php

namespace common\components\Domain\Person\Service;

use common\models\Provincia;
use Symfony\Component\Yaml\Yaml;
use Yii;
use yii\helpers\ArrayHelper;

/**
 * Ordena provincias para contexto paciente (geolocalización IP + vecinos declarativos).
 */
final class ProvinciaSuggestionService
{
    /** @var list<string> */
    private const FALLBACK_COD_INDEC = ['86', '14', '06', '82', '02'];

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
        $vecinos = $this->loadVecinosMap();

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

    /**
     * @return array<string, list<string>>
     */
    private function loadVecinosMap(): array
    {
        $path = Yii::getAlias('@common/metadata/bioenlace/geo/provincias-vecinas.yaml');
        if (!is_file($path)) {
            return [];
        }
        $parsed = Yaml::parseFile($path);
        if (!is_array($parsed)) {
            return [];
        }
        $map = $parsed['vecinos_por_cod_indec'] ?? [];

        return is_array($map) ? $map : [];
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
