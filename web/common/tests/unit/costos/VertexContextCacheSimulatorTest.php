<?php

namespace common\tests\unit\costos;

use Yii;
use common\components\Ai\Providers\Google\VertexContextCacheSimulator;
use common\components\Assistant\Chat\Preprocess\ChatPreprocessService;

class VertexContextCacheSimulatorTest extends \Codeception\Test\Unit
{
    protected function _before()
    {
        if (isset(Yii::$app)) {
            Yii::$app->params['vertex_context_cache_simulado'] = true;
        }
        VertexContextCacheSimulator::reset();
    }

    protected function _after()
    {
        VertexContextCacheSimulator::reset();
    }

    public function testSplitPreprocessPrompt()
    {
        if (!isset(Yii::$app)) {
            $this->markTestSkipped('Requiere aplicación Yii.');
        }
        $content = 'kiero un turno con el dra garcia';
        $full = ChatPreprocessService::buildFullPrompt($content);

        $proveedor = [
            'tipo' => 'google',
            'payload' => [],
        ];

        $ok = VertexContextCacheSimulator::assignPromptIfApplicable(
            $proveedor,
            'asistente-preprocess',
            $full
        );

        verify($ok)->true();
        verify(isset($proveedor['payload']['systemInstruction']))->true();
        verify($proveedor['payload']['contents'][0]['parts'][0]['text'])->equals($content);
        verify(strpos(
            $proveedor['payload']['systemInstruction']['parts'][0]['text'],
            'normalized_text'
        ) !== false)->true();
    }
}
