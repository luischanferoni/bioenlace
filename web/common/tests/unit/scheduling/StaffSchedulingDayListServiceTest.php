<?php

namespace common\tests\unit\scheduling;

use Codeception\Test\Unit;
use common\components\Domain\Scheduling\Home\Application\Service\StaffSchedulingDayListService;
use ReflectionClass;

class StaffSchedulingDayListServiceTest extends Unit
{
    public function testSurgicalAgendaCanBeSerializedAsGenericSection(): void
    {
        $method = (new ReflectionClass(StaffSchedulingDayListService::class))
            ->getMethod('genericSurgerySections');
        $method->setAccessible(true);

        $sections = $method->invoke(null, [
            'id' => 27,
            'id_persona' => 91,
            'paciente' => [
                'nombre_completo' => 'Paciente de prueba',
                'documento' => '12345678',
            ],
            'sala_nombre' => 'Quirófano 1',
            'fecha_hora_inicio' => '2026-10-23 10:00:00',
            'fecha_hora_fin_estimada' => '2026-10-23 12:00:00',
            'estado' => 'CONFIRMADA',
            'estado_label' => 'Confirmada',
        ]);

        $this->assertCount(1, $sections);
        $this->assertSame('surgery_schedule', $sections[0]['key']);
        $this->assertSame('Agenda quirúrgica', $sections[0]['label']);
        $this->assertSame(1, $sections[0]['order']);
        $this->assertSame(
            [
                'id',
                'id_persona',
                'nombre_paciente',
                'documento',
                'sala_nombre',
                'fecha_hora_inicio',
                'fecha_hora_fin_estimada',
                'estado',
                'estado_label',
            ],
            array_column($sections[0]['fields'], 'key')
        );
        $this->assertSame(range(1, 9), array_column($sections[0]['fields'], 'order'));
        $this->assertSame('Paciente de prueba', $sections[0]['fields'][2]['value']);
        $this->assertSame('Confirmada', $sections[0]['fields'][8]['value']);
    }

    public function testGenericSurgicalAgendaPreservesMissingOptionalValues(): void
    {
        $method = (new ReflectionClass(StaffSchedulingDayListService::class))
            ->getMethod('genericSurgerySections');
        $method->setAccessible(true);

        $sections = $method->invoke(null, [
            'id' => 27,
            'id_persona' => 91,
            'paciente' => ['nombre_completo' => 'Sin documento', 'documento' => null],
        ]);

        $this->assertNull($sections[0]['fields'][3]['value']);
        $this->assertNull($sections[0]['fields'][4]['value']);
        $this->assertNull($sections[0]['fields'][5]['value']);
    }
}
