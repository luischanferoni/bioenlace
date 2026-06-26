<?php

namespace common\components\Domain\Person\Service;

use common\models\Provincia;
use Yii;
use yii\helpers\ArrayHelper;
use yii\helpers\Yaml;

/**
 * Sugiere provincias para contexto paciente (geolocalización IP + vecinos declarativos).
 */
final class ProvinciaSuggestionService
{
    private const LIMITE = 5;

    /**
     * @return list<array{id_provincia: int, nombre: string, cod_indec: string}>
     */
    public function sugerirPorIp(?string $ip = null): array
    {
        $ip = $ip ?? $this->resolveClientIp();
        $codIndec = $this->resolveCodIndecFromIp($ip);
        $vecinos = $this->loadVecinosMap();

        $codigos = [];
        if ($codIndec !== null && $codIndec !== '') {
            $codigos[] = $codIndec;
            foreach ($vecinos[$codIndec] ?? [] as $vecino) {
                if (!in_array($vecino, $codigos, true)) {
                    $codigos[] = $vecino;
                }
            }
        }

        if ($codigos === []) {
            $codigos = ['86', '14', '06', '82', '00'];
        }

        $codigos = array_slice($codigos, 0, self::LIMITE);
        $provincias = Provincia::find()
            ->where(['cod_indec' => $codigos])
            ->all();
        $byCod = [];
        foreach ($provincias as $p) {
            $byCod[(string) $p->cod_indec] = $p;
        }

        $out = [];
        foreach ($codigos as $cod) {
            if (!isset($byCod[$cod])) {
                continue;
            }
            $p = $byCod[$cod];
            $out[] = [
                'id_provincia' => (int) $p->id_provincia,
                'nombre' => (string) $p->nombre,
                'cod_indec' => (string) $p->cod_indec,
            ];
            if (count($out) >= self::LIMITE) {
                break;
            }
        }

        if (count($out) < self::LIMITE) {
            $extra = Provincia::find()
                ->orderBy(['nombre' => SORT_ASC])
                ->limit(self::LIMITE)
                ->all();
            foreach ($extra as $p) {
                $id = (int) $p->id_provincia;
                if (ArrayHelper::getColumn($out, 'id_provincia') !== []
                    && in_array($id, ArrayHelper::getColumn($out, 'id_provincia'), true)) {
                    continue;
                }
                $out[] = [
                    'id_provincia' => $id,
                    'nombre' => (string) $p->nombre,
                    'cod_indec' => (string) $p->cod_indec,
                ];
                if (count($out) >= self::LIMITE) {
                    break;
                }
            }
        }

        return $out;
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
                return (string) $provincia->cod_indec;
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
            ['Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ', 'CIUDAD AUTÓNOMA DE BUENOS AIRES', 'CABA'],
            ['A', 'E', 'I', 'O', 'U', 'N', 'BUENOS AIRES', 'BUENOS AIRES'],
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
