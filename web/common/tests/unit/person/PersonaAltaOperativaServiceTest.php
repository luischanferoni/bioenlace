<?php

namespace common\tests\unit\person;

use Codeception\Test\Unit;
use common\components\Domain\Person\Service\PersonaAltaOperativaService;

final class PersonaAltaOperativaServiceTest extends Unit
{
    public function testNormalizeRequiereCampos(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new PersonaAltaOperativaService())->normalize([
            'apellido' => 'Alonso',
            'nombre' => 'Paciente',
        ]);
    }

    public function testNormalizeAceptaFechaLatinaYSexoLetra(): void
    {
        $n = (new PersonaAltaOperativaService())->normalize([
            'apellido' => 'Alonso',
            'nombre' => 'Paciente',
            'documento' => '37.123.456',
            'fecha_nacimiento' => '15/01/1990',
            'sexo' => 'F',
        ]);

        $this->assertSame('Alonso', $n['apellido']);
        $this->assertSame('Paciente', $n['nombre']);
        $this->assertSame('37123456', $n['documento']);
        $this->assertSame('1990-01-15', $n['fecha_nacimiento']);
        $this->assertSame(1, $n['sexo_biologico']);
    }

    public function testPareceAlta(): void
    {
        $svc = new PersonaAltaOperativaService();
        $this->assertFalse($svc->pareceAlta([]));
        $this->assertTrue($svc->pareceAlta(['apellido' => 'Alonso']));
        $this->assertTrue($svc->pareceAlta(['documento' => '37123456']));
    }
}
