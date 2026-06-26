<?php

namespace common\tests\unit\agent;

use Codeception\Test\Unit;
use common\components\Domain\Scheduling\Service\TurnoResolucionLinkTokenService;
use Yii;

class TurnoResolucionLinkTokenServiceTest extends Unit
{
    public function testIssueAndVerifyRoundTrip(): void
    {
        $svc = new TurnoResolucionLinkTokenService();
        $token = $svc->issue(42, 1001, 3600);
        $this->assertNotSame('', $token);

        $parsed = $svc->verify($token);
        $this->assertIsArray($parsed);
        $this->assertSame(42, $parsed['id_resolucion']);
        $this->assertSame(1001, $parsed['id_persona']);
    }

    public function testExpiredTokenRejected(): void
    {
        $svc = new TurnoResolucionLinkTokenService();
        $token = $svc->issue(1, 2, -10);
        $this->assertNull($svc->verify($token));
    }

    public function testTamperedTokenRejected(): void
    {
        $svc = new TurnoResolucionLinkTokenService();
        $token = $svc->issue(5, 6, 3600);
        $this->assertNull($svc->verify($token . 'x'));
    }
}
