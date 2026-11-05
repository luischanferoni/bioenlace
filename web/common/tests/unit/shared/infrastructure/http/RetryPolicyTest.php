<?php

namespace common\tests\unit\shared\infrastructure\http;

use Codeception\Test\Unit;
use common\components\Shared\Infrastructure\Http\HttpApiException;
use common\components\Shared\Infrastructure\Http\RetryPolicy;

/**
 * Regla de reintento: idempotente o con clave, nunca un POST pelado.
 *
 * @see RetryPolicy
 */
class RetryPolicyTest extends Unit
{
    public function testPostSinClaveNoSeReintentaAunqueSea500(): void
    {
        $policy = new RetryPolicy(2, 0, 0);

        $this->assertFalse($policy->shouldRetry('POST', false, $this->error(500), 1));
    }

    public function testPostConClaveSeReintenta(): void
    {
        $policy = new RetryPolicy(2, 0, 0);

        $this->assertTrue($policy->shouldRetry('POST', true, $this->error(500), 1));
        $this->assertTrue($policy->shouldRetry('POST', true, $this->error(429), 2));
        $this->assertFalse($policy->shouldRetry('POST', true, $this->error(500), 3), 'Agotó los reintentos.');
    }

    public function testMetodosIdempotentesSeReintentan(): void
    {
        $policy = new RetryPolicy(2, 0, 0);

        foreach (['GET', 'HEAD', 'PUT', 'DELETE'] as $method) {
            $this->assertTrue($policy->shouldRetry($method, false, $this->error(503), 1), $method);
        }
    }

    public function testErroresNoReintentablesCortanElLoop(): void
    {
        $policy = new RetryPolicy(2, 0, 0);

        foreach ([400, 401, 402, 404, 422] as $status) {
            $this->assertFalse($policy->shouldRetry('GET', false, $this->error($status), 1), (string) $status);
        }
    }

    public function testBackoffCreceYSeAcota(): void
    {
        $policy = new RetryPolicy(5, 200, 700);

        $this->assertSame(200, $policy->delayMs(1));
        $this->assertSame(400, $policy->delayMs(2));
        $this->assertSame(700, $policy->delayMs(3), 'No supera maxDelayMs.');
        $this->assertSame(700, $policy->delayMs(9));
    }

    public function testSleepInyectableEvitaEsperarEnTests(): void
    {
        $waits = [];
        $policy = new RetryPolicy(2, 200, 2000, static function (int $ms) use (&$waits): void {
            $waits[] = $ms;
        });

        $policy->waitBeforeRetry(1);
        $policy->waitBeforeRetry(2);

        $this->assertSame([200, 400], $waits);
    }

    private function error(int $statusCode): HttpApiException
    {
        return new HttpApiException('mercadopago', $statusCode, 'error');
    }
}
