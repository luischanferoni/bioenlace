<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\CarePlan\Service\ServiceRequestService;
use common\models\Clinical\ConsultaIndicaciones;
use common\models\Clinical\Input\PracticaInput;

/**
 * Separación tipológica: prácticas realizadas vs indicaciones (campos de prompt).
 */
class PracticasIndicacionesPromptSplitTest extends Unit
{
    public function testPracticasPromptFieldsIncludeResultado(): void
    {
        $campos = PracticaInput::promptFieldNames();
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
