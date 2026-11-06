<?php

namespace common\components\Shared\Infrastructure\Http;

use Yii;
use yii\httpclient\Client;
use yii\httpclient\CurlTransport;
use yii\httpclient\Request;

/**
 * Único transporte HTTP del repo: timeouts, reintentos, parseo, mapeo de error y log.
 *
 * No conoce payloads de proveedores ni cachea respuestas. El ACL de cada sistema externo
 * (`Domain/<BC>/[<Modulo>/]Infrastructure/External/<Sistema>/`) lo instancia y aporta
 * base URL, headers y, si hace falta, su `extractProviderError()`.
 */
class JsonApiClient
{
    private const LOG_TRUNCATE = 2000;

    private ?Client $httpClient;

    /**
     * @param array<string, string> $defaultHeaders
     * @param string $provider slug para logs y excepciones
     * @param string|null $providerLabel texto que ve el usuario; por defecto el slug
     * @param Client|null $httpClient inyectable para testear sin red
     */
    public function __construct(
        private readonly string $baseUrl,
        private readonly array $defaultHeaders = [],
        private readonly float $connectTimeout = 5,
        private readonly float $timeout = 20,
        private readonly string $logCategory = 'http',
        private readonly ?RetryPolicy $retry = null,
        private readonly string $provider = 'http',
        private readonly ?string $providerLabel = null,
        ?Client $httpClient = null
    ) {
        $this->httpClient = $httpClient;
    }

    /**
     * @param mixed $body null, array (JSON, o form si el `Content-Type` lo dice) o string crudo
     * @param array<string, string> $headers
     * @param array<string, scalar|null> $query se agrega a la URL
     * @param array<string, scalar|null> $logContext datos del ACL para el log de error (job_id, …)
     */
    public function request(
        string $method,
        string $path,
        mixed $body = null,
        array $headers = [],
        array $query = [],
        array $logContext = []
    ): HttpResponse {
        $method = strtoupper($method);
        if ($query !== []) {
            $path .= (str_contains($path, '?') ? '&' : '?') . http_build_query($query);
        }
        $headers = $this->mergeHeaders($method, $headers, $body);
        $retry = $this->retry ?? new RetryPolicy();
        $hasIdempotencyKey = isset($headers['idempotency-key']) || isset($headers['x-idempotency-key']);

        $attempt = 0;
        while (true) {
            $attempt++;

            try {
                return $this->sendOnce($method, $path, $body, $headers, $logContext);
            } catch (HttpApiException $e) {
                if (!$retry->shouldRetry($method, $hasIdempotencyKey, $e, $attempt)) {
                    throw $e;
                }
                $retry->waitBeforeRetry($attempt);
            }
        }
    }

    /**
     * @param array<string, scalar|null> $query
     * @param array<string, string> $headers
     */
    public function get(string $path, array $query = [], array $headers = []): HttpResponse
    {
        return $this->request('GET', $path, null, $headers, $query);
    }

    /** @param array<string, string> $headers */
    public function post(string $path, mixed $body = null, array $headers = []): HttpResponse
    {
        return $this->request('POST', $path, $body, $headers);
    }

    /** @param array<string, string> $headers */
    public function put(string $path, mixed $body = null, array $headers = []): HttpResponse
    {
        return $this->request('PUT', $path, $body, $headers);
    }

    /** @param array<string, string> $headers */
    public function delete(string $path, mixed $body = null, array $headers = []): HttpResponse
    {
        return $this->request('DELETE', $path, $body, $headers);
    }

    /**
     * @param array<string, scalar> $data
     * @param array<string, string> $headers
     */
    public function postForm(string $path, array $data, array $headers = []): HttpResponse
    {
        return $this->request(
            'POST',
            $path,
            $data,
            $headers + ['Content-Type' => 'application/x-www-form-urlencoded']
        );
    }

    /**
     * Forma de Mercado Pago: `message` | `error` + `cause[]` con `code: description`.
     *
     * @param array<mixed> $body
     * @return array{message: string, causes: list<string>}
     */
    protected function extractProviderError(array $body): array
    {
        $message = trim((string) ($body['message'] ?? $body['error'] ?? ''));
        $causes = [];

        if (is_array($body['cause'] ?? null)) {
            foreach ($body['cause'] as $cause) {
                if (is_array($cause)) {
                    $code = trim((string) ($cause['code'] ?? ''));
                    $description = trim((string) ($cause['description'] ?? ''));
                    $causes[] = trim($code . ': ' . $description, ': ');
                } elseif (is_scalar($cause)) {
                    $causes[] = (string) $cause;
                }
            }
        }

        return ['message' => $message, 'causes' => $causes];
    }

