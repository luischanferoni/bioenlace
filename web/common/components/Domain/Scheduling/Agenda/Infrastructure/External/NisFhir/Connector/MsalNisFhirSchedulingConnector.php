<?php

namespace common\components\Domain\Scheduling\Agenda\Infrastructure\External\NisFhir\Connector;

use common\components\Domain\Scheduling\Agenda\Infrastructure\External\NisFhir\Contract\FhirSchedulingInboundConnector;
use common\components\Domain\Scheduling\Agenda\Infrastructure\External\NisFhir\Exception\FhirSchedulingConnectorException;
use common\components\Domain\Scheduling\Agenda\Infrastructure\External\NisFhir\Mapper\FhirBundleMapper;
use common\components\Shared\Infrastructure\Http\Auth\AccessTokenProvider;
use common\components\Shared\Infrastructure\Http\Auth\OAuthClientCredentialsTokenProvider;
use common\components\Shared\Infrastructure\Http\Auth\OAuthTokenException;
use common\components\Shared\Infrastructure\Http\HttpApiException;
use common\components\Shared\Infrastructure\Http\JsonApiClient;
use yii\base\Component;

/**
 * HAPI FHIR R4 — NIS MSAL ({@see https://nis.msalsgo.gob.ar/fhir}).
 *
 * Con `tokenUrl` + `clientId` + `clientSecret` agrega `Authorization: Bearer`; sin ellos
 * opera contra un FHIR abierto (modo histórico que el registry usa en desarrollo).
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

    /** @internal seam de test: permite un `Client` con transporte falso, sin red. */
    public ?JsonApiClient $httpClient = null;

    private ?AccessTokenProvider $tokenProvider = null;

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
        return $this->request('GET', 'Schedule/' . rawurlencode($id), $this->includeQuery($includes));
    }

    public function readAppointment(string $id, array $includes = []): array
    {
        return $this->request('GET', 'Appointment/' . rawurlencode($id), $this->includeQuery($includes));
    }

    public function readResource(string $resourceType, string $id): array
    {
        return $this->request('GET', rawurlencode($resourceType) . '/' . rawurlencode($id));
    }

    public function updateAppointmentStatus(string $appointmentId, string $fhirStatus): array
    {
        $appointmentId = trim($appointmentId);
        if ($appointmentId === '') {
            throw new FhirSchedulingConnectorException('Appointment id vacío para actualización saliente.');
        }

        $raw = $this->readAppointment($appointmentId);
        $resources = FhirBundleMapper::collectResources($raw, 'Appointment');
        $appointment = $resources[0] ?? null;
        if ($appointment === null || ($appointment['resourceType'] ?? '') !== 'Appointment') {
            throw new FhirSchedulingConnectorException("Appointment {$appointmentId} no encontrado en NIS FHIR.");
        }

        $appointment['status'] = strtolower(trim($fhirStatus));

        return $this->request(
            'PUT',
            'Appointment/' . rawurlencode($appointmentId),
            [],
            $appointment
        );
    }

    /**
     * @param array<string, scalar|null> $query
     * @param array<string, mixed>|null $jsonBody
     * @return array<string, mixed>
     *
     * @throws FhirSchedulingConnectorException respuesta no-2xx o cuerpo no-FHIR
     * @throws OAuthTokenException no se pudo obtener el token
     */
    private function request(string $method, string $relativePath, array $query = [], ?array $jsonBody = null): array
    {
        $headers = ['Accept' => 'application/fhir+json, application/json'];
        $body = null;

        if ($this->usesOAuth()) {
            $headers['Authorization'] = 'Bearer ' . $this->tokenProvider()->token();
        }
        if ($jsonBody !== null) {
            $headers['Content-Type'] = 'application/fhir+json';
            $body = $jsonBody;
        }

        try {
            $response = $this->api()->request(
                $method,
                $relativePath,
                $body,
                $headers,
                array_filter($query, static fn ($v) => $v !== null && $v !== '')
            );
        } catch (HttpApiException $e) {
            throw new FhirSchedulingConnectorException(
                'NIS FHIR HTTP ' . $e->statusCode() . ' en ' . $relativePath,
                0,
                $e
            );
        }

        return $response->body();
    }

    /** @param list<string> $includes */
    private function includeQuery(array $includes): array
    {
        return $includes === [] ? [] : ['_include' => implode(',', $includes)];
    }

    private function usesOAuth(): bool
    {
        return $this->tokenUrl !== null && trim($this->tokenUrl) !== ''
            && $this->clientId !== null && trim($this->clientId) !== ''
            && $this->clientSecret !== null && $this->clientSecret !== '';
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
        return $this->httpClient ??= new JsonApiClient(
            baseUrl: $this->baseUrl,
            connectTimeout: 5,
            timeout: (float) $this->timeoutSeconds,
            logCategory: 'fhir-scheduling-inbound',
            provider: $this->connectorKey,
            providerLabel: 'NIS FHIR'
        );
    }
}
