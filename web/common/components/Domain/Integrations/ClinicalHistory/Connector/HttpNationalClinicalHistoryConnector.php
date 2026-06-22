<?php

namespace common\components\Domain\Integrations\ClinicalHistory\Connector;

use common\components\Domain\Integrations\ClinicalHistory\Contract\ClinicalHistoryExchangeConnector;
use common\components\Domain\Integrations\ClinicalHistory\Dto\ClinicalHistoryExchangeSubmitResult;
use common\components\Domain\Integrations\ClinicalHistory\Exception\ClinicalHistoryExchangeException;
use common\models\Clinical\ClinicalHistoryOutboundJob;
use Yii;
use yii\httpclient\Client;

/**
 * HTTP hacia servidor FHIR nacional / red jurisdiccional.
 *
 * Requiere `baseUrl`, `tokenUrl`, `clientId`, `clientSecret` y `submitPath` en params-local.
 * El path definitivo del contrato estatal se configura sin cambiar código de dominio.
 */
final class HttpNationalClinicalHistoryConnector implements ClinicalHistoryExchangeConnector
{
    public string $connectorKey = 'nacional-fhir';

    public bool $enabled = false;

    public ?string $baseUrl = null;

    public ?string $tokenUrl = null;

    public ?string $clientId = null;

    public ?string $clientSecret = null;

    /** Ruta relativa POST Bundle — ajustar cuando el organismo publique el contrato */
    public string $submitPath = '/fhir/Bundle';

    public int $timeoutSeconds = 120;

    private ?string $accessToken = null;

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

        if ($this->baseUrl === null || trim($this->baseUrl) === '') {
            throw new ClinicalHistoryExchangeException(
                'clinicalHistoryExchange: baseUrl requerido para nacional-fhir.'
            );
        }

        if ($this->clientId === null || $this->clientSecret === null
            || trim((string) $this->clientId) === '' || trim((string) $this->clientSecret) === '') {
            throw new ClinicalHistoryExchangeException(
                'clinicalHistoryExchange: clientId/clientSecret requeridos para nacional-fhir.'
            );
        }

        $token = $this->getAccessToken();
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($this->submitPath, '/');

        $client = new Client(['transport' => 'yii\httpclient\CurlTransport']);
        $response = $client->createRequest()
            ->setMethod('POST')
            ->setUrl($url)
            ->addHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/fhir+json',
                'Accept' => 'application/fhir+json, application/json',
            ])
            ->setContent($bundleJson)
            ->setOptions([CURLOPT_TIMEOUT => $this->timeoutSeconds])
            ->send();

        $status = (int) $response->statusCode;
        $body = (string) $response->content;

        if ($status >= 200 && $status < 300) {
            $externalId = $this->resolveExternalId($response->headers, $body, $job);

            return ClinicalHistoryExchangeSubmitResult::sent(
                $externalId,
                'Bundle aceptado HTTP ' . $status
            );
        }

        $retryable = $status >= 500 || $status === 429 || $status === 0;
        if ($status >= 400 && $status < 500 && $status !== 429) {
            $retryable = false;
        }

        Yii::error([
            'connector' => $this->connectorKey,
            'job_id' => (int) $job->id,
            'encounter_id' => (int) $job->encounter_id,
            'status' => $status,
            'body' => mb_substr($body, 0, 2000),
        ], 'clinical-history-exchange');

        return ClinicalHistoryExchangeSubmitResult::failed(
            'HTTP ' . $status . ' al enviar Bundle FHIR',
            $retryable
        );
    }

  /**
   * @param \yii\httpclient\HeaderCollection $headers
   */
    private function resolveExternalId($headers, string $body, ClinicalHistoryOutboundJob $job): string
    {
        $location = $headers->get('Location') ?? $headers->get('location');
        if (is_string($location) && $location !== '') {
            if (preg_match('#/([A-Za-z0-9\-\._]+)$#', $location, $m)) {
                return $m[1];
            }

            return $location;
        }

        $data = json_decode($body, true);
        if (is_array($data)) {
            if (!empty($data['id'])) {
                return (string) $data['id'];
            }
            if (!empty($data['identifier']['value'])) {
                return (string) $data['identifier']['value'];
            }
        }

        return 'bioenlace-job-' . (int) $job->id;
    }

    private function getAccessToken(): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $cacheKey = 'clinical_history_exchange_token_' . md5($this->connectorKey . (string) $this->clientId);
        if (Yii::$app->cache !== null) {
            $cached = Yii::$app->cache->get($cacheKey);
            if (is_string($cached) && $cached !== '') {
                $this->accessToken = $cached;

                return $cached;
            }
        }

        $tokenUrl = $this->tokenUrl ?? rtrim((string) $this->baseUrl, '/') . '/oauth/token';
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

        if (!$response->isOk) {
            throw new ClinicalHistoryExchangeException(
                'OAuth nacional-fhir falló HTTP ' . $response->statusCode
            );
        }

        $data = $response->data;
        $token = is_array($data) ? (string) ($data['access_token'] ?? '') : '';
        if ($token === '') {
            throw new ClinicalHistoryExchangeException('OAuth nacional-fhir: access_token vacío.');
        }

        $ttl = is_array($data) ? (int) ($data['expires_in'] ?? 3000) : 3000;
        if (Yii::$app->cache !== null) {
            Yii::$app->cache->set($cacheKey, $token, max(60, $ttl - 60));
        }

        $this->accessToken = $token;

        return $token;
    }
}