    private function sendOnce(string $method, string $path, mixed $body, array $headers, array $logContext): HttpResponse
    {
        $request = $this->client()
            ->createRequest()
            ->setMethod($method)
            ->setUrl($this->url($path));
        $request->setHeaders($headers);
        $request->setOptions([
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT => $this->timeout,
        ]);

        if ($body !== null) {
            $this->applyBody($request, $method, $body, $headers);
        }

        try {
            $response = $request->send();
            $statusCode = (int) $response->getStatusCode();
            $rawBody = (string) $response->getContent();
        } catch (\yii\httpclient\Exception $e) {
            $exception = new HttpApiException(
                $this->provider,
                0,
                $this->label() . ': no se pudo conectar.',
                [],
                $e
            );
            $this->logError($method, $path, $exception, $logContext, $e->getMessage());

            throw $exception;
        }

        $decoded = json_decode($rawBody, true);
        $httpResponse = new HttpResponse(
            $statusCode,
            $this->normalizeHeaders($response->getHeaders()->toArray()),
            is_array($decoded) ? $decoded : [],
            $rawBody
        );

        if ($httpResponse->isOk()) {
            return $httpResponse;
        }

        $providerError = $this->extractProviderError($httpResponse->body());
        $exception = new HttpApiException(
            $this->provider,
            $statusCode,
            $this->errorMessage($statusCode, $providerError),
            $providerError['causes']
        );
        $this->logError($method, $path, $exception, $logContext, null, $rawBody);

        throw $exception;
    }

    /**
     * El formato lo decide el `Content-Type`, no un flag extra: array → JSON (o form si el
     * header lo pide), string → contenido crudo tal cual (FHIR).
     *
     * El JSON lo serializamos nosotros a propósito: el formatter de Yii pisa el
     * `Content-Type` con `application/json; charset=UTF-8` y un FHIR necesita mandar
     * `application/fhir+json` tal cual.
     *
     * @param array<string, string> $headers
     */
    private function applyBody(Request $request, string $method, mixed $body, array $headers): void
    {
        if ($method === 'GET' || $method === 'HEAD') {
            $request->setData($body);
            $request->setFormat(Client::FORMAT_URLENCODED);

            return;
        }

        if (is_string($body)) {
            $request->setContent($body);

            return;
        }

        if (str_contains($headers['content-type'] ?? '', 'x-www-form-urlencoded')) {
            $request->setData($body);
            $request->setFormat(Client::FORMAT_URLENCODED);

            return;
        }

        $encoded = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new \InvalidArgumentException('No se pudo serializar el body de la request a JSON.');
        }
        $request->setContent($encoded);
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private function mergeHeaders(string $method, array $headers, mixed $body): array
    {
        $merged = [];
        foreach ($this->defaultHeaders as $name => $value) {
            $merged[strtolower($name)] = $value;
        }
        foreach ($headers as $name => $value) {
            $merged[strtolower($name)] = $value;
        }

        $merged['accept'] ??= 'application/json';
        if ($body !== null && $method !== 'GET' && $method !== 'HEAD') {
            $merged['content-type'] ??= 'application/json';
        }

        return $merged;
    }

    /**
     * @param array<string, mixed> $headers
     * @return array<string, string>
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $name => $value) {
            $normalized[strtolower((string) $name)] = is_array($value)
                ? implode(', ', array_map('strval', $value))
                : (string) $value;
        }

        return $normalized;
    }

    private function url(string $path): string
    {
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        return rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
    }

    private function client(): Client
    {
        return $this->httpClient ??= new Client(['transport' => CurlTransport::class]);
    }

    private function label(): string
    {
        return $this->providerLabel ?? $this->provider;
    }

    /** @param array{message: string, causes: list<string>} $providerError */
    private function errorMessage(int $statusCode, array $providerError): string
    {
        $message = $this->label() . ' rechazó la operación (HTTP ' . $statusCode . ')';
        if ($providerError['message'] !== '') {
            $message .= ': ' . $providerError['message'];
        }
        if ($providerError['causes'] !== []) {
            $message .= ' [' . implode('; ', $providerError['causes']) . ']';
        }

        return $message;
    }

    /**
     * Una entrada por request fallida. Nunca incluye headers ni cuerpo de la request:
     * `Authorization` y los datos personales no viajan al log.
     */
    private function logError(
        string $method,
        string $path,
        HttpApiException $e,
        array $logContext = [],
        ?string $transportError = null,
        ?string $responseBody = null
    ): void {
        $context = $logContext + [
            'event' => 'http.api_error',
            'provider' => $e->provider(),
            'method' => $method,
            'path' => $path,
            'status_code' => $e->statusCode(),
            'provider_message' => $e->getMessage(),
            'causes' => $e->providerCauses(),
        ];
        if ($transportError !== null) {
            $context['error'] = $transportError;
        }
        if ($responseBody !== null) {
            $context['response'] = mb_substr($responseBody, 0, self::LOG_TRUNCATE);
        }

        Yii::error($context, $this->logCategory);
    }
}
