<?php

namespace common\components\Domain\Geo\Application\Service;

use yii\db\Expression;
use yii\db\Query;

/**
 * Traduce coordenadas (lat/lng elegidas en el mapa) a la jerarquía geo local:
 * país → provincia → departamento → localidad.
 *
 * Estrategia: Google Geocoding aporta la dirección y los nombres; los IDs se
 * resuelven contra nuestras tablas `geo_*` por nombre dentro del padre. Si el
 * geocoding no está configurado o el nombre no matchea, se usa la localidad más
 * cercana por distancia (fallback determinístico, sin costo de API).
 */
final class GeoLocationResolver
{
    /** Radio máximo para aceptar la localidad más cercana. */
    public const MAX_DISTANCE_KM = 40.0;

    /**
     * @return array{
     *     lat: float,
     *     lng: float,
     *     direccion: ?string,
     *     pais: ?array{id: int, nombre: string, iso2: ?string},
     *     provincia: ?array{id: int, nombre: string},
     *     departamento: ?array{id: int, nombre: string},
     *     localidad: ?array{id: int, nombre: string, cod_postal: ?string},
     *     distancia_km: ?float,
     *     fuente: string
     * }
     */
    public function resolve(float $lat, float $lng): array
    {
        $geocoded = (new GoogleGeocodingClient())->reverse($lat, $lng);
        $nearest = $this->nearestLocality($lat, $lng);

        $pais = $this->matchPais($geocoded);
        if ($pais === null && $nearest !== null) {
            $pais = [
                'id' => (int) $nearest['id_pais'],
                'nombre' => (string) $nearest['pais'],
                'iso2' => $nearest['pais_iso2'] !== null ? (string) $nearest['pais_iso2'] : null,
            ];
        }

        $provincia = $this->matchProvincia($geocoded, $pais);
        if ($provincia === null && $nearest !== null && (int) $nearest['id_pais'] === (int) ($pais['id'] ?? 0)) {
            $provincia = [
                'id' => (int) $nearest['id_provincia'],
                'nombre' => (string) $nearest['provincia'],
            ];
        }

        $departamento = $this->matchDepartamento($geocoded, $provincia);
        if ($departamento === null && $nearest !== null && (int) $nearest['id_provincia'] === (int) ($provincia['id'] ?? 0)) {
            $departamento = [
                'id' => (int) $nearest['id_departamento'],
                'nombre' => (string) $nearest['departamento'],
            ];
        }

        $localidad = $this->matchLocalidad($geocoded, $departamento);
        if ($localidad === null && $nearest !== null && (int) $nearest['id_departamento'] === (int) ($departamento['id'] ?? 0)) {
            $localidad = [
                'id' => (int) $nearest['id_localidad'],
                'nombre' => (string) $nearest['localidad'],
                'cod_postal' => $nearest['localidad_cp'] !== null ? (string) $nearest['localidad_cp'] : null,
            ];
        }

        return [
            'lat' => $lat,
            'lng' => $lng,
            'direccion' => $geocoded['direccion'] ?? null,
            'pais' => $pais,
            'provincia' => $provincia,
            'departamento' => $departamento,
            'localidad' => $localidad,
            'distancia_km' => $nearest['distancia_m'] !== null
                ? round(((float) $nearest['distancia_m']) / 1000, 2)
                : null,
            'fuente' => $geocoded !== null ? 'google' : 'base',
        ];
    }

    /**
     * Localidad de la base geo más cercana a las coordenadas (dentro del radio).
     *
     * @return array{id_localidad: int, localidad: string, localidad_cp: ?string, id_departamento: int, departamento: string, id_provincia: int, provincia: string, id_pais: int, pais: string, pais_iso2: ?string, distancia_m: float}|null
     */
    public function nearestLocality(float $lat, float $lng, ?float $maxKm = null): ?array
    {
        $maxMeters = max(1.0, ($maxKm ?? self::MAX_DISTANCE_KM) * 1000);
        $expression = new Expression('ST_Distance_Sphere(l.coordenadas, POINT(:lng, :lat))');

        try {
            $row = (new Query())
                ->select([
                    'id_localidad' => 'l.id_localidad',
                    'localidad' => 'l.nombre',
                    'localidad_cp' => 'l.cod_postal',
                    'id_departamento' => 'd.id_departamento',
                    'departamento' => 'd.nombre',
                    'id_provincia' => 'p.id_provincia',
                    'provincia' => 'p.nombre',
                    'id_pais' => 'pa.id_pais',
                    'pais' => 'pa.nombre',
                    'pais_iso2' => 'pa.iso2',
                    'distancia_m' => $expression,
                ])
                ->from(['l' => '{{%geo_localidades}}'])
                ->innerJoin(['d' => '{{%geo_departamentos}}'], 'd.id_departamento = l.id_departamento')
                ->innerJoin(['p' => '{{%geo_provincias}}'], 'p.id_provincia = d.id_provincia')
                ->innerJoin(['pa' => '{{%geo_paises}}'], 'pa.id_pais = p.id_pais')
                ->where('l.coordenadas IS NOT NULL')
                ->having(['<=', 'distancia_m', $maxMeters])
                ->orderBy(['distancia_m' => SORT_ASC])
                ->limit(1)
                ->params([':lng' => $lng, ':lat' => $lat])
                ->one();
        } catch (\Throwable $e) {
            // Sin coordenadas en geo_localidades (o sin soporte espacial) seguimos
            // con lo que haya devuelto el geocoding.
            \Yii::warning(
                'No se pudo resolver la localidad más cercana: ' . $e->getMessage(),
                'geo.location'
            );

            return null;
        }

        if (!is_array($row)) {
            return null;
        }

        return [
            'id_localidad' => (int) $row['id_localidad'],
            'localidad' => (string) $row['localidad'],
            'localidad_cp' => $row['localidad_cp'] !== null ? (string) $row['localidad_cp'] : null,
            'id_departamento' => (int) $row['id_departamento'],
            'departamento' => (string) $row['departamento'],
            'id_provincia' => (int) $row['id_provincia'],
            'provincia' => (string) $row['provincia'],
            'id_pais' => (int) $row['id_pais'],
            'pais' => (string) $row['pais'],
            'pais_iso2' => $row['pais_iso2'] !== null ? (string) $row['pais_iso2'] : null,
            'distancia_m' => (float) $row['distancia_m'],
        ];
    }

