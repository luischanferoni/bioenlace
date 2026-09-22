<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Emergency\Application\Service\EmergencyIntakeService;

class EmergencyIntakeIdentityTest extends Unit
{
    public function testPareceIdentidadDniRequiereDocumentoYSexo(): void
    {
        $this->assertFalse(EmergencyIntakeService::pareceIdentidadDni([]));
        $this->assertFalse(EmergencyIntakeService::pareceIdentidadDni([
            'apellido' => 'Alonso',
            'nombre' => 'Ana',
            'documento' => '37123456',
            'fecha_nacimiento' => '1990-01-01',
        ]));
        $this->assertFalse(EmergencyIntakeService::pareceIdentidadDni([
            'documento' => '37123456',
        ]));
        $this->assertTrue(EmergencyIntakeService::pareceIdentidadDni([
            'documento' => '37.123.456',
            'sexo_biologico' => 1,
        ]));
        $this->assertTrue(EmergencyIntakeService::pareceIdentidadDni([
            'codigo_barras' => 'PDF417…',
        ]));
    }

    public function testPareceIdentidadDiditRequiereVerificationId(): void
    {
        $this->assertFalse(EmergencyIntakeService::pareceIdentidadDidit([]));
        $this->assertFalse(EmergencyIntakeService::pareceIdentidadDidit([
            'verification_id' => '   ',
        ]));
        $this->assertTrue(EmergencyIntakeService::pareceIdentidadDidit([
            'verification_id' => 'sess_abc',
        ]));
    }

    public function testPareceIdentidadPendiente(): void
    {
        $this->assertFalse(EmergencyIntakeService::pareceIdentidadPendiente([]));
        $this->assertTrue(EmergencyIntakeService::pareceIdentidadPendiente([
            'identidad_pendiente' => true,
        ]));
        $this->assertTrue(EmergencyIntakeService::pareceIdentidadPendiente([
            'identidad_pendiente' => '1',
        ]));
        $this->assertFalse(EmergencyIntakeService::pareceIdentidadPendiente([
            'identidad_pendiente' => '0',
        ]));
    }
}
