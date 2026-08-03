<?php

namespace common\tests\unit\auth;

use Codeception\Test\Unit;
use common\models\Platform\DemoSandboxSession;

class DemoSandboxSessionTest extends Unit
{
    public function testSeedPayloadRoundTrip(): void
    {
        $row = new DemoSandboxSession();
        $row->setSeedPayload([
            'paciente_ids' => [1, 2],
            'turno_ids' => [9],
        ]);
        $payload = $row->getSeedPayload();
        $this->assertSame([1, 2], $payload['paciente_ids']);
        $this->assertSame([9], $payload['turno_ids']);
    }

    public function testIsPurgedAndExpired(): void
    {
        $row = new DemoSandboxSession();
        $row->expires_at = '2000-01-01 00:00:00';
        $row->purged_at = null;
        $this->assertTrue($row->isExpired());
        $this->assertFalse($row->isPurged());

        $row->purged_at = '2026-01-01 00:00:00';
        $this->assertTrue($row->isPurged());
    }
}
