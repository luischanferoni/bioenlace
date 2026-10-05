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

    public function testMedicacionIncluyeElRecorridoCompleto(): void
    {
        $block = IntentSemanticsPromptFormatter::formatStateHits(
            StateTagIndex::match(['medicacion'])
        );

        $this->assertStringContainsString('Solicitar Atención', $block);
        $this->assertStringContainsString('Urgencia', $block);
        $this->assertStringContainsString('Estudio o práctica', $block);
        $this->assertStringContainsString("Botón \"Solicitar Atención\"\n└─ ", $block);
    }

    public function testEstudioIncluyeElRecorridoCompleto(): void
    {
        $block = IntentSemanticsPromptFormatter::formatStateHits(
            StateTagIndex::match(['estudio'])
        );

        $this->assertStringContainsString('Estudio o práctica', $block);
        $this->assertStringContainsString('Urgencia', $block);
        $this->assertStringContainsString('Malestar nuevo', $block);
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

    public function testFiebreEligeAtencion(): void
    {
        $hits = StateTagIndex::match(['fiebre']);
        $ids = [];
        foreach ($hits as $hit) {
            $ids[] = $hit['intent_id'];
        }

        $this->assertSame(['atencion.necesito-atencion'], $ids);
    }

    public function testNeedlesIncludeExtractionSpanAndSynonyms(): void
    {
        $needles = StateTagIndex::needles([
            'tags' => ['fiebre'],
            'extractions' => [
                ['span' => 'mi amigo', 'synonyms' => ['otra persona']],
            ],
        ]);

        $this->assertSame(['fiebre', 'mi', 'amigo', 'otra', 'persona'], $needles);
    }
}
