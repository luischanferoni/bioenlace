<?php

namespace common\tests\unit\person;

use Codeception\Test\Unit;
use common\components\Domain\Person\Identidad\Application\Service\PersonaIdentidadResolverService;
use common\components\Domain\Person\Ventanilla\Application\Service\VentanillaSesionConfigService;
use common\models\Person\VentanillaSesion;

class VentanillaSesionTest extends Unit
{
    protected function _after(): void
    {
        VentanillaSesionConfigService::reset();
    }

    public function testTtlMinutesDefaultFromYaml(): void
    {
        VentanillaSesionConfigService::reset();
        $ttl = VentanillaSesionConfigService::ttlMinutes();
        $this->assertGreaterThanOrEqual(1, $ttl);
        $this->assertLessThanOrEqual(120, $ttl);
        $this->assertSame(15, $ttl);
    }

    public function testUnhidePacienteIntentIdsFromYaml(): void
    {
        VentanillaSesionConfigService::reset();
        $ids = VentanillaSesionConfigService::unhidePacienteIntentIds();
        $this->assertContains('turnos.crear-como-paciente', $ids);
        $this->assertContains('turnos.ver-mis-turnos-como-paciente', $ids);
    }

    public function testIsOpenRespectsExpiryAndClosedAt(): void
    {
        $row = new VentanillaSesion();
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
        $this->assertFalse(PersonaIdentidadResolverService::looksLikeDniIdentity([]));
        $this->assertTrue(PersonaIdentidadResolverService::looksLikeDniIdentity([
            'documento' => '37.123.456',
            'sexo_biologico' => 1,
        ]));
        $this->assertTrue(PersonaIdentidadResolverService::looksLikeDiditIdentity([
            'verification_id' => 'sess_abc',
        ]));
    }
}