    public static function nearestLocalityId(float $lat, float $lng): ?int
    {
        $row = (new self())->nearestLocality($lat, $lng);

        return $row !== null ? (int) $row['id_localidad'] : null;
    }

    /**
     * @param array<string, mixed>|null $geocoded
     * @return array{id: int, nombre: string, iso2: ?string}|null
     */
    private function matchPais(?array $geocoded): ?array
    {
        $iso2 = $geocoded['pais_iso2'] ?? null;
        $nombre = $geocoded['pais'] ?? null;

        $row = null;
        if (is_string($iso2) && $iso2 !== '') {
            $row = (new Query())
                ->from('{{%geo_paises}}')
                ->where(new Expression('UPPER(iso2) = :iso2'), [':iso2' => strtoupper($iso2)])
                ->one();
        }
        if (!is_array($row) && is_string($nombre) && $nombre !== '') {
            $row = (new Query())
                ->from('{{%geo_paises}}')
                ->where(new Expression('LOWER(nombre) = :nombre'), [':nombre' => mb_strtolower($nombre)])
                ->one();
        }
        if (!is_array($row)) {
            return null;
        }

        return [
            'id' => (int) $row['id_pais'],
            'nombre' => (string) $row['nombre'],
            'iso2' => $row['iso2'] !== null ? (string) $row['iso2'] : null,
        ];
    }

    /**
     * @param array<string, mixed>|null $geocoded
     * @param array{id: int, nombre: string, iso2: ?string}|null $pais
     * @return array{id: int, nombre: string}|null
     */
    private function matchProvincia(?array $geocoded, ?array $pais): ?array
    {
        $nombre = $geocoded['provincia'] ?? null;
        if ($pais === null || !is_string($nombre) || $nombre === '') {
            return null;
        }

        $row = (new Query())
            ->from('{{%geo_provincias}}')
            ->where(['id_pais' => (int) $pais['id']])
            ->andWhere(new Expression('LOWER(nombre) = :nombre'), [':nombre' => mb_strtolower($nombre)])
            ->one();

        return is_array($row)
            ? ['id' => (int) $row['id_provincia'], 'nombre' => (string) $row['nombre']]
            : null;
    }

    /**
     * @param array<string, mixed>|null $geocoded
     * @param array{id: int, nombre: string}|null $provincia
     * @return array{id: int, nombre: string}|null
     */
    private function matchDepartamento(?array $geocoded, ?array $provincia): ?array
    {
        $nombre = $geocoded['departamento'] ?? null;
        if ($provincia === null || !is_string($nombre) || $nombre === '') {
            return null;
        }

        $row = (new Query())
            ->from('{{%geo_departamentos}}')
            ->where(['id_provincia' => (int) $provincia['id']])
            ->andWhere(new Expression('LOWER(nombre) = :nombre'), [':nombre' => mb_strtolower($nombre)])
            ->one();

        return is_array($row)
            ? ['id' => (int) $row['id_departamento'], 'nombre' => (string) $row['nombre']]
            : null;
    }

    /**
     * @param array<string, mixed>|null $geocoded
     * @param array{id: int, nombre: string}|null $departamento
     * @return array{id: int, nombre: string, cod_postal: ?string}|null
     */
    private function matchLocalidad(?array $geocoded, ?array $departamento): ?array
    {
        $nombre = $geocoded['localidad'] ?? null;
        if ($departamento === null || !is_string($nombre) || $nombre === '') {
            return null;
        }

        $row = (new Query())
            ->from('{{%geo_localidades}}')
            ->where(['id_departamento' => (int) $departamento['id']])
            ->andWhere(new Expression('LOWER(nombre) = :nombre'), [':nombre' => mb_strtolower($nombre)])
            ->one();

        return is_array($row)
            ? [
                'id' => (int) $row['id_localidad'],
                'nombre' => (string) $row['nombre'],
                'cod_postal' => $row['cod_postal'] !== null ? (string) $row['cod_postal'] : null,
            ]
            : null;
    }
}
