<?php

namespace common\components\Domain\Clinical\HistoryExchange\Infrastructure\External\Connector;

use common\components\Domain\Clinical\HistoryExchange\Infrastructure\External\Contract\ClinicalHistoryExchangeConnector;
use common\components\Domain\Clinical\HistoryExchange\Infrastructure\External\Contract\ClinicalHistorySubmissionStatusConnector;
use common\components\Domain\Clinical\HistoryExchange\Infrastructure\External\Dto\ClinicalHistoryExchangeSubmitResult;
use common\components\Domain\Clinical\HistoryExchange\Infrastructure\External\Dto\ClinicalHistoryExchangeStatusResult;
use common\components\Domain\Clinical\HistoryExchange\Infrastructure\External\Exception\ClinicalHistoryExchangeException;
use common\components\Shared\Infrastructure\Http\Auth\AccessTokenProvider;
use common\components\Shared\Infrastructure\Http\Auth\OAuthClientCredentialsTokenProvider;
use common\components\Shared\Infrastructure\Http\Auth\OAuthTokenException;
use common\components\Shared\Infrastructure\Http\HttpApiException;
use common\components\Shared\Infrastructure\Http\HttpResponse;
use common\components\Shared\Infrastructure\Http\JsonApiClient;
use common\models\Clinical\ClinicalHistoryOutboundJob;
use yii\base\Component;

/**
 * HTTP hacia servidor FHIR nacional / red jurisdiccional.
 *
 * Requiere `baseUrl`, `tokenUrl`, `clientId`, `clientSecret` y `submitPath` en params-local.
 * El path definitivo del contrato estatal se configura sin cambiar código de dominio.
 *
 * Transporte, reintentos, parseo y log vienen de `Shared/Infrastructure/Http`; el token, de
 * `OAuthClientCredentialsTokenProvider` (conserva la clave de caché histórica).
 */
