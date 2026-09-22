<?php

namespace common\tests\unit\person;

use Codeception\Test\Unit;
use common\components\Domain\Person\Identity\Application\UseCase\ResolvePersonIdentity;
use common\components\Domain\Person\FrontDesk\Application\Service\FrontDeskSessionConfigService;
use common\models\Person\FrontDeskSession;

class FrontDeskSessionTest extends Unit
{
    protected function _after(): void
    {
        FrontDeskSessionConfigService::reset();
    }

    public function testTtlMinutesDefaultFromYaml(): void
    {
        FrontDeskSessionConfigService::reset();
        $ttl = FrontDeskSessionConfigService::ttlMinutes();
        $this->assertGreaterThanOrEqual(1, $ttl);
        $this->assertLessThanOrEqual(120, $ttl);
        $this->assertSame(15, $ttl);
    }

    public function testUnhidePacienteIntentIdsFromYaml(): void
    {
        FrontDeskSessionConfigService::reset();
        $ids = FrontDeskSessionConfigService::unhidePacienteIntentIds();
        $this->assertContains('turnos.crear-como-paciente', $ids);
        $this->assertContains('turnos.ver-mis-turnos-como-paciente', $ids);
    }

    public function testIsOpenRespectsExpiryAndClosedAt(): void
    {
        $row = new FrontDeskSession();
        $row->closed_at = null;
        $row->expires_at = date('Y-m-d H:i:s', time() + 120);
        $this->assertTrue($row->isOpen());

        $row->expires_at = date('Y-m-d H:i:s', time() - 30);
        $this->assertFalse($row->isOpen());

        $row->expires_at = date('Y-m-d H:i:s', time() + 120);
        $row->closed_at = date('Y-m-d H:i:s');
        $this->assertFalse($row->isOpen());
    }

    public function testResolverPareceIdentidadIgualQueGuardia(): void
    {
        $this->assertFalse(ResolvePersonIdentity::looksLikeDniIdentity([]));
        $this->assertTrue(ResolvePersonIdentity::looksLikeDniIdentity([
            'documento' => '37.123.456',
            'sexo_biologico' => 1,
        ]));
        $this->assertTrue(ResolvePersonIdentity::looksLikeDiditIdentity([
            'verification_id' => 'sess_abc',
        ]));
    }
}
