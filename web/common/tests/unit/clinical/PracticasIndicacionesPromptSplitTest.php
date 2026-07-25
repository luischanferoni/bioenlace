<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Service\ServiceRequestService;
use common\models\ConsultaIndicaciones;
use common\models\ConsultaPracticas;

/**
 * Separación tipológica: prácticas realizadas vs indicaciones (campos de prompt).
 */
class PracticasIndicacionesPromptSplitTest extends Unit
{
    public function testPracticasPromptFieldsIncludeResultado(): void
    {
        $campos = (new ConsultaPracticas())->requeridosPrompt();
        $this->assertSame(['Practica', 'Resultado', 'Codigo'], $campos);
    }

    public function testIndicacionesPromptFieldsIncludeTipoAndPlazo(): void
    {
        $campos = (new ConsultaIndicaciones())->requeridosPrompt();
        $this->assertSame(['Indicacion', 'Tipo', 'Plazo dias'], $campos);
    }

    public function testResolvePlazoFromIndicacionRow(): void
    {
        $this->assertSame(
            15,
            ServiceRequestService::resolvePlazoDias(['Indicacion' => 'Control', 'Plazo dias' => '15'])
        );
    }
}
