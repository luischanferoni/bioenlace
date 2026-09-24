<?php

namespace common\tests\unit\platform\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Catalog\IntentSemanticsPromptFormatter;
use common\components\Platform\Assistant\Catalog\StateTagIndex;

class StateTagIndexTest extends Unit
{
    protected function _after(): void
    {
        StateTagIndex::resetCacheForTests();
    }

    public function testMedicacionNoIncluyeUrgencia(): void
    {
        $block = IntentSemanticsPromptFormatter::formatStateHits(
            StateTagIndex::match(['medicacion'])
        );

        $this->assertStringContainsString('Medicación', $block);
        $this->assertStringNotContainsString('Urgencia', $block);
        $this->assertStringNotContainsString('Estudio o práctica', $block);
    }

    public function testEstudioNoIncluyeRenovacion(): void
    {
        $block = IntentSemanticsPromptFormatter::formatStateHits(
            StateTagIndex::match(['estudio'])
        );

        $this->assertStringContainsString('Estudio o práctica', $block);
        $this->assertStringNotContainsString('Medicación', $block);
        $this->assertStringNotContainsString('Urgencia', $block);
    }

    public function testTurnoEmpataAtencionYAgenda(): void
    {
        $hits = StateTagIndex::match(['turno']);
        $ids = [];
        foreach ($hits as $hit) {
            $ids[] = $hit['intent_id'];
        }

        $this->assertSame(
            ['atencion.necesito-atencion', 'turnos.crear-como-paciente'],
            $ids
        );
    }

    public function testFraseDePedidoNoEsCategoria(): void
    {
        $this->assertSame([], StateTagIndex::match(['quiero un turno']));
    }

    public function testSintomasEligeAtencion(): void
    {
        $hits = StateTagIndex::match(['sintomas']);
        $ids = [];
        foreach ($hits as $hit) {
            $ids[] = $hit['intent_id'];
        }

        $this->assertSame(['atencion.necesito-atencion'], $ids);
    }
}
