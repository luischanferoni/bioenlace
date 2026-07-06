<?php

namespace common\components\Domain\Integrations\Scheduling\Connector;

use common\components\Domain\Integrations\Scheduling\Contract\FhirSchedulingInboundConnector;
use common\components\Domain\Integrations\Scheduling\Exception\FhirSchedulingConnectorException;
use Yii;
use yii\base\Component;
use yii\httpclient\Client;

/**
 * HAPI FHIR R4 — NIS MSAL ({@see https://nis.msalsgo.gob.ar/fhir}).
 */
class MsalNisFhirSchedulingConnector extends Component implements FhirSchedulingInboundConnector
{
    public string $connectorKey = 'msal-nis';

    /** Base FHIR, ej. https://nis.msalsgo.gob.ar/fhir */
    public string $baseUrl = 'https://nis.msalsgo.gob.ar/fhir';

    public ?string $tokenUrl = null;

    public ?string $clientId = null;

    public ?string $clientSecret = null;

    public int $timeoutSeconds = 30;

    private ?string $accessToken = null;

    public function getConnectorKey(): string
    {
        return $this->connectorKey;
    }

    public function searchAppointments(array $params = []): array
    {
        return $this->request('GET', 'Appointment', $params);
    }

    public function searchSchedules(array $params = []): array
    {
        return $this->request('GET', 'Schedule', $params);
    }

    public function readSchedule(string $id, array $includes = []): array
    {
        $params = [];
        if ($includes !== []) {
            $params['_include'] = implode(',', $includes);
        }

        return $this->request('GET', 'Schedule/' . rawurlencode($id), $params);
    }

    public function readAppointment(string $id, array $includes = []): array
    {
        $params = [];
        if ($includes !== []) {
            $params['_include'] = implode(',', $includes);
        }

        return $this->request('GET', 'Appointment/' . rawurlencode($id), $params);
    }

    public function readResource(string $resourceType, string $id): array
    {
        return $this->request('GET', rawurlencode($resourceType) . '/' . rawurlencode($id));
    }

    /**
     * @param array<string, scalar|null> $query
     * @return array<string, mixed>
     */
    private function request(string $method, string $relativePath, array $query = []): array
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($relativePath, '/');
        if ($query !== []) {
            $url .= '?' . http_build_query(array_filter($query, static fn ($v) => $v !== null && $v !== ''));
        }

        $client = new Client(['transport' => 'yii\httpclient\CurlTransport']);
        $request = $client->createRequest()
            ->setMethod($method)
            ->setUrl($url)
            ->addHeaders([
                'Accept' => 'application/fhir+json, application/json',
            ])
            ->setOptions(['timeout' => $this->timeoutSeconds]);

        if ($this->usesOAuth()) {
            $request->addHeaders(['Authorization' => 'Bearer ' . $this->getAccessToken()]);
        }

        $response = $request->send();
        if (!$response->isOk) {
            Yii::error([
                'connector' => $this->connectorKey,
                'url' => $url,
                'status' => $response->statusCode,
                'body' => $response->content,
            ], 'fhir-scheduling-inbound');

            throw new FhirSchedulingConnectorException(
                "NIS FHIR HTTP {$response->statusCode} en {$relativePath}"
            );
        }

        $data = $response->data;
        if (!is_array($data)) {
            throw new FhirSchedulingConnectorException('Respuesta FHIR no es JSON válido.');
        }

        return $data;
    }

    private function usesOAuth(): bool
    {
        return $this->tokenUrl !== null && $this->tokenUrl !== ''
            && $this->clientId !== null && $this->clientId !== ''
            && $this->clientSecret !== null && $this->clientSecret !== '';
    }

    private function getAccessToken(): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $client = new Client(['transport' => 'yii\httpclient\CurlTransport']);
        $response = $client->createRequest()
            ->setMethod('POST')
            ->setUrl((string) $this->tokenUrl)
            ->setData([
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ])
            ->send();

        if (!$response->isOk || !is_array($response->data) || empty($response->data['access_token'])) {
            throw new FhirSchedulingConnectorException('No se pudo obtener token OAuth para NIS FHIR.');
        }

        $this->accessToken = (string) $response->data['access_token'];

        return $this->accessToken;
    }
}
