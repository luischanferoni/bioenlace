<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Qa\AsistenteConsultasQaService;

class AsistenteConsultasQaCatalogTest extends Unit
{
    public function testCatalogLoadsAndHasSmokeCases(): void
    {
        $catalog = AsistenteConsultasQaService::loadCatalog();
        $this->assertNotEmpty($catalog['cases']);

        $ids = array_column($catalog['cases'], 'id');
        $this->assertContains('smoke-quiero-turno', $ids);
        $this->assertContains('smoke-sintoma-cabeza', $ids);
    }

    public function testFilterByCoberturaAndSeccion(): void
    {
        $smoke = AsistenteConsultasQaService::filterCases(['Hoy'], 'smoke', null, null);
        $this->assertNotEmpty($smoke);
        foreach ($smoke as $case) {
            $this->assertSame('Hoy', $case['cobertura']);
            $this->assertSame('smoke', $case['seccion']);
            $this->assertNotEmpty($case['mensajes']);
        }

        $borde = AsistenteConsultasQaService::filterCases(null, 'borde', null, null);
        $this->assertNotEmpty($borde);
        $ids = array_column($borde, 'id');
        $this->assertContains('borde-llegar-tarde-10min', $ids);
        $this->assertContains('fuera-sesion-medium', $ids);
    }

    public function testEvaluateExpectUserGoalAndIntent(): void
    {
        $obs = [
            'user_goal' => 'guide',
            'intent_refs' => ['atencion.necesito-atencion'],
            'button_intent_ids' => ['atencion.necesito-atencion'],
            'reply_text' => 'Podés solicitar atención.',
        ];
        $this->assertSame([], AsistenteConsultasQaService::evaluateExpect([
            'user_goal' => 'guide',
            'offer_intent' => 'atencion.necesito-atencion',
            'must_not_intent' => ['turnos.crear-como-paciente'],
        ], $obs));

        $failures = AsistenteConsultasQaService::evaluateExpect([
            'user_goal' => 'operational',
        ], $obs);
        $this->assertNotEmpty($failures);
    }
}
