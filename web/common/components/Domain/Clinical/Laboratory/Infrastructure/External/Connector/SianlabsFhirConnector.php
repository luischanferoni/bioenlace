<?php

namespace common\components\Domain\Clinical\Laboratory\Infrastructure\External\Connector;

use common\components\Domain\Clinical\Laboratory\Infrastructure\External\Contract\FhirLabResultsConnector;
use common\components\Domain\Clinical\Laboratory\Infrastructure\External\Exception\LaboratoryConnectorException;
use common\components\Shared\Infrastructure\Http\Auth\AccessTokenProvider;
use common\components\Shared\Infrastructure\Http\Auth\OAuthClientCredentialsTokenProvider;
use common\components\Shared\Infrastructure\Http\Auth\OAuthTokenException;
use common\components\Shared\Infrastructure\Http\HttpApiException;
use common\components\Shared\Infrastructure\Http\JsonApiClient;
use yii\base\Component;

/**
 * LIS Sianlabs — FHIR R4 (OAuth2 client credentials).
 *
 * Transporte, reintentos y token los aporta `Shared/Infrastructure/Http`; acá sólo viven
 * los endpoints y el mapeo de error del conector.
 */
class SianlabsFhirConnector extends Component implements FhirLabResultsConnector
{
    public string $connectorKey = 'sianlabs';

    public string $baseUrl = 'https://sianlabs.msalsgo.gob.ar/api/fhir/';

    public ?string $tokenUrl = 'https://sianlabs.msalsgo.gob.ar/oauth/token';

    public ?string $clientId = null;

    public ?string $clientSecret = null;

    public int $timeoutSeconds = 20;

    /** @internal seam de test: permite un `Client` con transporte falso, sin red. */
    public ?JsonApiClient $httpClient = null;

    private ?AccessTokenProvider $tokenProvider = null;

    public function getConnectorKey(): string
    {
        return $this->connectorKey;
    }

    public function resolvePatientFhirId(string $documentNumber): ?string
    {
        $bundle = $this->read('Patient', ['identifier' => $documentNumber]);

        return $this->extractFirstResourceId($bundle, 'Patient');
    }

    /** @return array<string, mixed> */
    public function fetchDiagnosticReports(string $patientFhirId): array
    {
        return $this->read('DiagnosticReport', ['patient' => $patientFhirId]);
    }

    /**
     * @param array<string, string> $query
     * @return array<string, mixed>
     *
     * @throws LaboratoryConnectorException respuesta no-2xx del LIS
     * @throws OAuthTokenException no se pudo obtener el token
     */
    private function read(string $path, array $query = []): array
    {
        $this->assertCredentials();

        try {
            $response = $this->api()->get($path, $query, $this->authHeaders());
        } catch (HttpApiException $e) {
            throw new LaboratoryConnectorException(
                'Sianlabs HTTP ' . $e->statusCode() . ' en ' . $path,
                0,
                $e
            );
        }

        return $response->body();
    }

    private function assertCredentials(): void
    {
        if ($this->clientId === null || $this->clientSecret === null
            || trim($this->clientId) === '' || trim($this->clientSecret) === '') {
            throw new LaboratoryConnectorException('Sianlabs: clientId/clientSecret no configurados en params-local.');
        }
    }

    /** @return array<string, string> */
    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->tokenProvider()->token()];
    }

    private function tokenProvider(): AccessTokenProvider
    {
        if ($this->tokenProvider === null) {
            $this->tokenProvider = new OAuthClientCredentialsTokenProvider(
                client: $this->api(),
                tokenPath: (string) $this->tokenUrl,
                clientId: (string) $this->clientId,
                clientSecret: (string) $this->clientSecret,
                cacheKeyPrefix: 'oauth_token_' . $this->connectorKey
            );
        }

        return $this->tokenProvider;
    }

    private function api(): JsonApiClient
    {
        return $this->httpClient ??= $this->unauthenticatedClient();
    }

    private function unauthenticatedClient(): JsonApiClient
    {
        return new JsonApiClient(
            baseUrl: $this->baseUrl,
            defaultHeaders: ['Accept' => 'application/fhir+json, application/json'],
            connectTimeout: 5,
            timeout: (float) $this->timeoutSeconds,
            logCategory: 'laboratory-fhir',
            provider: $this->connectorKey,
            providerLabel: 'Sianlabs'
        );
    }

    /**
     * @param array<string, mixed> $bundle
     */
    private function extractFirstResourceId(array $bundle, string $resourceType): ?string
    {
        $entries = $bundle['entry'] ?? [];
        if (!is_array($entries)) {
            if (($bundle['resourceType'] ?? '') === $resourceType && isset($bundle['id'])) {
                return (string) $bundle['id'];
            }

            return null;
        }

        foreach ($entries as $entry) {
            $res = $entry['resource'] ?? null;
            if (!is_array($res)) {
                continue;
            }
            if (($res['resourceType'] ?? '') === $resourceType && !empty($res['id'])) {
                return (string) $res['id'];
            }
        }

        return null;
    }
}
