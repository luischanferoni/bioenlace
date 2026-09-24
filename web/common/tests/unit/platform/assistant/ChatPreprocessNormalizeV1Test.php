<?php

namespace common\tests\unit\platform\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Chat\Preprocess\ChatPreprocessService;

class ChatPreprocessNormalizeV1Test extends Unit
{
    public function testNormalizeFromAiV1Fields(): void
    {
        $out = ChatPreprocessService::normalizeFromAi([
            'normalized_text' => '¿Voy a tener problemas si llego 10 minutos tarde?',
            'necesidad_usuario' => 'Saber si hay problema por llegar tarde.',
            'routing_hint' => 'pedido_claro',
            'tags' => ['llegar_tarde', 'scheduling'],
            'context_areas' => ['scheduling'],
            'extractions' => [
                ['span' => '10 minutos', 'category' => 'servicio', 'synonyms' => []],
            ],
            'intent_ids_hint' => [],
        ], 'fallback');

        $this->assertSame('pedido_claro', $out['routing_hint']);
        $this->assertSame('operational', $out['user_goal']);
        $this->assertSame(['llegar_tarde', 'scheduling'], $out['tags']);
        $this->assertSame([], $out['context_areas']);
        $this->assertSame(
            [['expresion' => 'Saber si hay problema por llegar tarde.', 'estado' => 'activa']],
            $out['necesidades_usuario']
        );
        $this->assertCount(1, $out['extractions']);
    }

    public function testNormalizeMultipleNecesidades(): void
    {
        $out = ChatPreprocessService::normalizeFromAi([
            'normalized_text' => 'quiero cancelar el turno y ver mis análisis',
            'necesidad_usuario' => 'Cancelar el turno.',
            'necesidades_usuario' => [
                'Cancelar el turno.',
                'Ver mis análisis.',
            ],
            'routing_hint' => 'pedido_claro_multiple',
            'tags' => [],
            'context_areas' => ['scheduling'],
            'extractions' => [],
        ], 'fallback');

        $this->assertSame('pedido_claro_multiple', $out['routing_hint']);
        $this->assertSame('guide', $out['user_goal']);
        $this->assertSame(
            [
                ['expresion' => 'Cancelar el turno.', 'estado' => 'activa'],
                ['expresion' => 'Ver mis análisis.', 'estado' => 'activa'],
            ],
            $out['necesidades_usuario']
        );
        $this->assertSame("Cancelar el turno.\nVer mis análisis.", $out['necesidad_usuario']);
    }

    public function testNormalizeTagsSanitizesCaseAndSpaces(): void
    {
        $tags = ChatPreprocessService::normalizeTags(['Llegar Tarde', ' scheduling ']);

        $this->assertSame(['llegar_tarde', 'scheduling'], $tags);
    }

    public function testLegacyUserGoalMapsToRoutingHint(): void
    {
        $out = ChatPreprocessService::normalizeFromAi([
            'user_goal' => 'operational',
            'normalized_text' => 'quiero un turno',
        ], 'quiero un turno');

        $this->assertSame('pedido_claro', $out['routing_hint']);
        $this->assertSame('operational', $out['user_goal']);
    }

    public function testInFlowQuestionTagPreservesGoal(): void
    {
        $out = ChatPreprocessService::normalizeFromAi([
            'routing_hint' => 'pedido_claro',
            'tags' => ['in_flow_question'],
            'normalized_text' => '¿y el paso siguiente?',
        ], '¿y el paso siguiente?');

        $this->assertSame('in_flow_question', $out['user_goal']);
    }

    public function testLegacyHintAliasDirectoMapsToPedidoClaro(): void
    {
        $out = ChatPreprocessService::normalizeFromAi([
            'routing_hint' => 'directo',
            'normalized_text' => 'qué es representacion',
        ], 'qué es representacion');

        $this->assertSame('pedido_claro', $out['routing_hint']);
    }

    public function testInvalidRoutingHintBecomesSinPedido(): void
    {
        $out = ChatPreprocessService::normalizeFromAi([
            'routing_hint' => 'invalido',
            'normalized_text' => 'hola',
        ], 'hola');

        $this->assertSame('sin_pedido', $out['routing_hint']);
    }

    public function testNormalizeIgnoresAiContextAreas(): void
    {
        $out = ChatPreprocessService::normalizeFromAi([
            'normalized_text' => 'me duele la cabeza desde ayer',
            'necesidad_usuario' => 'Alivio o orientación por dolor de cabeza.',
            'routing_hint' => 'sin_pedido',
            'tags' => ['dolor', 'sintoma'],
            'context_areas' => ['clinical', 'scheduling'],
            'extractions' => [],
        ], 'me duele la cabeza desde ayer');

        $this->assertSame([], $out['context_areas']);
        $this->assertSame(['dolor', 'sintoma'], $out['tags']);
    }

    public function testNormalizeKeepsActiveAndDropsUnknownState(): void
    {
        $out = ChatPreprocessService::normalizeFromAi([
            'normalized_text' => 'la ecografía sí, el turno de clínica ya lo saqué',
            'necesidades_usuario' => [
                ['expresion' => 'Ya sacó el turno de clínica.', 'estado' => 'satisfecha'],
                ['expresion' => 'Quiere una ecografía.', 'estado' => 'activa'],
                ['expresion' => 'No debería entrar.', 'estado' => 'pendiente'],
            ],
            'routing_hint' => 'pedido_claro',
            'tags' => [],
            'extractions' => [],
        ], 'fallback');

        $this->assertSame(
            [
                ['expresion' => 'Ya sacó el turno de clínica.', 'estado' => 'satisfecha'],
                ['expresion' => 'Quiere una ecografía.', 'estado' => 'activa'],
            ],
            $out['necesidades_usuario']
        );
        $this->assertSame('Quiere una ecografía.', $out['necesidad_usuario']);
    }
}
