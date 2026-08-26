<?php

namespace common\components\Domain\Person\Service;

use common\models\Pais;
use common\models\Provincia;
use Yii;
use yii\db\Query;

/**
 * Ordena provincias de un país (IP o iso2) usando vecinos en BD.
 */
final class ProvinciaSuggestionService
{
    /** @var array<int, list<int>>|null */
    private static ?array $vecinosCache = null;

    public static function resetCacheForTests(): void
    {
        self::$vecinosCache = null;
    }

    /**
     * @return list<array{id_provincia: int, nombre: string, cod_indec: string, id_pais: int, iso2: string, pais: string}>
     */
    public function sugerirPorIp(?string $ip = null, ?string $iso2 = null, ?int $idPais = null): array
    {
        $pais = $this->resolvePais($ip, $iso2, $idPais);
        if ($pais === null) {
            return [];
        }

        $provincias = Provincia::find()->where(['id_pais' => (int) $pais->id_pais])->all();
        if ($provincias === []) {
            return [];
        }

        $byCod = [];
        $byId = [];
        foreach ($provincias as $provincia) {
            $cod = $this->normalizeCod((string) $provincia->cod_indec);
            $byCod[$cod] = $provincia;
            $byId[(int) $provincia->id_provincia] = $provincia;
        }

        $ip = $ip ?? $this->resolveClientIp();
        $codHint = null;
        if ($iso2 === null && $idPais === null) {
            $codHint = $this->resolveSubdivisionCodFromIp($ip, $pais);
        }

        $vecinosById = $this->loadVecinosMap(array_keys($byId));

        $orderedIds = [];
        if ($codHint !== null && isset($byCod[$codHint])) {
            $this->appendProvinciaAndVecinos($orderedIds, (int) $byCod[$codHint]->id_provincia, $vecinosById, $byId);
        } else {
            foreach ($this->fallbackCodsForIso2((string) $pais->iso2) as $cod) {
                if (!isset($byCod[$cod])) {
                    continue;
                }
                $this->appendProvinciaAndVecinos($orderedIds, (int) $byCod[$cod]->id_provincia, $vecinosById, $byId);
            }
        }

        $remaining = array_keys($byId);
        usort($remaining, static function (int $a, int $b) use ($byId): int {
            return strcasecmp((string) $byId[$a]->nombre, (string) $byId[$b]->nombre);
        });
        foreach ($remaining as $id) {
            if (!in_array($id, $orderedIds, true)) {
                $orderedIds[] = $id;
            }
        }

        $out = [];
        foreach ($orderedIds as $id) {
            $out[] = $this->exportProvincia($byId[$id], $pais);
        }

        return $out;
    }

    /**
     * @return list<array{id_pais: int, iso2: string, nombre: string}>
     */
    public function listarPaises(): array
    {
        $out = [];
        foreach (Pais::find()->orderBy(['nombre' => SORT_ASC])->all() as $pais) {
            $out[] = [
                'id_pais' => (int) $pais->id_pais,
                'iso2' => (string) $pais->iso2,
                'nombre' => (string) $pais->nombre,
            ];
        }

        return $out;
    }

    private function resolvePais(?string $ip, ?string $iso2, ?int $idPais): ?Pais
    {
        if ($idPais !== null && $idPais > 0) {
            return Pais::findOne($idPais);
        }
        if ($iso2 !== null && trim($iso2) !== '') {
            return Pais::findByIso2($iso2);
        }

        $ip = $ip ?? $this->resolveClientIp();
        $fromIp = $this->resolveIso2FromIp($ip);
        if ($fromIp !== null) {
            $pais = Pais::findByIso2($fromIp);
            if ($pais instanceof Pais) {
                return $pais;
            }
        }

        return Pais::defaultPais();
    }

    /**
     * @param list<int> $orderedIds
     * @param array<int, list<int>> $vecinosById
     * @param array<int, Provincia> $byId
     */
    private function appendProvinciaAndVecinos(array &$orderedIds, int $id, array $vecinosById, array $byId): void
    {
        if (!isset($byId[$id])) {
            return;
        }
        if (!in_array($id, $orderedIds, true)) {
            $orderedIds[] = $id;
        }
        foreach ($vecinosById[$id] ?? [] as $vecinoId) {
            if (!isset($byId[$vecinoId])) {
                continue;
            }
            if (!in_array($vecinoId, $orderedIds, true)) {
                $orderedIds[] = $vecinoId;
            }
        }
    }

