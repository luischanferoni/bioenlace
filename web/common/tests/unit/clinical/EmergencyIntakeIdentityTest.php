<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Emergency\Application\Service\EmergencyIntakeService;

class EmergencyIntakeIdentityTest extends Unit
{
    public function testPareceIdentidadDniRequiereDocumentoYSexo(): void
    {
        $this->assertFalse(EmergencyIntakeService::looksLikeDniIdentity([]));
        $this->assertFalse(EmergencyIntakeService::looksLikeDniIdentity([
            'apellido' => 'Alonso',
            'nombre' => 'Ana',
            'documento' => '37123456',
            'fecha_nacimiento' => '1990-01-01',
        ]));
        $this->assertFalse(EmergencyIntakeService::looksLikeDniIdentity([
            'documento' => '37123456',
        ]));
        $this->assertTrue(EmergencyIntakeService::looksLikeDniIdentity([
            'documento' => '37.123.456',
            'sexo_biologico' => 1,
        ]));
        $this->assertTrue(EmergencyIntakeService::looksLikeDniIdentity([
            'codigo_barras' => 'PDF417…',
        ]));
    }

    public function testPareceIdentidadDiditRequiereVerificationId(): void
    {
        $this->assertFalse(EmergencyIntakeService::looksLikeDiditIdentity([]));
        $this->assertFalse(EmergencyIntakeService::looksLikeDiditIdentity([
            'verification_id' => '   ',
        ]));
        $this->assertTrue(EmergencyIntakeService::looksLikeDiditIdentity([
            'verification_id' => 'sess_abc',
        ]));
    }

    public function testPareceIdentidadPendiente(): void
    {
        $this->assertFalse(EmergencyIntakeService::looksLikePendingIdentity([]));
        $this->assertTrue(EmergencyIntakeService::looksLikePendingIdentity([
            'identidad_pendiente' => true,
        ]));
        $this->assertTrue(EmergencyIntakeService::looksLikePendingIdentity([
            'identidad_pendiente' => '1',
        ]));
        $this->assertFalse(EmergencyIntakeService::looksLikePendingIdentity([
            'identidad_pendiente' => '0',
        ]));
    }
}