final class HttpNationalClinicalHistoryConnector extends Component implements
    ClinicalHistoryExchangeConnector,
    ClinicalHistorySubmissionStatusConnector
{
    public string $connectorKey = 'nacional-fhir';

    public bool $enabled = false;

    public ?string $baseUrl = null;

    public ?string $tokenUrl = null;

    public ?string $clientId = null;

    public ?string $clientSecret = null;

    /** Ruta relativa POST Bundle — ajustar cuando el organismo publique el contrato */
    public string $submitPath = '/fhir/Bundle';

    /**
     * Ruta GET estado del envío; `{id}` = external_id o identificador del bundle.
     * Vacío = sin polling (reconcile no opera).
     */
    public ?string $statusPath = null;

    public int $timeoutSeconds = 120;

    /** @internal seam de test: permite un `Client` con transporte falso, sin red. */
    public ?JsonApiClient $httpClient = null;

    private ?AccessTokenProvider $tokenProvider = null;

    public function getConnectorKey(): string
    {
        return $this->connectorKey;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function submitEncounterBundle(
        ClinicalHistoryOutboundJob $job,
        string $bundleJson
    ): ClinicalHistoryExchangeSubmitResult {
        if (!$this->isEnabled()) {
            return ClinicalHistoryExchangeSubmitResult::skipped(
                'Conector nacional-fhir deshabilitado (enabled=false).'
            );
        }

        $this->assertConfigurado();
        $this->assertCredenciales();

        $context = ['job_id' => (int) $job->id, 'encounter_id' => (int) $job->encounter_id];

        try {
            $response = $this->send('POST', $this->submitPath, $bundleJson, $context);
        } catch (HttpApiException $e) {
            return ClinicalHistoryExchangeSubmitResult::failed(
                'HTTP ' . $e->statusCode() . ' al enviar Bundle FHIR',
                $e->isRetryable()
            );
        }

        $externalId = $this->resolveExternalId($response, $job);

        return ClinicalHistoryExchangeSubmitResult::sent(
            $externalId,
            'Bundle aceptado HTTP ' . $response->statusCode()
        );
    }

    public function pollSubmissionStatus(ClinicalHistoryOutboundJob $job): ClinicalHistoryExchangeStatusResult
    {
        if (!$this->isEnabled()) {
            return ClinicalHistoryExchangeStatusResult::unsupported();
        }

        $path = $this->statusPath;
        if ($path === null || trim($path) === '') {
            return ClinicalHistoryExchangeStatusResult::unsupported();
        }

        $this->assertConfigurado();
        $this->assertCredenciales();

        $lookupId = $this->resolveLookupId($job);
        $relative = str_replace('{id}', rawurlencode($lookupId), $path);
        $context = ['job_id' => (int) $job->id, 'encounter_id' => (int) $job->encounter_id];

        try {
            $response = $this->send('GET', $relative, null, $context);
        } catch (HttpApiException $e) {
            if ($e->statusCode() === 404) {
                return ClinicalHistoryExchangeStatusResult::notFound('Envío no encontrado HTTP 404');
            }

            return ClinicalHistoryExchangeStatusResult::notFound('Polling HTTP ' . $e->statusCode());
        }

        $data = $response->body();
        if ($data === []) {
            return ClinicalHistoryExchangeStatusResult::notFound('Respuesta de estado no JSON');
        }

        $externalId = (string) ($data['id'] ?? $lookupId);
        if ($externalId === '') {
            $externalId = $lookupId;
        }

        $remoteStatus = null;
        if (isset($data['status']) && is_string($data['status'])) {
            $remoteStatus = $data['status'];
        } elseif (isset($data['meta']['tag'][0]['code'])) {
            $remoteStatus = (string) $data['meta']['tag'][0]['code'];
        }

        return ClinicalHistoryExchangeStatusResult::found($externalId, $remoteStatus);
    }

    /**
     * Reintenta una vez tras invalidar el token: el servidor nacional responde `401` cuando
     * el token cacheado venció antes de su TTL declarado.
     *
     * @param array<string, scalar|null> $logContext
     *
     * @throws HttpApiException respuesta no-2xx
     * @throws OAuthTokenException no se pudo obtener el token
     */
    private function send(string $method, string $relativePath, ?string $body, array $logContext): HttpResponse
    {
        try {
            return $this->request($method, $relativePath, $body, $logContext);
        } catch (HttpApiException $e) {
            if ($e->statusCode() !== 401) {
                throw $e;
            }

            $this->tokenProvider()->invalidate();

            return $this->request($method, $relativePath, $body, $logContext);
        }
    }

    /** @param array<string, scalar|null> $logContext */
    private function request(string $method, string $relativePath, ?string $body, array $logContext): HttpResponse
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->tokenProvider()->token(),
            'Accept' => 'application/fhir+json, application/json',
        ];
        if ($body !== null) {
            $headers['Content-Type'] = 'application/fhir+json';
        }

        return $this->api()->request($method, $relativePath, $body, $headers, [], $logContext);
    }

    private function assertConfigurado(): void
    {
        if ($this->baseUrl === null || trim($this->baseUrl) === '') {
            throw new ClinicalHistoryExchangeException(
                'clinicalHistoryExchange: baseUrl requerido para nacional-fhir.'
            );
        }
    }

    private function assertCredenciales(): void
    {
        if ($this->clientId === null || $this->clientSecret === null
            || trim($this->clientId) === '' || trim($this->clientSecret) === '') {
            throw new ClinicalHistoryExchangeException(
                'clinicalHistoryExchange: clientId/clientSecret requeridos para nacional-fhir.'
            );
        }
    }

    private function resolveLookupId(ClinicalHistoryOutboundJob $job): string
    {
        $externalId = trim((string) ($job->external_id ?? ''));
        if ($externalId !== '' && !str_starts_with($externalId, 'bioenlace-job-')) {
            return $externalId;
        }

        return 'bioenlace-encounter-' . (int) $job->encounter_id;
    }

    private function resolveExternalId(HttpResponse $response, ClinicalHistoryOutboundJob $job): string
    {
        $location = $response->header('Location');
        if (is_string($location) && $location !== '') {
            if (preg_match('#/([A-Za-z0-9\-\._]+)$#', $location, $m)) {
                return $m[1];
            }

            return $location;
        }

        $data = $response->body();
        if (!empty($data['id'])) {
            return (string) $data['id'];
        }
        if (!empty($data['identifier']['value'])) {
            return (string) $data['identifier']['value'];
        }

        return 'bioenlace-job-' . (int) $job->id;
    }

    private function tokenProvider(): AccessTokenProvider
    {
        if ($this->tokenProvider === null) {
            $this->tokenProvider = new OAuthClientCredentialsTokenProvider(
                client: $this->api(),
                tokenPath: trim((string) $this->tokenUrl) !== ''
                    ? (string) $this->tokenUrl
                    : rtrim((string) $this->baseUrl, '/') . '/oauth/token',
                clientId: (string) $this->clientId,
                clientSecret: (string) $this->clientSecret,
                // Clave histórica: no invalidar tokens vigentes al desplegar.
                cacheKey: 'clinical_history_exchange_token_' . md5($this->connectorKey . (string) $this->clientId)
            );
        }

        return $this->tokenProvider;
    }

    private function api(): JsonApiClient
    {
        return $this->httpClient ??= new JsonApiClient(
            baseUrl: (string) $this->baseUrl,
            connectTimeout: 10,
            timeout: (float) $this->timeoutSeconds,
            logCategory: 'clinical-history-exchange',
            provider: $this->connectorKey,
            providerLabel: 'Historia clínica nacional'
        );
    }
}
