<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Emergency\Service\GuardiaIngresoService;

class GuardiaIngresoIdentidadTest extends Unit
{
    public function testPareceIdentidadDniRequiereDocumentoYSexo(): void
    {
        $this->assertFalse(GuardiaIngresoService::pareceIdentidadDni([]));
        $this->assertFalse(GuardiaIngresoService::pareceIdentidadDni([
            'apellido' => 'Alonso',
            'nombre' => 'Ana',
            'documento' => '37123456',
            'fecha_nacimiento' => '1990-01-01',
        ]));
        $this->assertFalse(GuardiaIngresoService::pareceIdentidadDni([
            'documento' => '37123456',
        ]));
        $this->assertTrue(GuardiaIngresoService::pareceIdentidadDni([
            'documento' => '37.123.456',
            'sexo_biologico' => 1,
        ]));
        $this->assertTrue(GuardiaIngresoService::pareceIdentidadDni([
            'codigo_barras' => 'PDF417…',
        ]));
    }

    public function testPareceIdentidadDiditRequiereVerificationId(): void
    {
        $this->assertFalse(GuardiaIngresoService::pareceIdentidadDidit([]));
        $this->assertFalse(GuardiaIngresoService::pareceIdentidadDidit([
            'verification_id' => '   ',
        ]));
        $this->assertTrue(GuardiaIngresoService::pareceIdentidadDidit([
            'verification_id' => 'sess_abc',
        ]));
    }

    public function testPareceIdentidadPendiente(): void
    {
        $this->assertFalse(GuardiaIngresoService::pareceIdentidadPendiente([]));
        $this->assertTrue(GuardiaIngresoService::pareceIdentidadPendiente([
            'identidad_pendiente' => true,
        ]));
        $this->assertTrue(GuardiaIngresoService::pareceIdentidadPendiente([
            'identidad_pendiente' => '1',
        ]));
        $this->assertFalse(GuardiaIngresoService::pareceIdentidadPendiente([
            'identidad_pendiente' => '0',
        ]));
    }
}
