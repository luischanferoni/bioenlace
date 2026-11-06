<?php

namespace common\components\Domain\Geo\Application\Service;

use common\components\Domain\Geo\Infrastructure\External\GoogleGeocoding\GoogleGeocodingApiClient;
use yii\db\Expression;
use yii\db\Query;

/**
 * Traduce coordenadas (lat/lng elegidas en el mapa) a la jerarquía geo local:
 * país → provincia → departamento → localidad.
 *
 * Estrategia: Google Geocoding aporta la dirección y los nombres; los IDs se
 * resuelven contra nuestras tablas `geo_*` dentro del padre ya resuelto. El
 * nombre se compara normalizado (minúsculas, sin acentos y sin los prefijos
 * administrativos que agrega Google), y cuando hay match se cachea el nombre
 * original de Google en `geo_*.google_nombre` para que la próxima búsqueda sea
 * una igualdad directa. Si el geocoding no está configurado o el nombre no
 * matchea, se usa la localidad más cercana por distancia (fallback
 * determinístico, sin costo de API).
 */
final class GeoLocationResolver
{
    /** Radio máximo para aceptar la localidad más cercana. */
    public const MAX_DISTANCE_KM = 40.0;

    /** Longitud de `geo_*.google_nombre` (ver migración m261105_120000). */
    private const GOOGLE_NOMBRE_LENGTH = [
        '{{%geo_provincias}}' => 120,
        '{{%geo_departamentos}}' => 150,
        '{{%geo_localidades}}' => 150,
    ];

    /** Prefijos que Google antepone al nombre real de la entidad. */
    private const NOISE_PREFIXES = [
        'provincia de', 'provincia del', 'provincia',
        'departamento de', 'departamento del', 'departamento',
        'partido de', 'partido del', 'partido',
        'municipio de', 'municipio del', 'municipio',
        'localidad de', 'localidad del', 'localidad',
        'comuna de', 'comuna',
        'ciudad de', 'ciudad del',
        'barrio de', 'barrio',
        'parroquia de', 'parroquia',
    ];

    /** Nombres de Google que no se corresponden con el que usamos nosotros. */
    private const NAME_ALIASES = [
        'ciudad autonoma de buenos aires' => 'caba',
        'ciudad de buenos aires' => 'caba',
        'capital federal' => 'caba',
        'caba' => 'caba',
    ];

    private const ACCENT_FOLD = [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
        'ñ' => 'n', 'ç' => 'c',
    ];

    /** @var array<string, bool> Tablas que ya validamos que tienen `google_nombre`. */
    private $googleNombreColumns = [];

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
        $geocoded = (new GoogleGeocodingApiClient())->reverse($lat, $lng);
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

        $departamento = $this->matchDepartamento($geocoded, $pais, $provincia);
        if ($departamento === null && $nearest !== null && (int) $nearest['id_provincia'] === (int) ($provincia['id'] ?? 0)) {
            $departamento = [
                'id' => (int) $nearest['id_departamento'],
                'nombre' => (string) $nearest['departamento'],
                'id_provincia' => (int) $nearest['id_provincia'],
            ];
        }

        $localidad = $this->matchLocalidad($geocoded, $provincia, $departamento);
        if ($localidad === null && $nearest !== null && (int) $nearest['id_departamento'] === (int) ($departamento['id'] ?? 0)) {
            $localidad = [
                'id' => (int) $nearest['id_localidad'],
                'nombre' => (string) $nearest['localidad'],
                'cod_postal' => $nearest['localidad_cp'] !== null ? (string) $nearest['localidad_cp'] : null,
            ];
        }

        // La localidad también identifica a su departamento (y éste a su
        // provincia): completamos la jerarquía cuando Google no la devolvió.
        if ($departamento === null && $localidad !== null && ($localidad['id_departamento'] ?? null) !== null) {
            $departamento = $this->departamentoById((int) $localidad['id_departamento']);
        }
        if ($provincia === null && $departamento !== null && $departamento['id_provincia'] !== null) {
            $provincia = $this->provinciaById((int) $departamento['id_provincia']);
        }

