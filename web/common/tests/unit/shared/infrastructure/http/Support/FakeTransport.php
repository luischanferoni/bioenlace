<?php

namespace common\tests\unit\shared\infrastructure\http\Support;

use yii\httpclient\Exception;
use yii\httpclient\Request;
use yii\httpclient\Response;
use yii\httpclient\Transport;

/**
 * Transporte falso para testear {@see \common\components\Shared\Infrastructure\Http\JsonApiClient}
 * sin red. Encola respuestas o excepciones y guarda lo que efectivamente se envió.
 */
final class FakeTransport extends Transport
{
    /** @var list<array{status: int, body: string, headers: array<string, string>}|\Throwable> */
    private array $queue = [];

    /** @var list<array{method: string, url: string, headers: array<string, string>, content: ?string}> */
    public array $sent = [];

    /** @param array<string, string> $headers */
    public function respond(int $status, string $body = '', array $headers = []): self
    {
        $this->queue[] = ['status' => $status, 'body' => $body, 'headers' => $headers];

        return $this;
    }

    public function fail(string $message = 'cURL error 28: Operation timed out'): self
    {
        $this->queue[] = new Exception($message);

        return $this;
    }

    /** @return list<array{method: string, url: string, headers: array<string, string>, content: ?string}> */
    public function sent(): array
    {
        return $this->sent;
    }

    /** @param Request $request */
    public function send($request)
    {
        $request->prepare();

        $headers = [];
        foreach ($request->getHeaders()->toArray() as $name => $values) {
            $headers[strtolower((string) $name)] = implode(', ', array_map('strval', (array) $values));
        }

        $this->sent[] = [
            'method' => $request->getMethod(),
            'url' => $request->getFullUrl(),
            'headers' => $headers,
            'content' => $request->getContent(),
        ];

        $next = array_shift($this->queue);
        if ($next === null) {
            throw new Exception('FakeTransport: no hay respuesta encolada.');
        }
        if ($next instanceof \Throwable) {
            throw $next;
        }

        $headers = ['HTTP/1.1 ' . $next['status'] . ' Fake'];
        foreach ($next['headers'] as $name => $value) {
            $headers[$name] = $value;
        }

        $response = new Response();
        $response->client = $request->client;
        $response->setHeaders($headers);
        $response->setContent($next['body']);

        return $response;
    }
}
