<?php

namespace common\components\Domain\Geo\Application\Service;

use Yii;
use yii\httpclient\Client;

/**
 * Reverse geocoding contra Google Maps Geocoding API (server-side).
 *
 * La API key se configura en `params-local.php` como
 * `google_maps_geocoding_api_key` (fallback: `google_cloud_api_key`).
 * Sin key, `isConfigured()` devuelve false y el resolver usa sólo la base geo.
 */
final class GoogleGeocodingClient
{
    private const DEFAULT_BASE_URL = 'https://maps.googleapis.com/maps/api/geocode/json';

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

        $baseUrl = (string) (Yii::$app->params['google_maps_geocoding_base_url'] ?? self::DEFAULT_BASE_URL);
        $timeout = (int) (Yii::$app->params['google_maps_geocoding_timeout'] ?? 8);

        try {
            $response = (new Client([
                'requestConfig' => [
                    'options' => ['timeout' => $timeout],
                ],
            ]))->createRequest()
                ->setMethod('GET')
                ->setUrl($baseUrl)
                ->setData([
                    'latlng' => sprintf('%.7F,%.7F', $lat, $lng),
                    'language' => 'es',
                    'key' => $apiKey,
                ])
                ->send();

            $data = $response->getData();
        } catch (\Throwable $e) {
            Yii::warning(
                'Google Geocoding falló: ' . $e->getMessage(),
                'geo.geocoding'
            );

            return null;
        }

        if (!is_array($data) || ($data['status'] ?? '') !== 'OK') {
            $status = is_array($data) ? (string) ($data['status'] ?? 'UNKNOWN') : 'INVALID_RESPONSE';
            if ($status !== 'ZERO_RESULTS') {
                Yii::warning('Google Geocoding status: ' . $status, 'geo.geocoding');
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

    private function apiKey(): string
    {
        $params = Yii::$app->params;

        return trim((string) ($params['google_maps_geocoding_api_key']
            ?? $params['google_cloud_api_key']
            ?? ''));
    }
}
