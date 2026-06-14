<?php

namespace common\components\Domain\Integrations\Laboratory\Connector;

use common\components\Domain\Integrations\Laboratory\Contract\FhirLabResultsConnector;
use common\components\Domain\Integrations\Laboratory\Exception\LaboratoryConnectorException;
use Yii;
use yii\base\Component;
use yii\httpclient\Client;

/**
 * LIS Sianlabs — FHIR R4 (OAuth2 client credentials).
 */
class SianlabsFhirConnector extends Component implements FhirLabResultsConnector
{
    public string $connectorKey = 'sianlabs';

    public string $baseUrl = 'https://sianlabs.msalsgo.gob.ar/api/fhir/';

    public ?string $tokenUrl = 'https://sianlabs.msalsgo.gob.ar/oauth/token';

    public ?string $clientId = null;

    public ?string $clientSecret = null;

    /** @var string|null token en caché de request */
    private ?string $accessToken = null;

    public function getConnectorKey(): string
    {
        return $this->connectorKey;
    }

    public function resolvePatientFhirId(string $documentNumber): ?string
    {
        $bundle = $this->request('GET', 'Patient?identifier=' . rawurlencode($documentNumber));
        return $this->extractFirstResourceId($bundle, 'Patient');
    }

    public function fetchDiagnosticReports(string $patientFhirId): array
    {
        return $this->request('GET', 'DiagnosticReport?patient=' . rawurlencode($patientFhirId));
    }

    /**
     * @return array<string, mixed>
     */
    private function request(string $method, string $relativePath): array
    {
        if ($this->clientId === null || $this->clientSecret === null || $this->clientId === '' || $this->clientSecret === '') {
            throw new LaboratoryConnectorException('Sianlabs: clientId/clientSecret no configurados en params-local.');
        }

        $token = $this->getAccessToken();
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($relativePath, '/');

        $client = new Client(['transport' => 'yii\httpclient\CurlTransport']);
        $response = $client->createRequest()
            ->setMethod($method)
            ->setUrl($url)
            ->addHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/fhir+json, application/json',
            ])
            ->send();

        if (!$response->isOk) {
            Yii::error([
                'connector' => $this->connectorKey,
                'url' => $url,
                'status' => $response->statusCode,
                'body' => $response->content,
            ], 'laboratory-fhir');

            throw new LaboratoryConnectorException(
                "Sianlabs HTTP {$response->statusCode} en {$relativePath}"
            );
        }

        $data = $response->data;
        if (!is_array($data)) {
            return [];
        }

        return $data;
    }

    private function getAccessToken(): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $tokenUrl = $this->tokenUrl ?? rtrim($this->baseUrl, '/') . '/../oauth/token';
        $client = new Client(['transport' => 'yii\httpclient\CurlTransport']);
        $response = $client->createRequest()
            ->setMethod('POST')
            ->setUrl($tokenUrl)
            ->setData([
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ])
            ->send();

        if (!$response->isOk || empty($response->data['access_token'])) {
            throw new LaboratoryConnectorException('Sianlabs: no se pudo obtener access_token.');
        }

        $this->accessToken = (string) $response->data['access_token'];

        return $this->accessToken;
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
