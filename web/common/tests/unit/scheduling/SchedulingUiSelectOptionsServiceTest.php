<?php

namespace common\tests\unit\scheduling;

use Codeception\Test\Unit;
use common\components\Domain\Scheduling\Assistant\SchedulingUiSelectOptionsService;
use common\components\Platform\Ui\UiSelectOptionSourceProviderRegistry;

class SchedulingUiSelectOptionsServiceTest extends Unit
{
    protected function _after(): void
    {
        UiSelectOptionSourceProviderRegistry::resetForTests();
    }

    public function testProfesionalesReturnsEmptyWithoutEfectorServicio(): void
    {
        $options = SchedulingUiSelectOptionsService::resolveProfesionales(
            'profesional-efector-servicio',
            'efector_rrhh',
            [],
            []
        );
        $this->assertSame([], $options);
    }

    public function testSlotsReturnsEmptyWithoutRequiredParams(): void
    {
        $this->assertSame([], SchedulingUiSelectOptionsService::resolveSlotsDisponiblesPaciente([]));
    }

    public function testRegistryRoutesSchedulingSource(): void
    {
        $options = UiSelectOptionSourceProviderRegistry::resolve(
            'slots_disponibles_paciente',
            ['filter' => null],
            []
        );
        $this->assertIsArray($options);
        $this->assertSame([], $options);
    }
}