    /**
     * @param list<int> $idsProvincia
     * @return array<int, list<int>>
     */
    private function loadVecinosMap(array $idsProvincia): array
    {
        if ($idsProvincia === []) {
            return [];
        }
        if (self::$vecinosCache === null) {
            self::$vecinosCache = [];
            $rows = (new Query())
                ->from('{{%geo_provincia_vecinos}}')
                ->select(['id_provincia', 'id_provincia_vecina'])
                ->all();
            foreach ($rows as $row) {
                $from = (int) $row['id_provincia'];
                $to = (int) $row['id_provincia_vecina'];
                self::$vecinosCache[$from][] = $to;
            }
        }

        $out = [];
        foreach ($idsProvincia as $id) {
            $out[$id] = self::$vecinosCache[$id] ?? [];
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function fallbackCodsForIso2(string $iso2): array
    {
        $iso2 = strtoupper(trim($iso2));
        $map = [];
        if (Yii::$app->has('params')) {
            $configured = Yii::$app->params['geoSuggestionFallbackCodigos'] ?? null;
            if (is_array($configured)) {
                $map = $configured;
            }
        }
        $list = $map[$iso2] ?? [];
        if (!is_array($list)) {
            return [];
        }
        $out = [];
        foreach ($list as $cod) {
            if (is_string($cod) || is_int($cod)) {
                $out[] = $this->normalizeCod((string) $cod);
            }
        }

        return $out;
    }

    /**
     * @return array{id_provincia: int, nombre: string, cod_indec: string, id_pais: int, iso2: string, pais: string}
     */
    private function exportProvincia(Provincia $provincia, Pais $pais): array
    {
        return [
            'id_provincia' => (int) $provincia->id_provincia,
            'nombre' => (string) $provincia->nombre,
            'cod_indec' => $this->normalizeCod((string) $provincia->cod_indec),
            'id_pais' => (int) $pais->id_pais,
            'iso2' => (string) $pais->iso2,
            'pais' => (string) $pais->nombre,
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

    private function resolveIso2FromIp(string $ip): ?string
    {
        if ($ip === '' || $this->isPrivateIp($ip)) {
            $default = Pais::defaultPais();

            return $default !== null ? (string) $default->iso2 : null;
        }

        try {
            $url = 'http://ip-api.com/json/' . rawurlencode($ip) . '?fields=status,countryCode';
            $raw = @file_get_contents($url);
            if ($raw === false) {
                return null;
            }
            $data = json_decode($raw, true);
            if (!is_array($data) || ($data['status'] ?? '') !== 'success') {
                return null;
            }
            $code = strtoupper(trim((string) ($data['countryCode'] ?? '')));

            return $code !== '' ? $code : null;
        } catch (\Throwable $e) {
            Yii::warning('GeoIP pais: ' . $e->getMessage(), 'paciente_contexto');

            return null;
        }
    }

    private function resolveSubdivisionCodFromIp(string $ip, Pais $pais): ?string
    {
        if ($ip === '' || $this->isPrivateIp($ip)) {
            $fallbacks = $this->fallbackCodsForIso2((string) $pais->iso2);

            return $fallbacks[0] ?? null;
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
            if (strtoupper((string) ($data['countryCode'] ?? '')) !== strtoupper((string) $pais->iso2)) {
                return null;
            }
            $region = trim((string) ($data['regionName'] ?? ''));
            if ($region === '') {
                return null;
            }

            return $this->matchProvinciaCod($region, (int) $pais->id_pais);
        } catch (\Throwable $e) {
            Yii::warning('GeoIP provincia: ' . $e->getMessage(), 'paciente_contexto');

            return null;
        }
    }

    private function matchProvinciaCod(string $regionName, int $idPais): ?string
    {
        $norm = $this->normalize($regionName);
        foreach (Provincia::find()->where(['id_pais' => $idPais])->all() as $provincia) {
            $pNorm = $this->normalize((string) $provincia->nombre);
            if ($pNorm === $norm || str_contains($pNorm, $norm) || str_contains($norm, $pNorm)) {
                return $this->normalizeCod((string) $provincia->cod_indec);
            }
        }

        return null;
    }

    private function normalizeCod(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return $value;
        }
        if (ctype_digit($value) && strlen($value) <= 2) {
            return str_pad($value, 2, '0', STR_PAD_LEFT);
        }

        return strtoupper($value);
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