        return [
            'lat' => $lat,
            'lng' => $lng,
            'direccion' => $geocoded['direccion'] ?? null,
            'pais' => $pais,
            'provincia' => $provincia === null ? null : [
                'id' => (int) $provincia['id'],
                'nombre' => (string) $provincia['nombre'],
            ],
            'departamento' => $departamento === null ? null : [
                'id' => (int) $departamento['id'],
                'nombre' => (string) $departamento['nombre'],
            ],
            'localidad' => $localidad === null ? null : [
                'id' => (int) $localidad['id'],
                'nombre' => (string) $localidad['nombre'],
                'cod_postal' => $localidad['cod_postal'] !== null ? (string) $localidad['cod_postal'] : null,
            ],
            'distancia_km' => $nearest !== null
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

        if (is_string($iso2) && $iso2 !== '') {
            $row = (new Query())
                ->from('{{%geo_paises}}')
                ->where(new Expression('UPPER(iso2) = :iso2'), [':iso2' => strtoupper($iso2)])
                ->one();
            if (is_array($row)) {
                return $this->paisFromRow($row);
            }
        }

        $row = $this->findGeoRow('{{%geo_paises}}', 'id_pais', $this->names($geocoded['pais'] ?? null), []);

        return $row !== null ? $this->paisFromRow($row) : null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array{id: int, nombre: string, iso2: ?string}
     */
    private function paisFromRow(array $row): array
    {
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
        if ($pais === null) {
            return null;
        }

        $row = $this->findGeoRow(
            '{{%geo_provincias}}',
            'id_provincia',
            $this->aliasesFirst($this->names($geocoded['provincia'] ?? null)),
            ['id_pais' => (int) $pais['id']]
        );

        return $row !== null
            ? ['id' => (int) $row['id_provincia'], 'nombre' => (string) $row['nombre']]
            : null;
    }

    /**
     * Busca el departamento dentro de la provincia ya resuelta; sin provincia,
     * sólo lo acepta si el nombre es único en el país.
     *
     * @param array<string, mixed>|null $geocoded
     * @param array{id: int, nombre: string, iso2: ?string}|null $pais
     * @param array{id: int, nombre: string}|null $provincia
     * @return array{id: int, nombre: string, id_provincia: ?int}|null
     */
    private function matchDepartamento(?array $geocoded, ?array $pais, ?array $provincia): ?array
    {
        $names = $this->names($geocoded['departamento'] ?? null);
        if ($names === []) {
            return null;
        }

        $row = null;
        if ($provincia !== null) {
            $row = $this->findGeoRow(
                '{{%geo_departamentos}}',
                'id_departamento',
                $names,
                ['id_provincia' => (int) $provincia['id']]
            );
        } elseif ($pais !== null) {
            $row = $this->findGeoRow(
                '{{%geo_departamentos}}',
                'id_departamento',
                $names,
                ['id_provincia' => $this->provinciaIdsOfPais((int) $pais['id'])],
                true
            );
        }

        return $row !== null ? $this->departamentoFromRow($row) : null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array{id: int, nombre: string, id_provincia: ?int}
     */
    private function departamentoFromRow(array $row): array
    {
        return [
            'id' => (int) $row['id_departamento'],
            'nombre' => (string) $row['nombre'],
            'id_provincia' => $row['id_provincia'] !== null ? (int) $row['id_provincia'] : null,
        ];
    }

    /**
     * Sin departamento, la localidad se acepta sólo si el nombre es único en la
     * provincia: no adivinamos entre localidades homónimas.
     *
     * @param array<string, mixed>|null $geocoded
     * @param array{id: int, nombre: string}|null $provincia
     * @param array{id: int, nombre: string, id_provincia: ?int}|null $departamento
     * @return array{id: int, nombre: string, cod_postal: ?string, id_departamento: ?int}|null
     */
    private function matchLocalidad(?array $geocoded, ?array $provincia, ?array $departamento): ?array
    {
        $names = $this->names($geocoded['localidad'] ?? null);
        if ($names === []) {
            return null;
        }

        $row = null;
        if ($departamento !== null) {
            $row = $this->findGeoRow(
                '{{%geo_localidades}}',
                'id_localidad',
                $names,
                ['id_departamento' => (int) $departamento['id']]
            );
        } elseif ($provincia !== null) {
            $row = $this->findGeoRow(
                '{{%geo_localidades}}',
                'id_localidad',
                $names,
                ['id_departamento' => $this->departamentoIdsOfProvincia((int) $provincia['id'])],
                true
            );
        }

        return $row !== null ? $this->localidadFromRow($row) : null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array{id: int, nombre: string, cod_postal: ?string, id_departamento: ?int}
     */
    private function localidadFromRow(array $row): array
    {
        return [
            'id' => (int) $row['id_localidad'],
            'nombre' => (string) $row['nombre'],
            'cod_postal' => $row['cod_postal'] !== null ? (string) $row['cod_postal'] : null,
            'id_departamento' => $row['id_departamento'] !== null ? (int) $row['id_departamento'] : null,
        ];
    }

    /**
     * Busca la fila de `$table` que corresponde a alguno de los nombres que
     * devolvió Google, acotada por `$parent`.
     *
     * Prueba en orden: el nombre ya cacheado en `google_nombre`, el nombre
     * normalizado contra `nombre` y, si `$unique`, sólo acepta el resultado
     * cuando hay una única coincidencia.
     *
     * @param string[] $googleNames Nombres candidatos, del más al menos específico.
     * @param array<string, mixed> $parent Condición que acota la búsqueda.
     * @return array<string, mixed>|null
     */
    private function findGeoRow(string $table, string $idColumn, array $googleNames, array $parent, bool $unique = false): ?array
    {
        $withGoogleNombre = $this->hasGoogleNombre($table);
        $rows = null;

        foreach ($googleNames as $googleName) {
            $googleName = trim($googleName);
            if ($googleName === '') {
                continue;
            }

            // El cache es por fila, no por contexto: sólo sirve cuando la
            // búsqueda ya está acotada por un padre exacto.
            if ($withGoogleNombre && !$unique) {
                $cached = (new Query())
                    ->from($table)
                    ->where($parent)
                    ->andWhere(['google_nombre' => $googleName])
                    ->one();
                if (is_array($cached)) {
                    return $cached;
                }
            }

            $wanted = self::normalizeName($googleName);
            if ($wanted === '') {
                continue;
            }

            if ($rows === null) {
                $rows = (new Query())->from($table)->where($parent)->all();
            }

            $matches = [];
            foreach ($rows as $row) {
                $isCached = $withGoogleNombre
                    && self::normalizeName((string) ($row['google_nombre'] ?? '')) === $wanted;
                $isNamed = self::normalizeName((string) $row['nombre']) === $wanted;
                if (!$isCached && !$isNamed) {
                    continue;
                }
                if (!$unique) {
                    $this->rememberGoogleNombre($table, $idColumn, (int) $row[$idColumn], $googleName);

                    return $row;
                }
                $matches[] = $row;
            }

            if (count($matches) === 1) {
                $row = $matches[0];
                $this->rememberGoogleNombre($table, $idColumn, (int) $row[$idColumn], $googleName);

                return $row;
            }
        }

        return null;
    }

    /**
     * Deja el nombre comparable: minúsculas, sin acentos y sin signos.
     */
    private static function fold(?string $name): string
    {
        if ($name === null) {
            return '';
        }

        $value = mb_strtolower(trim($name), 'UTF-8');
        $value = strtr($value, self::ACCENT_FOLD);

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $value));
    }

    /**
     * Deja el nombre comparable: minúsculas, sin acentos, sin signos y sin los
     * prefijos administrativos que agrega Google ("Provincia de …").
     */
    public static function normalizeName(?string $name): string
    {
        $value = self::fold($name);
        if ($value === '') {
            return '';
        }
        if (isset(self::NAME_ALIASES[$value])) {
            return self::NAME_ALIASES[$value];
        }

        foreach (self::NOISE_PREFIXES as $prefix) {
            $prefix = $prefix . ' ';
            if (strncmp($value, $prefix, strlen($prefix)) === 0) {
                $stripped = trim(substr($value, strlen($prefix)));
                if ($stripped !== '') {
                    $value = $stripped;
                    break;
                }
            }
        }

        return self::NAME_ALIASES[$value] ?? $value;
    }

    /**
     * Los alias ("CABA") identifican mejor la entidad que los nombres largos de
     * Google ("Buenos Aires" para la provincia): los probamos primero.
     *
     * @param string[] $names
     * @return string[]
     */
    private function aliasesFirst(array $names): array
    {
        $aliased = [];
        $rest = [];
        foreach ($names as $name) {
            if (isset(self::NAME_ALIASES[self::fold($name)])) {
                $aliased[] = $name;
            } else {
                $rest[] = $name;
            }
        }

        return array_merge($aliased, $rest);
    }

    /**
     * @param mixed $value Nombres devueltos por Google (string o lista).
     * @return string[]
     */
    private function names($value): array
    {
        $names = [];
        foreach (is_array($value) ? $value : [$value] as $item) {
            if (!is_string($item)) {
                continue;
            }
            $item = trim($item);
            if ($item !== '' && !in_array($item, $names, true)) {
                $names[] = $item;
            }
        }

        return $names;
    }

    private function hasGoogleNombre(string $table): bool
    {
        if (array_key_exists($table, $this->googleNombreColumns)) {
            return $this->googleNombreColumns[$table];
        }

        $has = false;
        if (isset(self::GOOGLE_NOMBRE_LENGTH[$table])) {
            try {
                $schema = \Yii::$app->db->schema->getTableSchema($table, true);
                $has = $schema !== null && $schema->getColumn('google_nombre') !== null;
            } catch (\Throwable $e) {
                $has = false;
            }
        }

        $this->googleNombreColumns[$table] = $has;

        return $has;
    }

    /**
     * Guarda el nombre de Google en la fila para que la próxima búsqueda sea una
     * igualdad directa (y sobreviva a cambios de nomenclatura).
     */
    private function rememberGoogleNombre(string $table, string $idColumn, int $id, string $googleName): void
    {
        if (!isset(self::GOOGLE_NOMBRE_LENGTH[$table])) {
            return;
        }

        try {
            \Yii::$app->db->createCommand()
                ->update(
                    $table,
                    ['google_nombre' => mb_substr($googleName, 0, self::GOOGLE_NOMBRE_LENGTH[$table])],
                    [$idColumn => $id]
                )
                ->execute();
        } catch (\Throwable $e) {
            \Yii::warning(
                'No se pudo cachear el nombre de Google: ' . $e->getMessage(),
                'geo.location'
            );
        }
    }

    private function provinciaIdsOfPais(int $idPais): Query
    {
        return (new Query())
            ->select('id_provincia')
            ->from('{{%geo_provincias}}')
            ->where(['id_pais' => $idPais]);
    }

    private function departamentoIdsOfProvincia(int $idProvincia): Query
    {
        return (new Query())
            ->select('id_departamento')
            ->from('{{%geo_departamentos}}')
            ->where(['id_provincia' => $idProvincia]);
    }

    /**
     * @return array{id: int, nombre: string}|null
     */
    private function provinciaById(int $id): ?array
    {
        $row = (new Query())->from('{{%geo_provincias}}')->where(['id_provincia' => $id])->one();

        return is_array($row)
            ? ['id' => (int) $row['id_provincia'], 'nombre' => (string) $row['nombre']]
            : null;
    }

    /**
     * @return array{id: int, nombre: string, id_provincia: ?int}|null
     */
    private function departamentoById(int $id): ?array
    {
        $row = (new Query())->from('{{%geo_departamentos}}')->where(['id_departamento' => $id])->one();

        return is_array($row) ? $this->departamentoFromRow($row) : null;
    }
}

