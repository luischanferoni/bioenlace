<?php

namespace common\components\Domain\Geo\Infrastructure\External\GoogleGeocoding;

use common\components\Shared\Infrastructure\Http\HttpApiException;
use common\components\Shared\Infrastructure\Http\JsonApiClient;
use Yii;

/**
 * Reverse geocoding contra Google Maps Geocoding API (server-side).
 *
 * La API key se configura en `params-local.php` como `google_maps_geocoding_api_key`
 * (fallback: `google_cloud_api_key`). Sin key, `isConfigured()` devuelve false y el
 * resolver usa sólo la base geo.
 */
final class GoogleGeocodingApiClient
{
    public const DEFAULT_BASE_URL = 'https://maps.googleapis.com/maps/api/geocode/json';

    public const LOG_CATEGORY = 'geo.geocoding';

    private const DEFAULT_TIMEOUT = 8;

    public function isConfigured(): bool
    {
        return $this->apiKey() !== '';
    }

    /**
     * @return array{
     *     direccion: ?string,
     *     pais: ?string,
     *     pais_iso2: ?string,
     *     provincia: string[],
     *     departamento: string[],
     *     localidad: string[]
     * }|null
     */
    public function reverse(float $lat, float $lng): ?array
    {
        $apiKey = $this->apiKey();
        if ($apiKey === '') {
            return null;
        }

        try {
            $response = $this->client()->get('', [
                'latlng' => sprintf('%.7F,%.7F', $lat, $lng),
                'language' => 'es',
                'key' => $apiKey,
            ]);
        } catch (HttpApiException $e) {
            Yii::warning('Google Geocoding falló: ' . $e->getMessage(), self::LOG_CATEGORY);

            return null;
        }

        $data = $response->body();
        if (($data['status'] ?? '') !== 'OK') {
            $status = (string) ($data['status'] ?? 'INVALID_RESPONSE');
            if ($status !== 'ZERO_RESULTS') {
                Yii::warning('Google Geocoding status: ' . $status, self::LOG_CATEGORY);
            }

            return null;
        }

        $result = $data['results'][0] ?? null;
        if (!is_array($result)) {
            return null;
        }

        $components = [];
        foreach (($result['address_components'] ?? []) as $component) {
            if (!is_array($component)) {
                continue;
            }
            foreach (($component['types'] ?? []) as $type) {
                $components[(string) $type] ??= $component;
            }
        }

        $pick = static function (array $types, string $field) use ($components): ?string {
            foreach ($types as $type) {
                $value = trim((string) ($components[$type][$field] ?? ''));
                if ($value !== '') {
                    return $value;
                }
            }

            return null;
        };

        // Varias componentes pueden describir el mismo nivel (en CABA la
        // `locality` es "Buenos Aires" y el barrio viene en
        // `sublocality_level_1`): devolvemos todas para que el resolver pruebe
        // en orden hasta encontrar la que existe en nuestra base.
        $pickAll = static function (array $types, array $fields) use ($components): array {
            $values = [];
            foreach ($fields as $field) {
                foreach ($types as $type) {
                    $value = trim((string) ($components[$type][$field] ?? ''));
                    if ($value !== '' && !in_array($value, $values, true)) {
                        $values[] = $value;
                    }
                }
            }

            return $values;
        };

        return [
            'direccion' => trim((string) ($result['formatted_address'] ?? '')) ?: null,
            'pais' => $pick(['country'], 'long_name'),
            'pais_iso2' => $pick(['country'], 'short_name'),
            // El `short_name` administrativo es el más específico (CABA) y el
            // `long_name` el más descriptivo ("Provincia de …"): probamos el
            // corto primero y dejamos el largo como respaldo.
            'provincia' => $pickAll(['administrative_area_level_1'], ['short_name', 'long_name']),
            'departamento' => $pickAll(['administrative_area_level_2'], ['short_name', 'long_name']),
            'localidad' => $pickAll(
                [
                    'locality',
                    'sublocality_level_1',
                    'sublocality_level_2',
                    'administrative_area_level_3',
                    'postal_town',
                ],
                ['long_name']
            ),
        ];
    }

    private function client(): JsonApiClient
    {
        $timeout = (float) (Yii::$app->params['google_maps_geocoding_timeout'] ?? self::DEFAULT_TIMEOUT);

        return new JsonApiClient(
            baseUrl: (string) (Yii::$app->params['google_maps_geocoding_base_url'] ?? self::DEFAULT_BASE_URL),
            connectTimeout: min(5.0, $timeout),
            timeout: $timeout,
            logCategory: self::LOG_CATEGORY,
            provider: 'google-geocoding',
            providerLabel: 'Google Geocoding'
        );
    }

    private function apiKey(): string
    {
        $params = Yii::$app->params;

        return trim((string) ($params['google_maps_geocoding_api_key']
            ?? $params['google_cloud_api_key']
            ?? ''));
    }
}
